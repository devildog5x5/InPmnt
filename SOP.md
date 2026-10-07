# ReceiptGrid owner SOP

Rules for the live site (https://invcpay.com/, PHP on Hostinger shared hosting). Deploy by unzipping the PHP zip into `public_html` with File Manager. There is no SSH.

## Version

- Show the software version as plain text in the **footer only**: `ReceiptGrid vX.Y.Z`.
- Do not put the version in the page `<title>` or in any heading (`h1`–`h6`). Search results should read as the page name, not a build number.
- Keep `VERSION`, `php/VERSION`, `Http::VERSION`, and the comment at the top of `php/index.php` in lockstep. That comment is for a source text search, not a page heading.

## Brand

- **ReceiptGrid** is the customer-facing name. Use **ReceiptGrid Invoicing** when a page needs to distinguish this product from the receipts app.
- The public origin stays `https://invcpay.com`. Do not change canonical URLs to another domain.
- The footer version stays `ReceiptGrid vX.Y.Z`.

## Canonical URL

- The only public origin is `https://invcpay.com` (no www, https, no trailing slash, no `/index.php`).
- www, an explicit `X-Forwarded-Proto: http`, `/index.php`, and trailing slashes 301 to that URL.
- Every public page emits a canonical tag on that same URL. Do not let each host variant point at itself.

## Menu

- Every page, including login, signup, forgot-password, reset-password, settings, and deep app sub-pages, shows the same main menu.
- The menu links to the site’s activities so a visitor is never stuck: Home, Pricing, Reminders, Overdue invoices, Contractors, FAQ, Help, Contact, Privacy, Terms, Security, Refunds, Log in, and Start free trial.
- Logged-in pages may use one logged-in variant (Dashboard, Reminders, Invoices, Clients, Templates, Settings, Help, Contact, the legal pages, Admin when the role is admin, Log out). That variant is the same on every logged-in page, including `/app` (settings and invoice sub-pages) and `/admin`.

## Search files

- `/robots.txt` allows the public marketing pages and disallows `/app`, `/dashboard`, `/admin`, and other private paths. It references the absolute sitemap URL.
- `/sitemap.xml` lists the public pages with `lastmod`: home, pricing, signup, invoice reminders, overdue invoices, contractors, FAQ, support, contact, privacy, terms, security, refunds.

## Share previews and verification

- Public pages include `og:image` and `twitter:image` pointing at `/static/img/og-image.png` (1200×630), plus a canonical URL.
- `google-site-verification` and `msvalidate.01` come from `GOOGLE_SITE_VERIFICATION` and `MSVALIDATE_01`. Leave both empty unless a search console gives you a code.

## PHP banner

- Hide `X-Powered-By` with `expose_php = Off` in `php/.user.ini`, `header_remove('X-Powered-By')`, and `.htaccess` `Header unset` / `php_flag expose_php Off` where the host allows it.

## Do not publish source from the live site

- Never link to GitHub, source trees, or release zips from pages served on the live site.
- `.htaccess` denies `.env`, `.git`, composer files, `*.zip`, `*.sql`, logs, database files, and similar. PHP source under `src/` and `views/` is not web-accessible.
- The deploy zip is an artifact for File Manager. Do not leave that zip, or docs such as this file, inside `public_html`.

## Database

- Public page views must not write SQLite. An unconditional `UPDATE` on every request takes an exclusive lock; overlapping visitors then get HTTP 500 (this took down `/refunds` intermittently).
- Use WAL and a busy timeout so a real write (signup, login, billing) waits instead of crashing the other request.
