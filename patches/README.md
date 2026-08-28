# Ubuntu / Hostinger patches

These are the mail + invoice-send + invoice-PDF changes, split so you can apply (or roll back) each piece on its own. Files are LF, mode **644**; directories **755**.

| File | What it is |
|------|------------|
| `001-mail-smtp-fallback.patch` | Resend → Hostinger SMTP fallback, real PHP SMTP, test-email in Settings |
| `002-invoice-send-email.patch` | **Send invoice** actually emails the client (apply after 001) |
| `003-invoice-pdf.patch` | Professional PDF invoice in the email body **and** as an attachment, plus Download PDF (apply after 002) |
| `inpmnt-mail-invoice-ubuntu.patch` | 001 + 002 + 003 in one patch |
| `inpmnt-hostinger-changed.tar.gz` | Drop-in PHP/static files for `public_html` (does **not** overwrite `.env`) |
| `inpmnt-ubuntu-patches.zip` | **Download this** — all of the above in one zip |

Do **not** overwrite a live `.env`. GitHub shows `.patch` as text; always hand the user the **zip** (repo + Cursor artifact download) unless they ask for the diff in chat.

## Hostinger (`public_html`)

```bash
cd ~/public_html
tar -xzf inpmnt-hostinger-changed.tar.gz
find src static -type d -exec chmod 755 {} \;
find src static -type f -exec chmod 644 {} \;
# bootstrap.php lands in public_html/ — keep your live .env
```

The tarball includes `src/InvoicePdf.php`, `static/img/inpmnt-logo-invoice.jpg`, and `fix-ubuntu-perms.sh`.

On **Ubuntu Apache**, after unzip/tar:

```bash
sudo bash fix-ubuntu-perms.sh
# dirs 755, files 644, data/ 775, logo 644 — never chmod -R 777
```

## Ubuntu VPS (git clone)

From the repo root, on `main` (or a tree that matches `main`):

```bash
# all three:
patch -p1 < patches/inpmnt-mail-invoice-ubuntu.patch

# or separately:
patch -p1 < patches/001-mail-smtp-fallback.patch
patch -p1 < patches/002-invoice-send-email.patch
patch -p1 < patches/003-invoice-pdf.patch

find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

Regenerate after future commits:

```bash
bash patches/make-ubuntu-archives.sh
```
