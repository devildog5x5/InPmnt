from __future__ import annotations

import os
from dataclasses import dataclass
from typing import Any

PLANS = {
    "starter": {
        "name": "Starter",
        "amount_label": "$19/mo",
        "env_price": "STRIPE_PRICE_STARTER",
    },
    "pro": {
        "name": "Pro",
        "amount_label": "$39/mo",
        "env_price": "STRIPE_PRICE_PRO",
    },
    "annual": {
        "name": "Annual",
        "amount_label": "$99/yr",
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

    @property
    def enabled(self) -> bool:
        return (
            _configured(self.secret_key, prefix="sk_", min_len=20)
            and all(_configured(pid, prefix="price_", min_len=20) for pid in self.prices.values())
        )


def load_stripe_config() -> StripeConfig:
    prices = {
        key: (os.environ.get(meta["env_price"]) or "").strip()
        for key, meta in PLANS.items()
    }
    return StripeConfig(
        secret_key=(os.environ.get("STRIPE_SECRET_KEY") or "").strip(),
        publishable_key=(os.environ.get("STRIPE_PUBLISHABLE_KEY") or "").strip(),
        webhook_secret=(os.environ.get("STRIPE_WEBHOOK_SECRET") or "").strip(),
        base_url=(os.environ.get("BASE_URL") or "http://127.0.0.1:5055").rstrip("/"),
        prices=prices,
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
    for plan, pid in cfg.prices.items():
        if pid and pid == price_id:
            return plan
    return None


def checkout_session_payload(
    *,
    plan: str,
    customer_email: str,
    client_reference_id: str,
    customer_id: str | None = None,
) -> dict[str, Any]:
    if plan not in PLANS:
        raise ValueError("Unknown plan")
    stripe, cfg = get_stripe()
    price = cfg.prices.get(plan)
    if not price:
        raise RuntimeError(f"Missing Stripe price for plan '{plan}'")

    params: dict[str, Any] = {
        "mode": "subscription",
        "line_items": [{"price": price, "quantity": 1}],
        "success_url": f"{cfg.base_url}/billing/success?session_id={{CHECKOUT_SESSION_ID}}",
        "cancel_url": f"{cfg.base_url}/#pricing",
        "client_reference_id": client_reference_id,
        "metadata": {"plan": plan, "user_id": client_reference_id},
        "allow_promotion_codes": True,
        "subscription_data": {"metadata": {"plan": plan, "user_id": client_reference_id}},
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
        return_url=f"{cfg.base_url}/app#/settings",
    )
