# Family Shield Pro — customer service

**Product:** OurCircle on https://familyshieldpro.com  
**Not InPmnt.**

## Email (live)

Homepage block **Customer service** (`#contact`) and the chat widget both use:

**CustomerService@FamilyShieldPro.com**

Create that mailbox in hPanel (or forward it) so messages actually arrive.

## Phone (off until you turn it on)

There is **no live phone number** on the site. A placeholder is commented out in code so you can switch it on later without hunting.

**Where it is**

| Place | What to change |
|---|---|
| Homepage (Hostinger / PHP) | `php/views/landing.php` — HTML comment titled `CUSTOMER SERVICE PHONE` just under the email button |
| Homepage (local Flask) | `ourcircle/templates/landing.html` — same comment, same spot |
| Optional constant | `php/src/SupportChat.php` and `ourcircle/support_chat.py` — commented `SUPPORT_PHONE` |
| Notes only | `.env` / `.env.example` — commented `# SUPPORT_PHONE=` (visitors do **not** read this; the homepage HTML is what they see) |

**To bring the phone online**

1. Pick the real number (example: `+1 555 123 4567`).
2. In **both** landing files above, uncomment the `<p class="support-phone">…</p>` line (remove the `<!--` / `-->` wrapper around it).
3. Replace `+1XXXXXXXXXX` in the `tel:` link and `(XXX) XXX-XXXX` in the visible text with that number.
4. Unzip the next zip over `public_html` (skip live `.env` and `data/`), or edit the live `views/landing.php` in File Manager if you only need a hotfix.
5. Optional: in live `.env` add `SUPPORT_PHONE=+1…` as a reminder for yourself.

Do not invent a number in the chatbot — it is told that phone support is unpublished until you do the steps above.

Circle **SMS** (Twilio) is a different thing: family invites, call-me alerts, and forwarding a sketchy text. That is **[SMS.md](SMS.md)**. It is not a customer-service hotline.

## Chatbot (already on every page)

The teal **Help** tab sits on the right edge of every page. **Hide** parks the box; the Help tab brings it back.

- **Works with no AI key.** It answers from a small Family Shield Pro FAQ (plans, login, pause rule, circle of five, billing, contact). It will **never** say a request is “safe.”
- **Optional OpenAI.** Paste a real key in the live `.env` (do not replace the whole file):

```
OPENAI_API_KEY=sk-...
# OPENAI_MODEL=gpt-4o-mini
```

Hostinger needs **curl** enabled (same as Stripe). Endpoint: `POST /support/chat`.

Until the key looks real (`sk-` and not a `...` stub), replies stay on the FAQ. If the API fails, FAQ is the fallback.

Rate limit: about 30 messages per browser session per hour. After that, the widget points people to the support email.
