# Ubuntu / Hostinger patches

These are the mail + invoice-send changes, split so you can apply (or roll back) each piece on its own. Files are LF, mode **644**; directories **755**.

| File | What it is |
|------|------------|
| `001-mail-smtp-fallback.patch` | Resend → Hostinger SMTP fallback, real PHP SMTP, test-email in Settings |
| `002-invoice-send-email.patch` | **Send invoice** actually emails the client (apply after 001) |
| `inpmnt-mail-invoice-ubuntu.patch` | 001 + 002 in one patch |
| `inpmnt-ubuntu-patches.zip` | **Download this** — all of the above in one zip |

Do **not** overwrite a live `.env`. GitHub shows `.patch` as text; always hand the user the **zip** (repo + Cursor artifact download) unless they ask for the diff in chat.

## Hostinger (`public_html`)

```bash
cd ~/public_html
tar -xzf inpmnt-hostinger-changed.tar.gz
find src static -type d -exec chmod 755 {} \;
find src static -type f -exec chmod 644 {} \;
```

## Ubuntu VPS (git clone)

From the repo root, on `main` (or a tree that matches `main`):

```bash
# both changes:
patch -p1 < patches/inpmnt-mail-invoice-ubuntu.patch

# or separately:
patch -p1 < patches/001-mail-smtp-fallback.patch
patch -p1 < patches/002-invoice-send-email.patch

find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
```

Regenerate after future commits:

```bash
bash patches/make-ubuntu-archives.sh
```
