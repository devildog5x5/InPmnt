# Family Shield Pro — operator console

A **management console for you**, not for families. Counts, names, emails, phones, circle status, and safe edits. **Not InPmnt.**

Until `ADMIN_PASSWORD` is in `.env`, **`/admin` is not found** (404). It is not in the public nav, sitemap, or family screens. `/healthz` shows `"admin": true` only when the password is configured — not whether someone is signed in.

**Site:** https://familyshieldpro.com

This cannot change `.env`, SMTP, Stripe, or Twilio keys. Families still use Circle and Account for their own people.

## What you can do

- See **households, users, pending invites, trusted contacts, and checks**
- Search by name, email, phone, or circle name
- Edit a login: name, email, mobile, SMS opt-out, optional new password (8+)
- Edit a circle name and the **monthly / yearly plan flag** (does not charge a card)
- Resend or delete a **pending** invite

You cannot delete the last owner, turn 2FA on/off here, or paste secrets. Leave the password field blank to keep the current one.

## Turn it on (Hostinger)

hPanel → **Files → File Manager** → `public_html/.env` (mode **640**).

**Do not replace the whole file.** Add:

```
ADMIN_PASSWORD=a-long-password-you-will-not-forget
# Optional: also open the console after that family email signs in
# ADMIN_EMAIL=Customer_Service@familyshieldpro.com
```

Rules:

- At least **12 characters**
- Placeholders that contain `...` stay **off**
- Do not reuse a family demo password
- Bookmark `https://familyshieldpro.com/admin/login` yourself — we do not advertise it

Save. Open `/admin/login`, enter that operator password (no family email required). Sign out of console when you are done.

`ADMIN_EMAIL` is optional. If that exact login email is already signed in as a family user **and** `ADMIN_PASSWORD` is configured, the Console link appears in the family nav. Stolen family sessions for that mailbox would also get the console — prefer a dedicated operator password and a mailbox only you use.

## Local Flask

Same names in `ourcircle/.env`. Restart `python3 run.py` (port **5065**).

## After an unzip

Skip live `.env` and `data/` so the password and the database stay put.
