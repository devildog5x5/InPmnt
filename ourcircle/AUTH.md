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

## Mail (needed for email reset links)

hPanel → `public_html/.env` — **add lines, do not replace the file**.

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

Until mail is set, `/forgot` still accepts the form (same “if that email is on a circle…” message) but **no email is sent**. Use a recovery code, or change the password while signed in.

## Turn on 2FA

Sign in → **Account** → **Turn on 2FA** → add the setup key in the app → enter the 6-digit code → **write down the recovery codes**.

Lost phone: `/forgot` → recovery code + new password, then sign in (if 2FA is still on, use another recovery code at the 2FA screen).
