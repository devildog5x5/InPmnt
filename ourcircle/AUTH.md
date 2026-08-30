# Family Shield Pro — password reset and 2FA

**Product:** OurCircle on https://familyshieldpro.com  
**Not InPmnt.** Do not paste these into an InPmnt `.env`.

## What families get

1. **Forgot password** at `/forgot`
   - Email link (one hour) if mail is configured
   - **Recovery codes** if they turned on 2FA (works even without mail)
2. **Two-factor authentication** (authenticator app: Google Authenticator, Authy, 1Password, iCloud Keychain)
   - After password, they enter a 6-digit code
   - Recovery codes also complete sign-in (each code once)
3. **Account** (signed in) → change password, turn 2FA on/off, new recovery codes

Demo login `family@ourcircle.app` has 2FA **off** until you turn it on.

## Mail (needed for email reset links and circle invites)

hPanel → `public_html/.env` — **add lines, do not replace the file**.

`MAIL_FROM` and `SMTP_USER` must match the Hostinger mailbox **exactly** (underscores count). Do not wrap `SMTP_PASSWORD` in quotes.

Set **both** site URLs to the host families actually open, including a sandbox host:

```
BASE_URL=https://sandbox.familyshieldpro.com
OURCIRCLE_SITE_URL=https://sandbox.familyshieldpro.com
```

Join and reset links use that host (`{OURCIRCLE_SITE_URL}/join/{token}` and `/reset/{token}`). If the host is wrong, the link 404s or opens a different database.

Hostinger mailbox (typical):

```
MAIL_FROM=hello@familyshieldpro.com
MAIL_FROM_NAME=Family Shield Pro
SMTP_HOST=smtp.hostinger.com
SMTP_PORT=465
SMTP_SSL=1
SMTP_USER=hello@familyshieldpro.com
SMTP_PASSWORD=your-mailbox-password
```

Or Resend:

```
MAIL_FROM=hello@familyshieldpro.com
MAIL_FROM_NAME=Family Shield Pro
RESEND_API_KEY=re_...
```

Until mail is set, `/forgot` **shows that reset email is not set up** instead of pretending a link went out. Use a recovery code, or change the password while signed in.

**Prove it:** Operator console → **Send test email**. If that does not arrive, invites and call-me emails will not either. Check inbox and spam. Last SMTP error is on that same Mail panel.

**Hostinger:** PHP `mail()` is not used. All Family Shield Pro email goes through SMTP only. `SMTP_PASSWORD` is required. Enable **openssl** in hPanel → PHP Configuration. If SMTP fails, `/forgot` and Circle invite flashes show the error. `/healthz` `"mail": true` only when SMTP (with password) or Resend is set. `"version"` should be `1.2.21` after this zip — `0.0.0` means the zip was not unzipped into `public_html`.

**Circle invites:** Circle → **Send invite**. If mail is set, they get a join link by email. The same full link stays under **Waiting to join**. They open `/join/…` without signing in first. Always share the link in a call you already trust if the email is slow or lands in spam.

**Please call me before I pay:** emails every other person already in the circle (same SMTP mailbox). Optional SMS if Twilio is set. This is not InPmnt invoice reminders.

## Turn on 2FA

Sign in → **Account** → **Turn on 2FA** → scan the square (or paste the setup key) → enter the 6-digit code → **write down the recovery codes**.

“Open authenticator app” works on a phone (and some desktop apps such as 1Password). On a computer with no authenticator installed, that button does nothing — scan the QR or copy the key instead. Refreshing the page keeps the same key until you tap **Use a different key**.

Lost phone: `/forgot` → recovery code + new password, then sign in (if 2FA is still on, use another recovery code at the 2FA screen).

## Customer service

Homepage email, **Chat with us** widget, and the commented-out phone number: **[SUPPORT.md](SUPPORT.md)**.
