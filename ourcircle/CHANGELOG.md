# Family Shield Pro changelog

Product: **Family Shield Pro** (app name in the UI: **OurCircle**).  
Not InPmnt. Version file: `ourcircle/VERSION` (independent of repo-root InPmnt `VERSION`).

## 1.2.14 — 2026-08-29

- Circle invites now **email a join link** when SMTP or Resend is in `.env`. Waiting to join shows the full `https://…/join/…` URL (not a bare token). Hostinger SMTP is used first — PHP `mail()` often claims success without delivering.

## 1.2.13 — 2026-08-29

- Homepage uses the exact line: **We offer Strategy and Tactics to help you and your family prevent being scammed. Not a guarantee. Your circle and us help prevent you from being taken advantage of.**

## 1.2.12 — 2026-08-29

- Homepage: **We offer strategy and tactics to help you and your family prevent being scammed. Not a guarantee.** Your circle and we help prevent you from being taken advantage of.

## 1.2.11 — 2026-08-29

- Guidance line sits under each **Pay / Choose** plan button and next to **Please call me before I pay** on a check. Paying still does not make a request safe.

## 1.2.10 — 2026-08-29

- **This application offers guidance, not a guarantee.** now sits next to pay buttons (Plans, Start a circle, homepage family plans) and on sign-in, join, the check form, report, and billing chat answers. Paying for a plan does not make a request safe.

## 1.2.9 — 2026-08-29

- Circle name example on Start a circle is **The Smith circle** (was The Patel circle).

## 1.2.8 — 2026-08-29

- **Hide** actually closes the help box. The panel used `display: flex`, which kept it on screen after Hide (the `hidden` attribute lost). Hide now sets the box to `display: none` and ignores a follow-up tap on the Help tab.

## 1.2.7 — 2026-08-29

- Hostinger PHP login on `http://127.0.0.1` keeps the session cookie (Secure flag no longer follows the public HTTPS site URL on localhost). Live HTTPS is unchanged.

## 1.2.6 — 2026-08-29

- Help box parks to a right-edge **Help** tab. **Hide** puts it away; tap **Help** to bring it back.

## 1.2.5 — 2026-08-29

- Chat: if someone asks you to send money, the answer is **NO!!!** unless a family member helps you vet it and you are sure it is the person you think it is.

## 1.2.4 — 2026-08-29

- 2FA setup shows a QR code you can scan. “Open authenticator app” is for phones; a refresh no longer mints a new key.

## 1.2.3 — 2026-08-29

- Short disclaimer on the homepage, signed-in screens, checks, and chat: **This application offers guidance, not a guarantee.**

## 1.2.2 — 2026-08-29

- Removed the public **Offers** page (`/offers`), nav link, and sitemap/robots entries.

## 1.2.1 — 2026-08-29

- Homepage footer no longer shows version, Hostinger, robots.txt, or sitemap.xml.

## 1.2.0 — 2026-08-29

- Customer-service email on the homepage: **CustomerService@FamilyShieldPro.com**.
- Site-wide **Chat with us** widget (FAQ; optional OpenAI key — see **SUPPORT.md**). Never says a request is “safe.”
- Phone number slot is in the homepage code, commented out, until a number is assigned.

## 1.1.1 — 2026-08-29

- Homepage explains the service, why we built it (we have been scammed; they keep getting more believable), the core pause rule, and “if it sounds too good to be true, it usually is.”

## 1.1.0 — 2026-08-29

- Password recovery: email reset link (SMTP/Resend) and one-time **recovery codes**.
- Two-factor authentication (authenticator app TOTP) on **Account**. Recovery codes also complete sign-in.

## 1.0.2 — 2026-08-29

- Stripe Checkout, Customer Portal, and webhook are wired for Family monthly **$14.99** and Family yearly **$119.99**.
- Add keys to `.env` (see **STRIPE.md**). Until then, choosing a plan only saves the flag — no charge.

## 1.0.1 — 2026-08-29

- Public pricing is two Family plans only: **$14.99/month** or **$119.99/year** (yearly highlighted).
- Removed Individual, founding $49 SKU, and group tile from the public menu.

## 1.0.0 — 2026-08-28

- First Hostinger PHP drop (`FamilyShieldPro-1.0.0.zip`) — unzip into `public_html`, no VPS.
- `robots.txt` and `sitemap.xml` ship **inside that zip** (zip root, next to `index.php`). Do not fetch them as GitHub raw text.
- Family circle, pause-and-verify checks, trusted list, “Please call me before I pay,” founding $49 reservation.
- Logo always links to https://familyshieldpro.com.
