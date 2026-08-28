# Family Shield Pro — Go to market

**Product:** OurCircle on **https://familyshieldpro.com**  
**Owner offer:** founding family year **$49** (reservation in this build — no card charge yet)  
**Positioning line:** Pause. Ask family. Then pay.

This is **not** an AI scam-detector launch. Families do not buy “our model scored 94%.” They buy a **circle that makes them pause** before they send money, gift cards, crypto, passwords, or account information.

## Who this is for

Primary buyer is the **adult child** who already fields “Mom got a weird text” calls. Secondary buyers are **churches, senior centers, credit unions, and veterans groups** that need a shared pause ritual, not another brochure.

| Segment | Why they pay | First ask |
|---|---|---|
| Adult children of seniors | Fear of a wire / gift-card loss this week | $49 founding year for one household (up to five people) |
| Households that already have a group chat | They need a place to park the screenshot | Family $14.99/mo after founding year |
| Church / senior / veterans group | Training + circles for members | $299–$999/year |
| Credit union / insurer partnership | Member protection, not a consumer ad | Per-member later — do not sell this in week one |

Do **not** lead with “AI.” Lead with the rule on every screen:

> Never send money, cryptocurrency, gift cards, passwords, or account information until the request is independently verified.

## Offer (sell this, not the rest)

**Founding family — $49 first year.** Up to five people. Trusted list. Paste a message, number, or URL. “Please call me before I pay.” No “this is safe” stamp.

Hold monthly ($7.99 / $14.99) and annual ($119) as the menu after the founding year. Do not discount below $49 in public posts; that is the paid-validation price.

This build records a **refundable reservation**, not a Stripe charge. When ten families have reserved, wire cards. Until then, money (or a named hold) is the signal — opinions are not.

## Seven-day paid validation

Share `/offers` and the homepage. Count **refundable $49 founding holds**, not likes.

| Day | Action | Done when |
|---|---|---|
| 0 | Ubuntu site live, HTTPS, `robots.txt` + `sitemap.xml` in Search Console | `https://familyshieldpro.com/healthz` returns ok |
| 1 | 20 personal asks: siblings, church admin, one credit-union contact, two Facebook “aging parents” groups (value first) | 20 named conversations |
| 2–3 | 60-second phone-camera demo: paste gift-card text → Pause → tap “Please call me before I pay” | Link in every DM |
| 4–5 | One church bulletin / senior-center flyer: “$49 founding circle — we never say a request is safe” | Flyer in one real hallway |
| 6–7 | Follow up. Collect holds on `/offers` or signup | **Ten $49 founding families** |

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
**CTA:** Create a family circle — founding year $49.  
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
5. Close on $49 founding year.

## SEO (done in this build)

- Canonical host: `https://familyshieldpro.com`
- `https://familyshieldpro.com/robots.txt` — allow public pages, disallow logged-in paths
- `https://familyshieldpro.com/sitemap.xml` — `/`, `/signup`, `/login`, `/offers`
- Logged-in shell is `noindex`
- After deploy: Search Console property + submit sitemap

Do not index invite links (`/join`), uploads, checks, or billing.

## Pricing after validation

| Plan | Price | When to push |
|---|---|---|
| Founding family | $49 first year | Until the first 10–50 households |
| Individual | $7.99/mo | Single adult who will not invite family yet |
| Family | $14.99/mo | Default after founding year |
| Annual family | $119/year | “Two months free” vs monthly |
| Group | $299–$999/year | Church / senior / veterans after three household proofs |

Partnership (credit union / insurer) is a **later** conversation with a one-pager, not a public SKU.

## First 30 days — what “working” means

- 10 founding holds (week 1 bar)
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
