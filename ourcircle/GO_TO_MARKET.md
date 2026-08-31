# Family Shield Pro — Go to market

**Product:** OurCircle on **https://familyshieldpro.com**  
**Owner offer:** Family yearly **$119.99** (default) or Family monthly **$14.99**. Stripe is wired — add keys (`STRIPE.md`).  
**Positioning line:** Pause. Ask family. Then pay.

This is **not** an AI scam-detector launch. Families do not buy “our model scored 94%.” They buy a **circle that makes them pause** before they send money, gift cards, crypto, passwords, or account information.

## Who this is for

Primary buyer is the **adult child** who already fields “Mom got a weird text” calls. Secondary buyers are **churches, senior centers, credit unions, and veterans groups** that need a shared pause ritual, not another brochure.

| Segment | Why they pay | First ask |
|---|---|---|
| Adult children of seniors | Fear of a wire / gift-card loss this week | Family year $119.99 (or $14.99/mo) for one household (up to five people) |
| Households that already have a group chat | They need a place to park the screenshot | Same two prices |
| Church / senior / veterans group | Training + circles for members | Quote later — not on the public menu |
| Credit union / insurer partnership | Member protection, not a consumer ad | Per-member later — do not sell this in week one |

Do **not** lead with “AI.” Lead with the rule on every screen:

> Never send money, cryptocurrency, gift cards, passwords, or account information until the request is independently verified.

## Offer (sell this, not the rest)

**Family yearly — $119.99.** Up to five people. Trusted list. Paste a message, number, or URL. “Please call me before I pay.” No “this is safe” stamp.

**Family monthly — $14.99.** Same circle if they will not pay a year up front. Do not list Individual, founding, or group prices on the public page.

This build records a **refundable reservation**, not a Stripe charge, until keys are in `.env`. Checkout is already wired: **[STRIPE.md](STRIPE.md)**. When ten families have reserved a year (or monthly), turn on live keys. Until then, money (or a named hold) is the signal — opinions are not.

## Seven-day paid validation

Share the homepage and `/signup`. Count **Family yearly signups at $119.99**, not likes.

| Day | Action | Done when |
|---|---|---|
| 0 | Hostinger zip live in `public_html`, HTTPS, `robots.txt` + `sitemap.xml` in Search Console | Open https://familyshieldpro.com — no VPS |
| 1 | 20 personal asks: siblings, church admin, one credit-union contact, two Facebook “aging parents” groups (value first) | 20 named conversations |
| 2–3 | 60-second phone-camera demo: paste gift-card text → Pause → tap “Please call me before I pay” | Link in every DM |
| 4–5 | One church bulletin / senior-center flyer: “$119.99 family year — we never say a request is safe” | Flyer in one real hallway |
| 6–7 | Follow up. Collect signups | **Ten Family yearly holds** |

If fewer than ten holds: change the **ask and the channel**, not the product architecture. Do not add more detector features to “make it sell.”

## Channels that fit (and that do not)

**Use**

- Adult-child Facebook groups and Nextdoor (“parent got a bank text”)
- Church / synagogue / mosque offices and senior ministries
- Credit-union community rooms and fraud-prevention staff (one conversation, not a banner ad)
- AARP-adjacent local chapters and veterans posts — **in person**, not spray advertising
- Direct SMS/email to people who already asked you about a scam this year

**Skip for the first week**

- Product Hunt / Hacker News as the main bet
- Google Ads on “scam detector” (wrong intent, expensive, looks like the problem)
- Influencer “AI safety” threads
- Claiming FTC/IC3 partnership you do not have

## Message that converts

**Headline:** Not another AI scam detector. A circle that helps you pause.  
**Proof:** The app never labels a request safe.  
**CTA:** Create a family circle — $119.99/year (or $14.99/month).  
**Logo:** Always Family Shield Pro → https://familyshieldpro.com

Ad / post formula:

1. Concrete scare they already lived (“gift cards for a fake IRS agent”).
2. The pause rule in one sentence.
3. Circle of up to five + “call me before I pay.”
4. Link to familyshieldpro.com — not a PDF, not a Zoom.

## Demo (under 60 seconds)

1. Open familyshieldpro.com — logo goes home.
2. Paste: “Your grandson is in jail. Buy $500 in Apple gift cards and keep this secret.”
3. Show **Pause**, warning signs, core rule. Point out there is no Safe button.
4. Tap **Please call me before I pay**.
5. Close on Family yearly $119.99 (monthly $14.99 as the small print).

## SEO (done in this build)

- Canonical host: `https://familyshieldpro.com`
- `https://familyshieldpro.com/robots.txt` — allow public pages, disallow logged-in paths
- `https://familyshieldpro.com/sitemap.xml` — `/`, `/signup`, `/login`, `/forgot`
- Logged-in shell is `noindex`
- After deploy: Search Console property + submit sitemap

Do not index invite links (`/join`), uploads, checks, or billing.

## Pricing (public)

| Plan | Price | When to push |
|---|---|---|
| Family yearly | $119.99/year | Default CTA |
| Family monthly | $14.99/month | If they will not pay a year up front |
| Group / partnership | Quoted | Church, senior, veterans, credit union — not a public SKU |

Partnership (credit union / insurer) is a **later** conversation with a one-pager, not a public SKU.

## First 30 days — what “working” means

- 10 Family yearly holds (week 1 bar)
- 3 households that actually invited a second person
- 1 church or senior-center intro that will put the URL in a bulletin
- 0 public claims that OurCircle detected or prevented a specific crime

If week 1 misses ten holds, keep the product, change the first sentence and the room you walk into.

## What not to build next

Do not add a public “scam score,” a browser extension, or a feed of stranger reports until founding families are using the circle. The next honest feature after ten paid holds is **real email/SMS for “call me before I pay”** — not a smarter classifier.

## Brand (locked)

- **Site:** Family Shield Pro · https://familyshieldpro.com  
- **Product:** OurCircle  
- **Tagline:** Pause. Ask family. Then pay.  
- **Logo:** every mark links to familyshieldpro.com  
- **Keep off InPmnt:** this is its own Ubuntu deploy, own domain, own `.env`
