"""Stripe Checkout for Family Shield Pro (OurCircle). Not InPmnt."""
from __future__ import annotations

import hashlib
import hmac
import json
import os
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from typing import Any

from flask import has_request_context, request

PRICE_ENV = {
    "monthly": "STRIPE_PRICE_MONTHLY",
    "yearly": "STRIPE_PRICE_YEARLY",
}
PLAN_LABELS = {
    "monthly": "$14.99/month",
    "yearly": "$119.99/year",
}


def _configured(value: str, *, prefix: str = "", min_len: int = 16) -> bool:
    v = (value or "").strip()
    if not v or "..." in v:
        return False
    if prefix and not v.startswith(prefix):
        return False
    return len(v) >= min_len


def public_base() -> str:
    env = (os.environ.get("BASE_URL") or "").strip().rstrip("/")
    if _configured(env, min_len=8) and (env.startswith("https://") or env.startswith("http://")):
        return env
    if has_request_context():
        return request.host_url.rstrip("/")
    return (os.environ.get("OURCIRCLE_SITE_URL") or "http://127.0.0.1:5065").rstrip("/")


@dataclass
class StripeConfig:
    secret_key: str
    publishable_key: str
    webhook_secret: str
    base_url: str
    prices: dict[str, str]

    @property
    def enabled(self) -> bool:
        return _configured(self.secret_key, prefix="sk_", min_len=20) and all(
            _configured(pid, prefix="price_", min_len=20) for pid in self.prices.values()
        )


def load_stripe_config() -> StripeConfig:
    prices = {key: (os.environ.get(env) or "").strip() for key, env in PRICE_ENV.items()}
    return StripeConfig(
        secret_key=(os.environ.get("STRIPE_SECRET_KEY") or "").strip(),
        publishable_key=(os.environ.get("STRIPE_PUBLISHABLE_KEY") or "").strip(),
        webhook_secret=(os.environ.get("STRIPE_WEBHOOK_SECRET") or "").strip(),
        base_url=public_base(),
        prices=prices,
    )


def plan_from_price_id(price_id: str | None) -> str | None:
    if not price_id:
        return None
    cfg = load_stripe_config()
    for plan, pid in cfg.prices.items():
        if pid and pid == price_id:
            return plan
    return None


def _form_pairs(data: Any, prefix: str = "") -> list[tuple[str, str]]:
    items: list[tuple[str, str]] = []
    if isinstance(data, dict):
        for key, val in data.items():
            nxt = f"{prefix}[{key}]" if prefix else str(key)
            items.extend(_form_pairs(val, nxt))
    elif isinstance(data, (list, tuple)):
        for i, val in enumerate(data):
            items.extend(_form_pairs(val, f"{prefix}[{i}]"))
    elif data is True:
        items.append((prefix, "true"))
    elif data is False:
        items.append((prefix, "false"))
    elif data is None:
        return items
    else:
        items.append((prefix, str(data)))
    return items


def stripe_api(method: str, path: str, params: dict[str, Any] | None = None) -> dict[str, Any]:
    cfg = load_stripe_config()
    if not cfg.secret_key:
        raise RuntimeError("STRIPE_SECRET_KEY is not set")
    url = "https://api.stripe.com" + path
    body = urllib.parse.urlencode(_form_pairs(params or {})).encode("utf-8") if params else None
    req = urllib.request.Request(url, data=body, method=method.upper())
    req.add_header("Authorization", "Bearer " + cfg.secret_key)
    if body is not None:
        req.add_header("Content-Type", "application/x-www-form-urlencoded")
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            raw = resp.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="replace")
        try:
            data = json.loads(raw)
        except json.JSONDecodeError:
            raise RuntimeError(raw or "Stripe request failed") from exc
        msg = (data.get("error") or {}).get("message") if isinstance(data, dict) else raw
        raise RuntimeError(str(msg or raw)) from exc
    data = json.loads(raw)
    if not isinstance(data, dict):
        raise RuntimeError("Stripe returned invalid JSON")
    return data


def create_checkout_session(
    *,
    plan: str,
    customer_email: str,
    household_id: int | str,
    user_id: int | str,
    customer_id: str | None = None,
) -> dict[str, Any]:
    if plan not in PRICE_ENV:
        raise ValueError("Unknown plan")
    cfg = load_stripe_config()
    price = cfg.prices.get(plan) or ""
    if not price:
        raise RuntimeError(f"Missing Stripe price for plan '{plan}'")
    hid = str(household_id)
    uid = str(user_id)
    meta = {"plan": plan, "household_id": hid, "user_id": uid, "product": "familyshieldpro"}
    params: dict[str, Any] = {
        "mode": "subscription",
        "line_items": [{"price": price, "quantity": 1}],
        "success_url": cfg.base_url + "/billing/success?session_id={CHECKOUT_SESSION_ID}",
        "cancel_url": cfg.base_url + "/billing",
        "client_reference_id": hid,
        "metadata": meta,
        "allow_promotion_codes": True,
        "subscription_data": {"metadata": dict(meta)},
    }
    if customer_id:
        params["customer"] = customer_id
    else:
        params["customer_email"] = customer_email
    return stripe_api("POST", "/v1/checkout/sessions", params)


def create_portal_session(customer_id: str) -> dict[str, Any]:
    cfg = load_stripe_config()
    return stripe_api(
        "POST",
        "/v1/billing_portal/sessions",
        {"customer": customer_id, "return_url": cfg.base_url + "/billing"},
    )


def retrieve_checkout(session_id: str) -> dict[str, Any]:
    q = urllib.parse.urlencode([("expand[]", "subscription"), ("expand[]", "subscription.items.data.price")])
    return stripe_api("GET", "/v1/checkout/sessions/" + urllib.parse.quote(session_id, safe="") + "?" + q)


def construct_event(payload: str, header: str, secret: str) -> dict[str, Any]:
    parts: dict[str, list[str]] = {}
    for item in header.split(","):
        if "=" not in item:
            continue
        key, val = item.strip().split("=", 1)
        parts.setdefault(key, []).append(val)
    timestamp = (parts.get("t") or [""])[0]
    expected = hmac.new(secret.encode("utf-8"), f"{timestamp}.{payload}".encode("utf-8"), hashlib.sha256).hexdigest()
    ok = False
    for sig in parts.get("v1") or []:
        if hmac.compare_digest(expected, sig):
            ok = True
    if not ok:
        raise RuntimeError("Invalid Stripe signature")
    if abs(time.time() - int(timestamp or "0")) > 300:
        raise RuntimeError("Stripe timestamp too old")
    event = json.loads(payload)
    if not isinstance(event, dict):
        raise RuntimeError("Invalid Stripe event JSON")
    return event
