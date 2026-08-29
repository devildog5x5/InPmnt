"""Family Shield Pro customer-service chat — FAQ plus optional OpenAI."""
from __future__ import annotations

import json
import os
import re
import urllib.error
import urllib.request

from analyze import CORE_RULE, GUIDANCE

SUPPORT_EMAIL = "CustomerService@FamilyShieldPro.com"

# Phone is not live. Homepage markup is commented out (see SUPPORT.md).
# SUPPORT_PHONE = "+1XXXXXXXXXX"

SYSTEM = (
    "You are the Family Shield Pro (OurCircle) customer-service helper at familyshieldpro.com. "
    "Product: a family pause-and-verify circle of up to five people. "
    "Plans: $14.99/month or $119.99/year. "
    "Never tell anyone a request, message, phone number, or website is safe or legitimate. "
    f"Always keep this rule in mind: {CORE_RULE} "
    "If it sounds too good to be true, it usually is. "
    "If they ask whether they should send money because someone asked them to: answer with a resounding NO!!! "
    "Not unless a family member helps them vet it, and they can make sure it is the person they think it is — without a doubt. "
    f"Support email: {SUPPORT_EMAIL}. Do not invent a customer-service phone number — phone support is not published yet. "
    "Circle SMS (Twilio) is optional: invites, call-me alerts, and forwarding a sketchy text. Reply STOP to opt out. Never say a request is safe. "
    "An operator console exists only when ADMIN_PASSWORD is set in .env; it is not a public family page. Do not invent a public admin URL. "
    "Password reset is at /forgot. 2FA is on Account. "
    "Keep answers short (2–6 sentences). Do not ask for passwords, card numbers, or 2FA codes. "
    "If you cannot help, point to the support email."
)

SEND_MONEY_NO = (
    "NO!!! Do not send the money. Not unless a family member helps you vet it, "
    "and you can make sure it is the person you think it is — without a doubt. "
    "Call a number you already have for them, not the one in the message. "
    f"{CORE_RULE}"
)

_FAQ: list[tuple[tuple[str, ...], str]] = [
    (
        (
            "send them money",
            "asks me to send",
            "should i send",
            "should i do it",
            "give them money",
            "wire them",
            "send money",
        ),
        SEND_MONEY_NO,
    ),
    (
        ("safe", "scam", "verify", "legit", "real or", "too good"),
        f"I will never tell you a request is safe. {CORE_RULE} "
        "If it sounds too good to be true, it usually is. Really! Really! Really! "
        "Pause, involve your circle, and call numbers you already trust — not the ones in the message.",
    ),
    (
        ("price", "cost", "plan", "monthly", "yearly", "how much", "subscription", "119", "14.99"),
        "Family Shield Pro is $14.99 per month or $119.99 per year for one circle of up to five people. "
        "Yearly is the better family value. Start at familyshieldpro.com/signup. "
        f"{GUIDANCE} Billing questions: {SUPPORT_EMAIL}.",
    ),
    (
        ("password", "forgot", "reset", "2fa", "two-factor", "two factor", "authenticator", "recovery code"),
        "Forgot your password? Use /forgot. We can email a one-hour reset link if mail is set up, "
        "or you can use a one-time recovery code from when you turned on 2FA. "
        "Turn 2FA on or off under Account after you sign in. "
        f"Stuck? {SUPPORT_EMAIL}.",
    ),
    (
        ("sms", "text message", "text me", "twilio", "forward a text", "text the number"),
        "Circle SMS is optional (Twilio in .env). Save a mobile on Account. Invites and “Please call me before I pay” can go by text. Forward a sketchy message to the Family Shield Pro number to open a check. Reply STOP to opt out. "
        "We never say a request is safe. This is not a customer-service phone. "
        f"{CORE_RULE} Setup: SMS.md. Stuck? {SUPPORT_EMAIL}.",
    ),
    (
        ("admin", "console", "management", "operator", "how many users", "list users"),
        "A management console exists for the product owner when ADMIN_PASSWORD is set in .env (see ADMIN.md). "
        "It shows user counts, names, emails, and safe edits. It is not a family page and is not in the public nav. "
        "Until that password is set, /admin is not found. Families use Circle and Account for their own people. "
        f"Stuck? {SUPPORT_EMAIL}.",
    ),
    (
        ("phone", "call us", "telephone", "support number", "customer service number"),
        f"We do not publish a customer-service phone number yet. Email {SUPPORT_EMAIL} — a person reads every message.",
    ),
    (
        ("invite email", "invite link", "join link", "invitation", "invite someone"),
        "Circle → Invite someone. We email them a join link if mail is set up (SMTP or Resend in .env), and we can text it if Twilio and a mobile number are set. "
        "You can also copy the full https://…/join/… link and tap Resend invite. They do not need to sign in first. "
        f"Stuck? {SUPPORT_EMAIL}.",
    ),
    (
        ("email", "contact", "reach you", "customer service", "support"),
        f"Email customer service at {SUPPORT_EMAIL}. A person reads every message. "
        "There is no public phone number yet.",
    ),
    (
        ("stripe", "billing", "cancel", "refund", "charge", "card", "invoice"),
        "Plans are Family monthly $14.99 or Family yearly $119.99. "
        "If Stripe keys are in .env, Plans sends you to Stripe Checkout; you can manage or cancel in the Stripe customer portal. "
        f"Until keys are live, choosing a plan only saves the flag — no charge. {GUIDANCE} Email {SUPPORT_EMAIL} for billing help.",
    ),
    (
        ("circle", "invite", "member", "five", "5 people", "household"),
        "A circle is up to five people in one household. Invite them from Circle after you sign in. "
        "Circle status is Invited → Invite sent → Invite Accepted → User Accesses the Circle. "
        "Anyone in the circle can look at a check and you can tap “Please call me before I pay” when it is urgent.",
    ),
    (
        ("login", "sign in", "signin", "account", "demo"),
        "Sign in at /login. The demo circle is family@ourcircle.app / password123 (2FA off until you enable it). "
        f"If you are locked out, try /forgot or email {SUPPORT_EMAIL}.",
    ),
    (
        ("what is", "what’s this", "whats this", "ourcircle", "family shield", "how it works", "pause"),
        "Family Shield Pro (OurCircle) is a trusted family circle for sketchy texts, calls, prizes, and urgent payment asks. "
        "It is not an AI that stamps a request as safe. You paste the message, read the warning signs, and get someone you trust on the phone — then you decide.",
    ),
    (
        ("hello", "hi ", "hey", "help", "thanks"),
        f"Hi — I can explain plans, login, the circle of five, and the pause-and-verify rule. "
        f"I will never tell you a request is safe. For a person, email {SUPPORT_EMAIL}.",
    ),
]


def openai_configured() -> bool:
    key = (os.environ.get("OPENAI_API_KEY") or "").strip()
    return key.startswith("sk-") and "..." not in key and len(key) > 24


def _key_hit(key: str, text: str) -> bool:
    if " " in key:
        return key in text
    return re.search(r"\b" + re.escape(key) + r"\b", text) is not None


def faq_reply(message: str) -> str:
    text = (message or "").strip().lower()
    if not text:
        return (
            f"Ask me about plans, login, or how the circle works. "
            f"For a person, email {SUPPORT_EMAIL}."
        )
    best = ""
    best_hits = 0
    for keys, reply in _FAQ:
        hits = sum(1 for k in keys if _key_hit(k, text))
        if hits > best_hits:
            best_hits = hits
            best = reply
    if best_hits:
        return best
    return (
        "I can help with Family Shield Pro plans ($14.99/month or $119.99/year), "
        "login and password reset, the circle of five, and the pause-and-verify rule. "
        f"I will never tell you a request is safe. For anything else, email {SUPPORT_EMAIL}."
    )


def _looks_like_safe_claim(text: str) -> bool:
    t = (text or "").lower()
    return bool(re.search(r"\b(this is safe|it is safe|looks safe|seems safe)\b", t))


def _openai_reply(message: str, history: list) -> str | None:
    if not openai_configured():
        return None
    key = (os.environ.get("OPENAI_API_KEY") or "").strip()
    model = (os.environ.get("OPENAI_MODEL") or "gpt-4o-mini").strip() or "gpt-4o-mini"
    messages: list[dict[str, str]] = [{"role": "system", "content": SYSTEM}]
    if isinstance(history, list):
        for item in history[-8:]:
            if not isinstance(item, dict):
                continue
            role = item.get("role")
            content = str(item.get("content") or "")[:400]
            if role in ("user", "assistant") and content:
                messages.append({"role": str(role), "content": content})
    messages.append({"role": "user", "content": message[:800]})
    payload = json.dumps(
        {
            "model": model,
            "messages": messages,
            "temperature": 0.3,
            "max_tokens": 400,
        }
    ).encode("utf-8")
    req = urllib.request.Request(
        "https://api.openai.com/v1/chat/completions",
        data=payload,
        headers={
            "Authorization": f"Bearer {key}",
            "Content-Type": "application/json",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=20) as resp:
            data = json.loads(resp.read().decode("utf-8"))
        text = str(data["choices"][0]["message"]["content"]).strip()
        if not text or _looks_like_safe_claim(text):
            return None
        return text
    except (urllib.error.URLError, urllib.error.HTTPError, KeyError, IndexError, json.JSONDecodeError, TimeoutError, OSError):
        return None


def handle_chat(message: str, history: list | None = None) -> tuple[str, str]:
    msg = (message or "").strip()
    if len(msg) > 800:
        msg = msg[:800]
    if not msg:
        return faq_reply(""), "faq"
    ai = _openai_reply(msg, history or [])
    if ai:
        return ai, "openai"
    return faq_reply(msg), "faq"
