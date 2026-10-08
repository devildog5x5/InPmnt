from __future__ import annotations

import os
from dataclasses import dataclass
from datetime import date
from typing import Any

PLANS = {
    "monthly": {
        "name": "Monthly",
        "amount_label": "$4.99/mo",
        "env_price": "STRIPE_PRICE_MONTHLY",
    },
    "yearly": {
        "name": "Yearly",
        "amount_label": "$49.99/yr",
        "env_price": "STRIPE_PRICE_YEARLY",
    },
}

# Older subscriptions stored as starter / pro / annual. Not offered at checkout.
LEGACY_PLANS = {
    "starter": {
        "name": "Starter",
        "amount_label": "$10/mo",
        "env_price": "STRIPE_PRICE_STARTER",
    },
    "pro": {
        "name": "Pro",
        "amount_label": "$20/mo",
        "env_price": "STRIPE_PRICE_PRO",
    },
    "annual": {
        "name": "Starter Annual",
        "amount_label": "$100/yr",
        "env_price": "STRIPE_PRICE_ANNUAL",
    },
}


def _configured(value: str, *, prefix: str = "", min_len: int = 16) -> bool:
    """True when a value looks like a real Stripe id, not an .env.example stub."""
    v = (value or "").strip()
    if not v or "..." in v:
        return False
    if prefix and not v.startswith(prefix):
        return False
    return len(v) >= min_len


@dataclass
class StripeConfig:
    secret_key: str
    publishable_key: str
    webhook_secret: str
    base_url: str
    prices: dict[str, str]
    legacy_prices: dict[str, str]

    @property
    def enabled(self) -> bool:
        return (
            _configured(self.secret_key, prefix="sk_", min_len=20)
            and bool(self.prices)
            and all(_configured(pid, prefix="price_", min_len=20) for pid in self.prices.values())
        )


SUPPORT_NOTE = "Inquiries Text: 801.319.1061"
CUSTOMER_SETUP = "Payments are being set up. " + SUPPORT_NOTE + "."
PAID_PLANS = {"monthly", "yearly", "starter", "pro", "annual"}


def config_issues() -> list[str]:
    issues: list[str] = []
    if not _configured(os.environ.get("STRIPE_SECRET_KEY") or "", prefix="sk_", min_len=20):
        issues.append("STRIPE_SECRET_KEY")
    for meta in PLANS.values():
        if not _configured(os.environ.get(meta["env_price"]) or "", prefix="price_", min_len=20):
            issues.append(meta["env_price"])
    return issues


def admin_setup_message() -> str:
    issues = config_issues()
    listed = ", ".join(issues) if issues else "STRIPE_SECRET_KEY, STRIPE_PRICE_MONTHLY, STRIPE_PRICE_YEARLY"
    return (
        "Stripe checkout is off. These keys in public_html/.env are missing or invalid: "
        + listed
        + "."
    )


def days_left(value: str | None) -> int:
    raw = (value or "")[:10]
    try:
        end = date.fromisoformat(raw)
    except ValueError:
        return 0
    return max(0, (end - date.today()).days)


def request_base_url() -> str:
    """Use BASE_URL when it is set. Otherwise use the host on this request."""
    configured = (os.environ.get("BASE_URL") or "").strip().rstrip("/")
    if configured:
        return configured
    try:
        from flask import has_request_context, request

        if has_request_context():
            return request.host_url.rstrip("/")
    except Exception:
        pass
    return "http://127.0.0.1:5055"


def load_stripe_config() -> StripeConfig:
    prices = {
        key: (os.environ.get(meta["env_price"]) or "").strip()
        for key, meta in PLANS.items()
    }
    legacy = {
        key: (os.environ.get(meta["env_price"]) or "").strip()
        for key, meta in LEGACY_PLANS.items()
    }
    return StripeConfig(
        secret_key=(os.environ.get("STRIPE_SECRET_KEY") or "").strip(),
        publishable_key=(os.environ.get("STRIPE_PUBLISHABLE_KEY") or "").strip(),
        webhook_secret=(os.environ.get("STRIPE_WEBHOOK_SECRET") or "").strip(),
        base_url=request_base_url(),
        prices=prices,
        legacy_prices=legacy,
    )


def get_stripe():
    import stripe

    cfg = load_stripe_config()
    if not cfg.secret_key:
        raise RuntimeError("STRIPE_SECRET_KEY is not set")
    stripe.api_key = cfg.secret_key
    return stripe, cfg


def plan_from_price_id(price_id: str | None) -> str | None:
    if not price_id:
        return None
    cfg = load_stripe_config()
    for plan, pid in {**cfg.legacy_prices, **cfg.prices}.items():
        if pid and pid == price_id:
            return plan
    return None


def checkout_session_payload(
    *,
    plan: str,
    customer_email: str,
    client_reference_id: str,
    customer_id: str | None = None,
    workspace_id: int | str | None = None,
) -> dict[str, Any]:
    if plan not in PLANS:
        raise ValueError("Unknown plan")
    stripe, cfg = get_stripe()
    price = cfg.prices.get(plan)
    if not price:
        raise RuntimeError(f"Missing Stripe price for plan '{plan}'")

    meta: dict[str, Any] = {"plan": plan, "user_id": client_reference_id}
    if workspace_id is not None:
        meta["workspace_id"] = str(workspace_id)

    params: dict[str, Any] = {
        "mode": "subscription",
        "line_items": [{"price": price, "quantity": 1}],
        "success_url": f"{cfg.base_url}/billing/success?session_id={{CHECKOUT_SESSION_ID}}",
        "cancel_url": f"{cfg.base_url}/app#/billing",
        "client_reference_id": client_reference_id,
        "metadata": meta,
        "allow_promotion_codes": True,
        "subscription_data": {
            "metadata": dict(meta),
            "description": "InvoicePay",
        },
    }
    if customer_id:
        params["customer"] = customer_id
    else:
        params["customer_email"] = customer_email
    return params


def create_checkout_session(**kwargs) -> Any:
    stripe, _ = get_stripe()
    return stripe.checkout.Session.create(**checkout_session_payload(**kwargs))


def create_portal_session(customer_id: str) -> Any:
    stripe, cfg = get_stripe()
    return stripe.billing_portal.Session.create(
        customer=customer_id,
        return_url=f"{cfg.base_url}/app#/billing",
    )
