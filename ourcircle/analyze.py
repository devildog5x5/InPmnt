"""Plain-language pause-and-verify checks. Never labels a request as safe."""
from __future__ import annotations

import re
from urllib.parse import urlparse

PAUSE = "pause"
CAUTION = "caution"
LOOKALIKE = "lookalike"
UNKNOWN = "unknown"

CORE_RULE = (
    "Never send money, cryptocurrency, gift cards, passwords, or account information "
    "until the request is independently verified."
)

GUIDANCE = "This application offers guidance, not a guarantee."

DISCLAIMER = (
    "OurCircle cannot tell you that something is safe. We help you pause, look for "
    "warning signs, check your family's trusted list, and ask someone you trust "
    "before you act. " + GUIDANCE
)

KNOWN_BRANDS = {
    "irs.gov",
    "ssa.gov",
    "usa.gov",
    "ftc.gov",
    "paypal.com",
    "amazon.com",
    "apple.com",
    "microsoft.com",
    "google.com",
    "wellsfargo.com",
    "chase.com",
    "bankofamerica.com",
    "usbank.com",
    "capitalone.com",
    "aetna.com",
    "uhc.com",
    "anthem.com",
    "medicare.gov",
    "va.gov",
}

GIFT_CARD = re.compile(
    r"\b(gift\s*cards?|steam\s*card|apple\s*card|google\s*play\s*card|itunes|"
    r"vanilla\s*card|moneygram|western\s*union|bitcoin|btc|crypto|usdt|ether|"
    r"wire\s*transfer|cashier'?s?\s*check)\b",
    re.I,
)
SECRECY = re.compile(
    r"\b(don'?t tell|keep this (secret|quiet)|do not (tell|call)|between us|"
    r"your grandson|your grandson'?s? in (jail|trouble)|act now|today only|"
    r"limited time|or else|account (will be )?suspend|warrant for (your )?arrest)\b",
    re.I,
)
REMOTE = re.compile(
    r"\b(anydesk|teamviewer|remote\s*access|let me on your computer|"
    r"install this (app|program)|screen\s*share)\b",
    re.I,
)
URGENCY_PAY = re.compile(
    r"\b(pay (now|immediately|today)|send (it|money) now|wire it|"
    r"before (midnight|they arrest)|confirm (your )?(password|ssn|social|"
    r"routing|account number))\b",
    re.I,
)
PRIZE = re.compile(
    r"\b(you('ve| have)? won|claim your prize|free (iphone|car|vacation)|"
    r"unclaimed (refund|benefit)|overpaid taxes)\b",
    re.I,
)
PHONE_RE = re.compile(r"(?:\+?1[-.\s]?)?(?:\(?\d{3}\)?[-.\s]?)\d{3}[-.\s]?\d{4}")
URL_RE = re.compile(r"https?://[^\s<>]+|(?:www\.)[a-z0-9.-]+\.[a-z]{2,}", re.I)


def digits_only(value: str) -> str:
    d = re.sub(r"\D+", "", value or "")
    if len(d) == 11 and d.startswith("1"):
        d = d[1:]
    return d


def extract_urls(text: str) -> list[str]:
    found = URL_RE.findall(text or "")
    out: list[str] = []
    for raw in found:
        item = raw.strip().rstrip(").,;")
        if item not in out:
            out.append(item)
    return out


def extract_phones(text: str) -> list[str]:
    out: list[str] = []
    seen: set[str] = set()
    for match in PHONE_RE.findall(text or ""):
        d = digits_only(match)
        if len(d) == 10 and d not in seen:
            seen.add(d)
            out.append(d)
    return out


def registrable_domain(url: str) -> str:
    raw = (url or "").strip()
    if not raw:
        return ""
    if "://" not in raw:
        raw = "https://" + raw
    host = (urlparse(raw).hostname or "").lower()
    if host.startswith("www."):
        host = host[4:]
    return host


def _lev(a: str, b: str) -> int:
    if a == b:
        return 0
    if not a:
        return len(b)
    if not b:
        return len(a)
    prev = list(range(len(b) + 1))
    for i, ca in enumerate(a, 1):
        cur = [i]
        for j, cb in enumerate(b, 1):
            cur.append(min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (ca != cb)))
        prev = cur
    return prev[-1]


def lookalike_hits(domain: str, trusted_domains: list[str]) -> list[str]:
    host = registrable_domain(domain)
    if not host:
        host = (domain or "").lower().strip()
        if host.startswith("www."):
            host = host[4:]
    if not host:
        return []
    pool = set(KNOWN_BRANDS)
    for item in trusted_domains:
        cleaned = registrable_domain(item) or (item or "").lower().strip()
        if cleaned:
            pool.add(cleaned)
    hits: list[str] = []
    host_core = host.split(".")[0]
    for brand in sorted(pool):
        if host == brand:
            continue
        base = brand.split(".")[0]
        if len(base) < 4:
            continue
        dist = _lev(host, brand)
        dist_core = _lev(host_core, base)
        contains = len(base) >= 5 and base in host.replace("-", "")
        if dist <= 2 or dist_core <= 1 or contains:
            hits.append(brand)
    return hits


def analyze(
    *,
    text: str = "",
    phone: str = "",
    url: str = "",
    trusted: list[dict] | None = None,
) -> dict:
    """Return a pause-and-verify report. Never includes a 'safe' verdict."""
    blob = " ".join(part for part in (text, phone, url) if part).strip()
    trusted = trusted or []
    trusted_phones = {digits_only(str(row.get("phone") or "")) for row in trusted}
    trusted_phones.discard("")
    trusted_domains = []
    for row in trusted:
        site = str(row.get("website") or row.get("domain") or "")
        host = registrable_domain(site)
        if host:
            trusted_domains.append(host)

    urls = extract_urls(blob)
    if url:
        host = url if "://" in url or "." in url else url
        if host not in urls:
            urls.insert(0, host)
    phones = extract_phones(blob)
    p = digits_only(phone)
    if len(p) == 10 and p not in phones:
        phones.insert(0, p)

    signs: list[str] = []
    matches: list[str] = []
    level = UNKNOWN

    if GIFT_CARD.search(blob):
        signs.append(
            "This asks for a gift card, crypto, wire, or similar hard-to-reverse payment. "
            "Real banks, tax offices, and family emergencies almost never demand that."
        )
        level = PAUSE
    if SECRECY.search(blob):
        signs.append(
            "It pushes you to keep it secret or act before you can talk to anyone. "
            "Scams thrive on isolation and panic."
        )
        level = PAUSE
    if REMOTE.search(blob):
        signs.append(
            "It wants remote access to your computer or phone. Hang up and use a device "
            "they cannot see."
        )
        level = PAUSE
    if URGENCY_PAY.search(blob):
        signs.append(
            "It asks you to pay or share a password / account number right now. "
            "Independent verification comes first."
        )
        if level != PAUSE:
            level = CAUTION
    if PRIZE.search(blob):
        signs.append(
            "Unexpected prizes, refunds, or 'you won' messages are a classic lure. "
            "Do not pay a fee to receive money."
        )
        if level == UNKNOWN:
            level = CAUTION

    for host_src in urls:
        host = registrable_domain(host_src) or host_src.lower()
        likes = lookalike_hits(host_src, trusted_domains)
        if likes:
            signs.append(
                f"The website {host} resembles {', '.join(likes[:3])} but is not an exact match. "
                "Lookalike sites are a common trick."
            )
            matches.append(f"Lookalike of {', '.join(likes[:3])}")
            if level != PAUSE:
                level = LOOKALIKE
        if host in trusted_domains:
            matches.append(f"Domain matches a trusted contact: {host}")
        elif host in KNOWN_BRANDS:
            matches.append(
                f"{host} is a well-known official domain — still call using a number you already have, "
                "not a number in the message."
            )

    for num in phones:
        pretty = f"({num[:3]}) {num[3:6]}-{num[6:]}"
        if num in trusted_phones:
            matches.append(f"{pretty} is on your family's trusted list.")
        else:
            matches.append(
                f"{pretty} is not on your trusted list. Call the organization using a number from "
                "a statement, the back of your card, or a contact you already saved — not this one."
            )
            if level == UNKNOWN:
                level = CAUTION

    if not blob:
        signs.append("Nothing was pasted yet. Add the message, number, or website you were given.")
        level = UNKNOWN
    elif not signs and level == UNKNOWN:
        signs.append(
            "No classic scam phrases jumped out, which does not mean it is genuine. "
            "Pause and verify with your circle anyway."
        )

    titles = {
        PAUSE: "Pause. Do not pay or share anything yet.",
        CAUTION: "Slow down and verify this independently.",
        LOOKALIKE: "This may be pretending to be someone you know.",
        UNKNOWN: "We cannot confirm this. Ask your circle before you act.",
    }
    next_steps = [
        "Do not send money, crypto, gift cards, passwords, or account details yet.",
        "Ask someone in your family circle to look at this with you.",
        "If they want you to pay, use a phone number you already trust — not a number in the message.",
        "If you already paid or shared information, open Report & recover for the next steps.",
    ]
    if level == PAUSE:
        next_steps.insert(
            1,
            "Send a “Please call me before I pay” alert to your circle so nobody is left alone with this.",
        )

    return {
        "level": level,
        "title": titles[level],
        "explanation": " ".join(signs[:3]) if signs else DISCLAIMER,
        "warning_signs": signs,
        "matches": matches,
        "next_steps": next_steps,
        "phones": phones,
        "urls": [registrable_domain(u) or u for u in urls],
        "core_rule": CORE_RULE,
        "disclaimer": DISCLAIMER,
        "never_safe": True,
    }
