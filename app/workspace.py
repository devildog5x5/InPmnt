"""Workspace helpers and plan limits."""
from __future__ import annotations

from datetime import date
from typing import Any

from flask import g


STARTER_OPEN_INVOICE_LIMIT = 40


def workspace_id() -> int | None:
    if not g.get("user"):
        return None
    wid = g.user.get("workspace_id")
    return int(wid) if wid is not None else None


def require_workspace_id() -> int:
    wid = workspace_id()
    if wid is None:
        raise RuntimeError("No workspace on user")
    return wid


def get_settings(conn, wid: int | None = None) -> Any:
    wid = wid if wid is not None else require_workspace_id()
    return conn.execute("SELECT * FROM settings WHERE id = ?", (wid,)).fetchone()


def effective_plan(settings) -> str:
    if not settings:
        return "trial"
    plan = (settings["plan"] or "trial").lower()
    trial_ends = settings["trial_ends_on"]
    if plan == "trial" and trial_ends and trial_ends < date.today().isoformat():
        return "expired"
    return plan


def plan_allows_sms(plan: str) -> bool:
    return plan in ("pro",)


def plan_open_invoice_limit(plan: str) -> int | None:
    if plan in ("starter", "annual", "trial"):
        return STARTER_OPEN_INVOICE_LIMIT
    if plan == "pro":
        return None
    if plan == "expired":
        return 0
    return STARTER_OPEN_INVOICE_LIMIT


def count_open_invoices(conn, wid: int) -> int:
    row = conn.execute(
        """
        SELECT COUNT(*) AS c FROM invoices
        WHERE workspace_id = ? AND status IN ('sent','partial','overdue')
        """,
        (wid,),
    ).fetchone()
    return int(row["c"]) if row else 0


def assert_can_add_open_invoice(conn, wid: int, settings) -> str | None:
    """Return error message if blocked, else None."""
    plan = effective_plan(settings)
    if plan == "expired":
        return "Your trial has ended. Subscribe on the Billing page to continue."
    limit = plan_open_invoice_limit(plan)
    if limit is None:
        return None
    if count_open_invoices(conn, wid) >= limit:
        return (
            f"Open invoice limit reached ({limit}). "
            "Upgrade to Pro for unlimited open invoices."
        )
    return None
