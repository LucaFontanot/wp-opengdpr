# WP OpenGDPR

A free, self-hosted GDPR / ePrivacy consent plugin for WordPress. No accounts, no third-party servers, no paywalls.

---

## Modules

WP OpenGDPR has four independent modules — enable only what you need.

### Cookie Consent

Cookie banner, granular category toggles, script blocker, domain-level autoblocker, and consent logging.

- **Banner** — top/bottom, slide/fade/none animation, optional logo, full colour control, optional overlay
- **Preference popup** — per-category toggles (Necessary, Functional, Analytics, Marketing) with optional extended descriptions and a cookie table per category
- **Script Manager** — paste third-party snippets (inline JS, `<script src>`, `<iframe>`); blocked as `type="text/plain"` until consent, then activated client-side without reload
- **Domain Blocker** — define domain + optional path rules; matching `<script>`/`<iframe>`/`<img>` elements are blocked by category before they load (autoblocker runs at `wp_head` priority 0)
- **Detections** — admin-only scanner that records scripts, cookies, iframes, and external assets seen while browsing the site; one-click add to cookie table or domain blocker
- **FAB button** — persistent floating button so visitors can change preferences anytime
- **Shortcode** — `[wpog_consent_button]` renders a "Cookie Settings" link anywhere
- **Policy versioning** — bump the version to re-show the banner to users who consented to an older policy
- **IP anonymisation** — last octet (IPv4) or last 80 bits (IPv6) zeroed before storage
- **Consent logging** — every accept/reject/custom choice stored with timestamp, anonymised IP, user-agent, and policy version; paginated viewer with date/action filters and CSV export
- **EU-only mode** — show the banner only to visitors from European IP ranges
- **Auto log cleanup** — WP-Cron deletes entries older than the configured retention period
- **Settings import/export** — JSON export and import for all plugin settings

### Privacy Consent

GDPR Art. 6/7 consent checkboxes for form submissions. Independent from the cookie module.

- **Contact Form 7** — auto-inject privacy checkbox before submit (or manual `[wpog_privacy_consent]` shortcode)
- **WPForms** — auto-inject privacy checkbox into selected forms
- **Marketing consent** — optional second checkbox for marketing opt-in
- **Configurable labels** — checkbox text supports inline links (`<a>` tags) for policy URLs
- **Blocking** — optionally block form submission when consent is not given
- **Consent logging** — dedicated log table with form ID, type, title, page URL, consent text, and policy version; CSV export
- **Privacy policy versioning** — bump to require re-consent on next form submission

### Terms & Conditions

E-commerce consent module for checkout and registration. Independent from cookie and privacy modules.

- **WooCommerce checkout** — terms, privacy, marketing, and age-verification checkboxes; configurable position (before submit, before/after native terms)
- **WooCommerce Blocks** — full support for block-based checkout via Additional Checkout Fields API
- **WooCommerce registration** — consent checkboxes on the registration form
- **Replace native terms** — optionally replace WooCommerce's default terms checkbox with the plugin's logged version
- **My Account** — privacy consent history endpoint so customers can review their acceptance records
- **WP Privacy API** — exporter and eraser registered for consent records
- **Document versioning** — register new versions with effective dates and change reasons; full version history
- **Inline document preview** — show the latest terms text directly on the checkout page
- **Order integration** — save consent proof to order meta; optional orders-list column
- **HPOS compatible** — declared compatibility with WooCommerce Custom Order Tables
- **Consent logging** — dedicated log table with document type, version, hash, context, and integration source; CSV export

### Legal Document Generator

Client-side wizard that produces structured prompts for AI-powered legal document generation. **No data is ever sent to any external service** — the plugin never calls an AI API. You copy the generated prompt and paste it into your LLM of choice.

- **Document types** — Privacy Policy, Cookie Policy, Terms & Conditions
- **Pre-fill** — reads your existing plugin configuration (cookie categories, scripts, company info) to populate the wizard
- **Supervisory authority picker** — jurisdiction-specific DPA references
- **Prompt history** — saved prompts with name, date, and language; re-open or delete anytime
- **Multi-language** — wizard UI supports any language

---

## How the script blocker works

1. On activation the plugin outputs a tiny inline `<script>` at the very top of `<head>` that exposes `window.wpogConsent` — a plain object with the visitor's current consent state (`{ necessary: true, analytics: false, … }`).
2. Every snippet added via **Script Manager** is rendered with `type="text/plain"` plus a `data-wpog-category` attribute — the browser never executes it.
3. Domain blocker rules match `<script>`, `<iframe>`, and `<img>` elements by their `src` domain; blocked elements get `type="text/plain"` before the browser loads them.
4. When the visitor makes a choice, client-side JS promotes blocked elements to `text/javascript` (or restores `src` / `iframe src`) for allowed categories — **no page reload**.
5. On subsequent page loads the consent cookie is already set, so scripts are rendered executable server-side — zero flash, zero delay.

---

## Requirements

- WordPress **5.9+**
- PHP **7.4+**
- MySQL / MariaDB (two custom tables created on activation)

---

## Installation

### Manual

1. Download or clone this repository.
2. Copy the folder into `wp-content/plugins/`.
3. Activate **WP OpenGDPR** from *Plugins → Installed Plugins*.
4. Go to **GDPR Settings** in the admin sidebar and configure each module.

### Docker (development)

```bash
docker compose up -d
```

WordPress available at `http://localhost:8080`. Plugin folder is mounted as a volume.

---

## Admin menu map

| Menu | Sub-pages |
|---|---|
| **GDPR Settings** | General Settings, Translations, Export / Import, Legal Generator |
| **Cookie Consent** | Cookie Settings, Banner Appearance, Popup Appearance, Categories & Cookies, Script Manager, Domain Blocker, Detections, Consent Logs |
| **Privacy Consent** | Privacy Settings, Texts & Labels, Form Integrations, Form Consent Logs |
| **Terms & Conditions** | General Settings, Documents & Versions, WooCommerce, Acceptance Logs |

---

## Consent cookies

| Cookie | Content |
|---|---|
| `wpog_consent` | JSON-encoded consent state (`{ necessary, functional, analytics, marketing }`) |
| `wpog_consent_id` | Unique UUID generated client-side |
| `wpog_consent_eu` | `1` if the visitor was detected as EU-based (only when EU-only mode is on) |

All are first-party, set client-side, with an expiry matching the configured consent duration (default: 180 days).

---

## Shortcodes

| Shortcode | Purpose |
|---|---|
| `[wpog_consent_button]` | Renders a "Cookie Settings" button that opens the preference popup |
| `[wpog_privacy_consent]` | Manual placement of the privacy consent checkbox in CF7 forms (when auto-inject is off) |

---

## Licence

GPLv2 or later — see [LICENSE](LICENSE).
