# AGENTS.md

> Living technical map of the **WP OpenGDPR** plugin. Keep this in sync
> when adding/removing files, classes, REST routes, options or DB tables.

---

## 1. High-level architecture

```
wp-opengdpr.php  ── bootstraps WPOG_Loader → loads everything → instantiates WPOG_Core
                                                                       │
                                                                       ▼
              ┌───────────────────┬───────────────────┬──────────────────────────┐
              │ WPOG_Public       │ WPOG_Admin        │ REST + AJAX + Cron       │
              │ (frontend render) │ (settings UI)     │ (data ingress)           │
              └─────────┬─────────┴─────────┬─────────┴──────────────┬───────────┘
                        │                   │                        │
              ┌─────────▼──────────┐  ┌─────▼─────────────┐  ┌───────▼────────┐
              │ Banner / Popup /   │  │ 9 admin sub-pages │  │ POST track,    │
              │ FAB templates      │  │ (general, banner, │  │ AJAX consent,  │
              │ + JS:              │  │  popup, cookies,  │  │ daily cleanup  │
              │  wpog-consent.js   │  │  scripts, blocker,│  └────────────────┘
              │  wpog-blocker.js   │  │  tracking, logs,  │
              │  wpog-tracking.js  │  │  i18n, io)        │
              └────────────────────┘  └───────────────────┘
```

---

## 2. Directory layout

```
wp-opengdpr/
├── wp-opengdpr.php                    Plugin header + bootstrap
├── uninstall.php                      Drops options + tables
├── includes/
│   ├── class-wpog-loader.php          Single source of truth for require_once chain
│   ├── class-wpog-core.php            Singleton, hook wiring, activate/deactivate
│   ├── class-wpog-settings.php        Options wrapper + defaults + i18n strings
│   ├── class-wpog-logger.php          {prefix}wpog_consent_log table I/O
│   ├── class-wpog-consent.php         Cookie reader / validator, ajax_save endpoint
│   ├── class-wpog-script-blocker.php  Admin-defined custom-script injector (head/body)
│   ├── blocker/
│   │   └── class-wpog-domain-blocker.php   Domain-rule storage + frontend bootstrap
│   ├── tracking/
│   │   └── class-wpog-tracking.php         {prefix}wpog_detections I/O
│   └── rest/
│       └── class-wpog-rest.php             Registers /wpog/v1/* REST routes
├── admin/
│   ├── class-wpog-admin.php           Menu, asset enqueue, POST router, CSV/JSON export
│   ├── assets/
│   │   ├── admin.css
│   │   └── admin.js                   Repeater UI for cookies / scripts / blocker rules
│   └── views/
│       ├── page-general.php
│       ├── page-banner.php
│       ├── page-popup.php
│       ├── page-cookies.php
│       ├── page-scripts.php           Custom scripts to inject after consent
│       ├── page-blocker.php           NEW — domains to block before consent
│       ├── page-tracking.php          NEW — review detections, promote to blocker
│       ├── page-logs.php
│       ├── page-translations.php
│       └── page-settings-io.php
├── public/
│   ├── class-wpog-public.php          Enqueue, inline styles, banner/popup render
│   └── assets/
│       ├── wpog-banner.css
│       ├── wpog-popup.css
│       ├── wpog-consent.js            Banner+popup logic, consent persistence
│       ├── wpog-blocker.js            NEW — autoblocker (createElement / MutationObserver)
│       └── wpog-tracking.js           NEW — admin-only detection probe
├── templates/
│   ├── banner.php
│   └── popup.php
└── languages/
```

---

## 3. Core classes (one-paragraph reference)

| Class | Responsibility |
|---|---|
| `WPOG_Loader` | Single `load()` that `require_once`s every PHP file. Adding a new module means editing one place. |
| `WPOG_Core` | Singleton; binds hooks, runs activation (`install_table`s + cron schedule), deactivation, daily purge. `maybe_upgrade()` runs on `plugins_loaded` and re-creates missing tables when `wpog_db_version` is stale. |
| `WPOG_Settings` | Wrapper around `wpog_*` options with default values; `string()` resolves admin overrides → `.po` → English fallback. |
| `WPOG_Logger` | Manages `{prefix}wpog_consent_log`. IPv4/IPv6 anonymisation. CSV export feeds from `query()`. |
| `WPOG_Consent` | Server-side reader of `wpog_consent` cookie + AJAX log endpoint. Returns category allow-map. |
| `WPOG_Script_Blocker` | Renders admin-defined snippets (inline / external src / iframe) at `wp_head`/`wp_body_open`/`wp_footer`. Blocks them as `type="text/plain"` until consent. |
| `WPOG_Domain_Blocker` | CRUD over `wpog_blocker_rules` option. Prints inline `WPOG_BLOCKER_CONFIG` + loads `wpog-blocker.js` as the first head asset. `add_rule()` is used by the Detections page to promote a detection. |
| `WPOG_Tracking` | `{prefix}wpog_detections` table I/O: `install_table()`, `ensure_table()` (lazy create on upsert), `upsert()`, `query()`, `set_status()`, `delete()`, `stats()`. |
| `WPOG_REST` | Registers REST routes under `wpog/v1`. Permission callback uses `manage_options`. |
| `WPOG_Public` | Enqueues frontend CSS/JS, localises `WPOG_DATA` (consent runtime config), `WPOG_TRACK` (admin-only). Renders banner+popup+FAB. |
| `WPOG_Admin` | Menu + dispatcher; `handle_post()` switch routes per-form sanitisation; export handlers. |

---

## 4. JavaScript modules

| File | Loaded for | Purpose |
|---|---|---|
| `public/assets/wpog-consent.js` | All visitors | Renders banner/popup, persists `wpog_consent` cookie, activates queued `<script type="text/plain">` after consent, optionally reloads the page (`reload_on_accept`). |
| `public/assets/wpog-blocker.js` | All visitors (when `autoblocker_enabled`) | Overrides `document.createElement` and uses a `MutationObserver` on `document.documentElement` to neuter third-party assets whose domain matches a rule in `WPOG_BLOCKER_CONFIG`. Listens for `wpog:consent` to restore blocked elements once the relevant category is accepted. Extends to shadow DOM via `attachShadow` override. |
| `public/assets/wpog-tracking.js` | Logged-in admins only | Scans the page for `<script src>`, `<iframe src>`, `<img src>`, `<link href>` and `document.cookie` — **every** asset and cookie is recorded, including same-origin ones (only the plugin's own `wpog_consent[_id]` cookies and `data:` URLs are skipped). Deduplicates per page load, batches and `POST`s to `/wpog/v1/track` using a `wp_rest` nonce. Re-scans on DOM mutations and on `beforeunload`. |
| `admin/assets/admin.js` | Admin only | Color picker, media picker, repeatable-row UI for cookies / scripts / blocker rules. |

### Blocker boot contract

`WPOG_Domain_Blocker::render_bootstrap()` emits, at `wp_head` priority 0:

```html
<script id="wpog-blocker-config">window.WPOG_BLOCKER_CONFIG = { rules:[...], consent:{...}, sameOrigin:"…", placeholder:"wpog-lazyload" };</script>
<script id="wpog-blocker-js" src=".../public/assets/wpog-blocker.js"></script>
```

`wpog-blocker.js` reads that config synchronously and installs its hooks
before any other script in the page has the chance to create a network
request. Same-origin URLs and rules whose category already has consent
pass through untouched.

### Tracking → Blocker handoff

When an admin clicks **Block** on the Detections page, `WPOG_Admin::handle_post()`
(`case 'tracking'`) reads the detection, calls `WPOG_Domain_Blocker::add_rule(domain, category, note)`,
and flips the detection's status to `blocked`. The rule is now part of
`WPOG_BLOCKER_CONFIG` on the next page load.

---

## 5. Settings (`wpog_*` options)

| Option | Notes |
|---|---|
| `wpog_general` | Master flag, consent duration, policy version, privacy/cookie URLs, EU-only flag, log toggles, FAB settings, **`reload_on_accept`**, **`autoblocker_enabled`**, **`tracking_enabled`**. |
| `wpog_banner` | Position, colours, font size, animation, overlay, logo. |
| `wpog_popup` | Width, overlay/text/toggle colours, font size, border radius, `show_extended`. |
| `wpog_categories` | The four-category structure with their localised label, description and embedded cookie tables. |
| `wpog_scripts` | Admin-defined custom snippets to inject after consent. |
| `wpog_blocker_rules` | **NEW** — `[ { domain, category, note, active } ]` consumed by the autoblocker. |
| `wpog_translations` | Per-key overrides for `WPOG_Settings::default_strings()`. |
| `wpog_cookie_policy` | Optional HTML appended to the popup body. |
| `wpog_db_version` | Used to detect schema upgrades. |

---

## 6. Database tables

| Table | Owner | Columns |
|---|---|---|
| `{prefix}wpog_consent_log` | `WPOG_Logger` | `id, consent_id, consent_date, ip_address, user_agent, necessary, functional, analytics, marketing, action, policy_version, created_at`. |
| `{prefix}wpog_detections`  | `WPOG_Tracking` | `id, type, value, domain, page_url, first_seen, last_seen, hits, status, category_hint, value_hash`. Unique key on `(type, value_hash)`. |

---

## 7. REST surface

| Route | Method | Auth | Body | Returns |
|---|---|---|---|---|
| `/wpog/v1/track` | POST | `manage_options` + `wp_rest` nonce | `{ items: [ { type, value, domain?, page_url?, category_hint? } ] }` (capped at 500) | `{ ok: true, stored: N }` |

AJAX (still on `admin-ajax.php`):

| Action | Auth | Body | Behaviour |
|---|---|---|---|
| `wpog_save_consent` | public + private | `payload` JSON + `nonce` (`wpog_consent`) | Writes a row in `wpog_consent_log` via `WPOG_Logger::log()`. |

---

## 8. Frontend events

| Event | Dispatched by | Payload | Listeners |
|---|---|---|---|
| `wpog:consent` | `wpog-consent.js` after a save | `{ detail: payload }` | `wpog-blocker.js` (re-evaluates lazy-loaded nodes), site code wanting to react to consent. |

---

## 9. Translation keys (added in this milestone)

`popup_cookies_summary`, `popup_col_name`, `popup_col_provider`,
`popup_col_duration`, `popup_col_purpose`.

---

## 10. Conventions

- All PHP files start with the `ABSPATH` guard.
- Class names: `WPOG_*` in `PascalCase` with underscores.
- Filenames: `class-wpog-<dashed>.php` mirroring the class.
- Public, frontend asset slugs: `wpog-<feature>`.
- Data attributes on the frontend: `data-wpog-*` (`data-wpog-src`,
  `data-wpog-category`, `data-wpog-domain`, `data-wpog-blocked`,
  `data-wpog-unblocked`, `data-wpog-action`, `data-wpog-cat`).
- CSS placeholder class for blocked elements: `wpog-lazyload`.
- Nonces: admin forms use `wpog_admin`; AJAX consent uses `wpog_consent`;
  REST uses the standard `wp_rest` nonce.

---

## 11. Open follow-ups

- Vendor-purpose mapping (multi-category rules per domain) — for now the
  rule is a single category per domain.
- Google Consent Mode v2 default emission.
- An automatic "suggested rules" library (well-known third-parties auto-classified).
- Cookie expiry / size capture in the tracking probe (currently we only
  record cookie names).
