# Family Shield Pro — SMS (Twilio)

Optional texts so a circle can invite, ping, and pause from a phone. **Not InPmnt.** Do not paste these keys into an InPmnt `.env`.

Until Twilio is in `.env`, the app **does not send SMS**. Placeholders that contain `...` stay off. `/healthz` shows `"sms": false` until it is live.

**Site:** https://familyshieldpro.com

This is **not** a customer-service hotline. Family Shield Pro still does not publish a support phone (see **[SUPPORT.md](SUPPORT.md)**). Circle SMS is member-to-product, with STOP/START.

## What it does

- **Outbound invite** — Circle → Invite someone, optional mobile. If Twilio is on, they get a join link by text (and email if mail is on). **Invite sent** status counts email **or** SMS.
- **Outbound call-me** — **Please call me before I pay** texts other circle members who saved a mobile on Account and did not opt out.
- **Inbound check** — A member whose number is on Account can forward a sketchy text to the Twilio number. That opens a check. We **never** reply that a request is safe.
- **STOP / START / HELP** — Twilio-style keywords. STOP opts that login out of our texts.

Email is still required to log in. Mobile is optional.

## 1. Twilio number

1. Open [Twilio Console](https://console.twilio.com/) (trial is fine first).
2. Buy a US number that can **send and receive SMS**.
3. Copy **Account SID** (`AC…`), **Auth Token**, and the number in E.164 (`+1…`).

## 2. Inbound webhook

[Twilio → Phone Numbers → the number → Messaging](https://console.twilio.com/us1/develop/phone-numbers/manage/incoming):

**A message comes in** webhook (HTTP POST):

`https://familyshieldpro.com/sms/inbound`

If the live origin is sandbox, use that HTTPS origin + `/sms/inbound`. Twilio cannot reach `127.0.0.1`.

The app checks `X-Twilio-Signature` against `OURCIRCLE_SITE_URL` + `/sms/inbound`. Set `OURCIRCLE_SITE_URL` / `BASE_URL` to the same public HTTPS origin Twilio posts to.

## 3. Paste into Hostinger `.env`

hPanel → **Files → File Manager** → `public_html/.env` (mode **640**).

**Do not replace the whole file** (that would wipe `APP_SECRET`, SMTP, and Stripe). Add or edit:

```
TWILIO_ACCOUNT_SID=ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=+1xxxxxxxxxx
```

Save. PHP **8.2+** with **curl** (same as Stripe). Trial accounts can only text verified numbers until you upgrade.

Stubs that contain `...` or a SID that does not start with `AC` stay off.

## 4. How families use it

1. Each person: **Account → Mobile and SMS** (or the optional mobile on signup / join / invite).
2. Owner: **Circle → Invite someone** with email (required) and mobile (optional) → **Send invite**. **Resend invite** if they missed it.
3. Urgent: open a check → **Please call me before I pay**.
4. From a phone: forward the sketchy text to the Family Shield Pro Twilio number. Sign in on the web to read the full check with the circle.
5. Reply **STOP** to opt out, **START** to turn texts back on, **HELP** for a short reminder.

## Local Flask (optional)

`ourcircle/.env` — same Twilio names. `OURCIRCLE_SITE_URL` must match the public URL Twilio uses (ngrok / Cloudflare Tunnel if you test inbound). Outbound send works from localhost once keys are real.

```bash
cd ourcircle
python3 run.py
```

http://127.0.0.1:5065 — InPmnt stays on 5055.
