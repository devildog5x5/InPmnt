from __future__ import annotations

import json
import os
from datetime import date

from flask import Response, request

TITLE = "InPmnt — Invoice reminders for trades and freelancers"
DESCRIPTION = (
    "Invoice reminder software for plumbers, landscapers, photographers, and consultants. "
    "Track overdue invoices, auto-send payment reminders, and record payments without a full accounting suite."
)

FAQS = [
    {
        "q": "What is InPmnt?",
        "a": "InPmnt is invoice reminder software for solo trades and freelancers. You paste unpaid invoices, set a reminder schedule, and the app queues polite payment nudges so you spend less time chasing late clients.",
    },
    {
        "q": "Who is InPmnt for?",
        "a": "Plumbers, HVAC techs, landscapers, photographers, videographers, and independent consultants who invoice clients and get paid late. It is not a replacement for QuickBooks or a full CRM.",
    },
    {
        "q": "How do invoice payment reminders work?",
        "a": "You add an invoice with a due date and reminder offsets (before due, on the day, and after). InPmnt queues email or SMS templates you control, including a final notice when someone has gone quiet.",
    },
    {
        "q": "How much does InPmnt cost?",
        "a": "Starter is $19/month for up to 40 open invoices. Pro is $39/month for unlimited invoices plus SMS and custom templates. Annual is $99/year with Starter features. All plans start with a 14-day trial.",
    },
]


def origin() -> str:
    host = (request.host or "").strip()
    proto = (request.headers.get("X-Forwarded-Proto") or request.scheme or "https").split(",")[0].strip()
    if host:
        return f"{proto}://{host}".rstrip("/")
    return (os.environ.get("BASE_URL") or "http://127.0.0.1:5055").rstrip("/")


def graph() -> dict:
    root = origin()
    return {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "SoftwareApplication",
                "name": "InPmnt",
                "url": f"{root}/",
                "applicationCategory": "BusinessApplication",
                "operatingSystem": "Web",
                "description": DESCRIPTION,
                "offers": [
                    {"@type": "Offer", "name": "Starter", "price": "19.00", "priceCurrency": "USD"},
                    {"@type": "Offer", "name": "Pro", "price": "39.00", "priceCurrency": "USD"},
                    {"@type": "Offer", "name": "Annual", "price": "99.00", "priceCurrency": "USD"},
                ],
            },
            {
                "@type": "Organization",
                "name": "InPmnt",
                "url": f"{root}/",
                "founder": {"@type": "Person", "name": "Robert Foster"},
            },
            {
                "@type": "FAQPage",
                "mainEntity": [
                    {
                        "@type": "Question",
                        "name": item["q"],
                        "acceptedAnswer": {"@type": "Answer", "text": item["a"]},
                    }
                    for item in FAQS
                ],
            },
        ],
    }


def json_ld() -> str:
    return json.dumps(graph(), separators=(",", ":"), ensure_ascii=False)


def robots_txt() -> Response:
    root = origin()
    body = (
        "User-agent: *\n"
        "Allow: /\n"
        "Allow: /static/\n"
        "Disallow: /app\n"
        "Disallow: /api/\n"
        "Disallow: /login\n"
        "Disallow: /signup\n"
        "Disallow: /logout\n"
        "Disallow: /billing/\n"
        f"Sitemap: {root}/sitemap.xml\n"
    )
    return Response(body, mimetype="text/plain; charset=utf-8", headers={"Cache-Control": "public, max-age=3600"})


def sitemap_xml() -> Response:
    root = origin()
    today = date.today().isoformat()
    body = (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n'
        f"  <url><loc>{root}/</loc><lastmod>{today}</lastmod><changefreq>weekly</changefreq><priority>1.0</priority></url>\n"
        "</urlset>\n"
    )
    return Response(body, mimetype="application/xml; charset=utf-8", headers={"Cache-Control": "public, max-age=3600"})


def llms_txt() -> Response:
    root = origin()
    lines = [
        "InPmnt — Get paid without the chase.",
        "Invoice reminder software for solo trades and freelancers.",
        "",
        f"Site: {root}/",
        f"Signup: {root}/signup",
        f"Pricing: {root}/#pricing",
        "",
        DESCRIPTION,
        "",
    ]
    for item in FAQS:
        lines.append(f"Q: {item['q']}")
        lines.append(f"A: {item['a']}")
        lines.append("")
    return Response("\n".join(lines), mimetype="text/plain; charset=utf-8", headers={"Cache-Control": "public, max-age=3600"})
