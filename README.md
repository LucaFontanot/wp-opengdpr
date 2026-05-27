# WP OpenGDPR

> A simple, free, no-account, no-bullshit GDPR / ePrivacy cookie consent plugin for WordPress.

---

## Why does this exist?

Every GDPR consent plugin in the WordPress ecosystem falls into one of these traps:

- Requires creating an account on an external platform.
- Phones home to a third-party server.
- Is free only for the basic banner and puts everything useful behind a paywall.
- Is bloated with features meant for complex back-end sites (login walls, user data export wizards, …).
- Breaks when you deactivate it because consent data lives on *their* servers.

**WP OpenGDPR** was built for the most common use case: a **frontend-only WordPress site** (blog, portfolio, landing page, brochure site) that just needs a solid, self-hosted cookie consent solution. Everything runs on your server, everything is stored in your database, and nothing is sent anywhere else.

---

## Features

| Feature | Details |
|---|---|
| **Cookie banner** | Top or bottom position, slide / fade / no animation, optional logo, fully configurable colours |
| **Preference popup** | Granular category toggles (Necessary, Functional, Analytics, Marketing) with optional extended descriptions |
| **Script blocker** | Paste third-party snippets (inline JS, external `<script src>`, or `<iframe>`) — they are blocked as `type="text/plain"` until the visitor consents, then activated client-side without a page reload |
| **Consent logging** | Every accept / reject / custom choice is stored in a dedicated DB table with timestamp, anonymised IP, user-agent and policy version |
| **Floating button (FAB)** | Persistent 🍪 button so visitors can change their preferences at any time |
| **Shortcode** | `[wpog_consent_button]` — place a "Cookie Settings" link anywhere in your content |
| **Translations** | All user-facing strings are overridable from the admin panel, importable / exportable as JSON (no `.po` file required) |
| **IP anonymisation** | Last octet (IPv4) or last 80 bits (IPv6) are zeroed before storage |
| **Auto log cleanup** | WP-Cron deletes entries older than the configured retention period (default: 365 days) |
| **Log export** | Download all consent records as a CSV file directly from the admin |
| **Policy versioning** | Bump the policy version to automatically re-show the banner to users who consented to an older version |
| **Zero external dependencies** | No CDN, no third-party API, no account |

---

## Requirements

- WordPress **5.9** or later
- PHP **7.4** or later
- A standard MySQL / MariaDB installation (one extra table is created on activation)

---

## Installation

### Manual (recommended for now)

1. Clone or download this repository.
2. Copy the entire folder into `wp-content/plugins/`.
3. Activate **WP OpenGDPR** from *Plugins → Installed Plugins*.
4. Go to **Cookie Consent** in the WordPress admin sidebar and configure to taste.

### With Docker (development)

A `docker-compose.yml` is included for local development:

```bash
docker compose up -d
```

WordPress will be available at `http://localhost:8080`. The plugin folder is mounted as a volume so changes are reflected immediately.

---

## Configuration

All settings live under the **Cookie Consent** admin menu. Each sub-page is self-explanatory, but here is a quick map:

| Admin page | What you configure |
|---|---|
| **General Settings** | Enable/disable the plugin, consent duration, policy version, privacy & cookie policy URLs, logging, IP anonymisation, FAB button |
| **Banner Appearance** | Position, colours, font size, border radius, animation, optional overlay, logo |
| **Popup Appearance** | Width, overlay colour, toggle colours, font size, border radius |
| **Categories & Cookies** | Names, descriptions, and a cookie table (name, provider, duration, purpose, privacy URL) for each of the four categories |
| **Script Manager** | Add third-party snippets — choose category, type (inline / src / iframe) and injection point (head / body-top / body-bottom) |
| **Consent Logs** | Paginated log viewer with date and action filters, CSV export, manual purge |
| **Translations** | Override every user-facing label; import / export as JSON |

---

## How the script blocker works

1. On activation the plugin outputs a tiny inline `<script>` at the very top of `<head>` that exposes `window.wpogConsent` — a plain object with the visitor's current consent state (e.g. `{ necessary: true, analytics: false, … }`).
2. Every third-party snippet you add via **Script Manager** is rendered with `type="text/plain"` (so the browser never executes it) plus a `data-wpog-category` attribute.
3. When the visitor makes a choice, the client-side JS changes the `type` to `text/javascript` (or sets `src` / `iframe src`) for every allowed category, activating the scripts **without a page reload**.
4. On subsequent page loads the cookie is already set, so the correct scripts are rendered executable from the server side — no flash, no delay.

---

## Consent cookie

The plugin stores consent in a cookie named `wpog_consent` (JSON-encoded). A unique consent ID is stored in `wpog_consent_id`. Both are first-party cookies, written client-side, with an expiry matching the configured **consent duration** (default: 180 days).

---

## Data stored in the database

A single table `{prefix}wpog_consent_log` is created on activation:

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT | Auto-increment PK |
| `consent_id` | VARCHAR(64) | UUID generated client-side |
| `consent_date` | DATETIME | When the choice was made |
| `ip_address` | VARCHAR(45) | Anonymised by default |
| `user_agent` | VARCHAR(255) | Browser UA string |
| `necessary` | TINYINT | Always 1 |
| `functional` | TINYINT | 0 or 1 |
| `analytics` | TINYINT | 0 or 1 |
| `marketing` | TINYINT | 0 or 1 |
| `action` | VARCHAR(20) | `accept_all`, `reject_all`, or `custom` |
| `policy_version` | VARCHAR(20) | Matches the configured version |

On deactivation the scheduled cleanup job is removed. On uninstall (`uninstall.php`) all plugin options and the log table are deleted.

---

## Shortcode

Place a "Cookie Settings" button anywhere in a post or page:

```
[wpog_consent_button]
```

The button label can be customised from **Translations → Shortcode button label**.

---

## Licence

GPLv2 or later — see [LICENSE](LICENSE).
