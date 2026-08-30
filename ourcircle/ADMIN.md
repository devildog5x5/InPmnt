# Family Shield Pro — operator console

A **management console for you**, not for families. Add, edit, and delete circles, logins, invites, trusted contacts, and checks. **Not InPmnt.**

Until `ADMIN_PASSWORD` is in `.env`, **`/admin` is not found** (404). It is not in the public nav, sitemap, or family screens. `/healthz` shows `"admin": true` only when the password is configured — not whether someone is signed in.

**Site:** https://familyshieldpro.com

This cannot change `.env`, SMTP, Stripe, or Twilio keys. It does show whether mail is configured and the last mail attempt (no passwords).

## What you can do

- See **households, users, pending invites, trusted contacts, and checks**
- **Add / edit / delete** circles (a circle delete removes everyone in it)
- **Add / edit / delete** logins (name, email, mobile, password, role, which circle)
- Turn **2FA off** if someone is locked out
- **Add / resend / delete** pending invites
- **Add / delete** trusted contacts
- **Delete** checks

The **last owner** of a circle cannot be deleted or demoted. Add another owner first, or delete the whole circle.

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

`ADMIN_EMAIL` is optional. If that exact login email is already signed in as a family user **and** `ADMIN_PASSWORD` is configured, the Console link appears in the family nav.

## Local Flask

Same names in `ourcircle/.env`. Restart `python3 run.py` (port **5065**).

## After an unzip

Skip live `.env` and `data/` so the password and the database stay put.
