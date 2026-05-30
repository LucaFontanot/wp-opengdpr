# AGENTS.md

> Living technical map of the **WP OpenGDPR** plugin (v1.0.1). Keep this in sync
> when adding/removing files, classes, REST routes, options or DB tables.

---

## Self-update rule

**Any agent or contributor that adds, renames, or removes a file, class, REST
route, DB table, option key, JS global, or hook MUST update the relevant
sections of this file in the same commit.** If a section is missing, add it.
If information is stale, correct it. This file is the source of truth for
onboarding and automated agents.

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
              │ Banner / Popup /   │  │ 3 top-level menus │  │ POST track,    │
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
├── wp-opengdpr.php                    Plugin header + bootstrap (v1.0.1)
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
│   ├── form-consent/
│   │   ├── class-wpog-form-consent.php        Form privacy consent module bootstrap
│   │   ├── class-wpog-form-consent-logger.php {prefix}wpog_form_consent_log I/O
│   │   ├── class-wpog-cf7-integration.php     Contact Form 7 integration
│   │   └── class-wpog-wpforms-integration.php WPForms integration
│   └── rest/
│       └── class-wpog-rest.php             Registers /wpog/v1/* REST routes
├── admin/
│   ├── class-wpog-admin.php           Menu, asset enqueue, POST router, CSV/JSON export
│   ├── assets/
│   │   ├── admin.css
│   │   └── admin.js                   Repeater UI for cookies / scripts / blocker rules
│   └── views/
│       ├── page-general.php           GDPR Settings — shared URLs (privacy_url, cookie_url)
│       ├── page-cookie-settings.php   Cookie Consent — enabled, duration, logs, FAB, autoblocker
│       ├── page-banner.php
│       ├── page-popup.php
│       ├── page-cookies.php
│       ├── page-scripts.php           Custom scripts to inject after consent
│       ├── page-blocker.php           Domain Blocker — manage autoblocker rules
│       ├── page-tracking.php          Detections — review detections, promote to blocker/category
│       ├── page-logs.php
│       ├── page-translations.php
│       ├── page-settings-io.php
│       ├── page-form-consent.php      Privacy Consent — general settings
│       ├── page-form-texts.php        Privacy Consent — checkbox texts & labels
│       ├── page-form-integrations.php Privacy Consent — CF7 / WPForms / form-builder integrations
│       └── page-form-logs.php         Privacy Consent — form consent log + CSV export
├── public/
│   ├── class-wpog-public.php          Enqueue, inline styles, banner/popup render
│   └── assets/
│       ├── wpog-banner.css
│       ├── wpog-popup.css
│       ├── wpog-consent.js            Banner+popup logic, consent persistence
│       ├── wpog-blocker.js            Autoblocker (createElement / MutationObserver)
│       ├── wpog-tracking.js           Admin-only detection probe
│       ├── wpog-form-consent.css      Form consent checkbox styles
│       └── wpog-form-consent.js       Form consent client-side validation (UX)
├── templates/
│   ├── banner.php
│   └── popup.php
└── languages/
```

---

## 3. Core classes (one-paragraph reference)

| Class | Responsibility |
|---|---|
| `WPOG_Loader` | Single `load()` that `require_once`s every PHP file in dependency order. Adding a new module means editing one place. |
| `WPOG_Core` | Singleton; binds hooks, runs activation (`install_table`s + cron schedule), deactivation, daily purge. `maybe_upgrade()` runs on `plugins_loaded` and re-creates missing tables when `wpog_db_version` differs from `WPOG_Settings::DB_VERSION`. |
| `WPOG_Settings` | Wrapper around `wpog_*` options with default values; `string()` resolves admin overrides → `.po` → English fallback. |
| `WPOG_Logger` | Manages `{prefix}wpog_consent_log`. IPv4/IPv6 anonymisation. CSV export feeds from `query()`. |
| `WPOG_Consent` | Server-side reader of `wpog_consent` cookie + AJAX log endpoint. `allowed_categories()` returns category allow-map. |
| `WPOG_Script_Blocker` | Renders admin-defined snippets (inline / external src / iframe) at `wp_head`/`wp_body_open`/`wp_footer`. Blocks them as `type="text/plain"` until consent. |
| `WPOG_Domain_Blocker` | CRUD over `wpog_blocker_rules` option. `render_bootstrap()` runs at `wp_head` priority 0 — prints inline `WPOG_BLOCKER_CONFIG` + loads `wpog-blocker.js`. `add_rule(domain, category, note, path)` is used by the Detections page to promote a detection; uniqueness key is `(domain, path)`. |
| `WPOG_Tracking` | `{prefix}wpog_detections` table I/O: `install_table()`, `ensure_table()` (lazy create on upsert), `upsert()`, `query()`, `set_status()`, `delete()`, `stats()`. |
| `WPOG_Form_Consent` | Bootstrap of the form privacy-consent module (GDPR Art. 6/7) — independent from the cookie consent. `init()` loads `WPOG_CF7_Integration` when CF7 is active, `WPOG_WPForms_Integration` when WPForms is active (`function_exists('wpforms')`), and enqueues `wpog-form-consent.{css,js}` via `wpcf7_enqueue_scripts` (CF7 pages) — the WPForms path enqueues on demand from its injection callback. `main_label()` / `marketing_label()` build the exact user-facing checkbox text; `{privacy_url}` is resolved from `wpog_general.privacy_url` (the shared General Settings URL). |
| `WPOG_Form_Consent_Logger` | `{prefix}wpog_form_consent_log` I/O: `install_table()`, `ensure_table()`, `log()` (always anonymises IP via `WPOG_Logger::anonymize_ip()`, never stores form payload), `query()`, `form_ids()`, `stats()` (totals + 30-day series), `purge_old()`. |
| `WPOG_CF7_Integration` | Contact Form 7 glue. Auto-injects the checkbox before the submit button via `wpcf7_form_elements`, registers `[wpog_privacy_consent]`/`[wpog_marketing_consent]` manual form-tags, blocks submission with missing mandatory consent via `wpcf7_spam`, and logs the proof via `wpcf7_before_send_mail`. Reads consent from `$_POST` (auto-injected raw inputs are not in CF7's `get_posted_data()`). Field constants: `FIELD_MAIN` (`wpog-privacy-consent`), `FIELD_MARKETING` (`wpog-marketing-consent`). |
| `WPOG_WPForms_Integration` | WPForms glue (Lite + Pro). Echoes the consent checkbox(es) before the submit button via the `wpforms_display_submit_before` action (markup shared with CF7 through `WPOG_CF7_Integration::render_consent_checkboxes()`), blocks submission with missing mandatory consent via `wpforms_process` → `wpforms()->process->errors[$form_id]['header']`, and logs the proof via `wpforms_process_complete`. Reads consent from `$_POST` (custom inputs are not in WPForms' `$fields`); `page_url` from `$_POST['wpforms']['page_url']`. Reuses `FIELD_MAIN`/`FIELD_MARKETING` from `WPOG_CF7_Integration`. `wpforms_form_ids` (empty = all) scopes which forms receive injection. |
| `WPOG_REST` | Registers REST routes under `wpog/v1`. Namespace constant: `WPOG_REST::NAMESPACE_V1`. Permission callback: `manage_options`. |
| `WPOG_Public` | Enqueues frontend CSS/JS, localises `WPOG_DATA` (consent runtime config), `WPOG_TRACK` (admin-only, contains `endpoint`, `nonce`, `page_url`). Renders banner+popup+FAB. |
| `WPOG_Admin` | Registers three top-level WP admin menus (**GDPR Settings** slug `wpog`, **Cookie Consent** slug `wpog-cookie`, **Privacy Consent** slug `wpog-privacy`); `route()` maps page slugs to view files; `handle_post()` switch routes per-form sanitisation (`general` saves only shared URLs, `cookie_settings` saves all other cookie-consent keys — both use `WPOG_Settings::update()`); export handlers. Nonce constant: `WPOG_Admin::NONCE`. |

---

## 4. JavaScript modules

| File | Loaded for | Purpose |
|---|---|---|
| `public/assets/wpog-consent.js` | All visitors | Renders banner/popup, persists `wpog_consent` cookie, activates queued `<script type="text/plain">` after consent, optionally reloads the page (`reload_on_accept`). |
| `public/assets/wpog-blocker.js` | All visitors (when `autoblocker_enabled`) | Overrides `document.createElement` and uses a `MutationObserver` on `document.documentElement` to neuter third-party assets whose domain (and optional path prefix) matches a rule in `WPOG_BLOCKER_CONFIG`. Listens for `wpog:consent` to restore blocked elements. Extends to shadow DOM via `attachShadow` override. Exposes `window.WPOG_BLOCKER = { rescan, unblock, rules }` for diagnostics. |
| `public/assets/wpog-tracking.js` | Logged-in admins only | Scans the page for `<script src>`, `<iframe src>`, `<img src>`, `<link href>` and `document.cookie` — records every asset and cookie, skipping the plugin's own `wpog_consent[_id]` cookies and `data:` URLs. Deduplicates per page load, batches and `POST`s to `/wpog/v1/track` using a `wp_rest` nonce (`X-WP-Nonce` header). Re-scans on DOM mutations and on `beforeunload`. |
| `public/assets/wpog-form-consent.js` | Pages with a CF7 or WPForms form | Progressive-enhancement validation for the form consent checkbox. Binds to `.wpcf7-form` and `.wpforms-form`. Relies on the native `required` attribute + server-side validation; this script just adds inline error UX and scroll-into-view on submit. No localisation. |
| `admin/assets/admin.js` | Admin only | Color picker, media picker, repeatable-row UI for cookies / scripts / blocker rules. |

### Blocker boot contract

`WPOG_Domain_Blocker::render_bootstrap()` emits at `wp_head` priority 0:

```html
<script id="wpog-blocker-config">window.WPOG_BLOCKER_CONFIG = {
  rules:      [ { domain, path, category, note, active } ],
  consent:    { necessary, functional, analytics, marketing },
  sameOrigin: "example.com",
  placeholder: "wpog-lazyload"
};</script>
<script id="wpog-blocker-js" src=".../public/assets/wpog-blocker.js?ver=1.0.1"></script>
```

`wpog-blocker.js` reads that config synchronously and installs its hooks
before any other script has the chance to create a network request. Same-origin
URLs and rules whose category already has consent pass through untouched.

Path-based rules: if a rule has a non-empty `path`, only URLs whose pathname
starts with that path are matched, enabling fine-grained blocking of individual
scripts on shared CDN domains (e.g. block `/maps/api` on `google.com`).

### Tracking → Blocker handoff

When an admin clicks **Block** on the Detections page, `WPOG_Admin::handle_post()`
(`case 'tracking'`, action `add_to_blocker`) reads the detection, calls
`WPOG_Domain_Blocker::add_rule(domain, category, note, path)`, and flips the
detection's status to `blocked`. The rule is now part of `WPOG_BLOCKER_CONFIG`
on the next page load.

When an admin clicks **Add to Category**, `WPOG_Admin::handle_post()`
(`case 'add_cookie'`) appends a new cookie entry to the relevant category in
`wpog_categories` and marks the detection as `allowed`.

---

## 5. Settings (`wpog_*` options)

| Option | Notes |
|---|---|
| `wpog_general` | Master flag, consent duration, policy version, **`privacy_url`** (shared — used by cookie banner AND form consent `{privacy_url}` placeholder), **`cookie_url`**, EU-only flag, log toggles, FAB settings, **`reload_on_accept`**, **`autoblocker_enabled`**, **`tracking_enabled`**. Edited across two admin pages: "General Settings" (URLs only) and "Cookie Settings" (all other keys). |
| `wpog_banner` | Position, colours, font size, animation, overlay, logo. |
| `wpog_popup` | Width, overlay/text/toggle colours, font size, border radius, `show_extended`. |
| `wpog_categories` | The four-category structure with their localised label, description and embedded cookie tables. |
| `wpog_scripts` | Admin-defined custom snippets to inject after consent. |
| `wpog_blocker_rules` | `[ { domain, path, category, note, active } ]` consumed by the autoblocker. Uniqueness key: `(domain, path)`. |
| `wpog_translations` | Per-key overrides for `WPOG_Settings::default_strings()`. |
| `wpog_cookie_policy` | Optional HTML appended to the popup body. |
| `wpog_form_consent` | Form privacy-consent module (independent from cookie consent). Keys: `enabled`, `privacy_policy_version`, `block_submit_without_consent`, `log_enabled`, `log_retention_days`, `checkbox_main_enabled`, `checkbox_main_required`, `checkbox_main_label` (supports a single `<a>`, `{privacy_url}` placeholder), `checkbox_main_error`, `checkbox_marketing_enabled`, `checkbox_marketing_required`, `checkbox_marketing_label`, `cf7_enabled`, `cf7_auto_inject`, `cf7_position` (`before_submit`/`after_fields`/`manual`), `cf7_form_ids` (empty = all), `wpforms_enabled`, `wpforms_form_ids` (empty = all). Saved across three admin pages via `WPOG_Settings::update()` (merge). |
| `wpog_db_version` | Used to detect schema upgrades; compared against `WPOG_Settings::DB_VERSION` (currently `1.2.0`). |

---

## 6. Database tables

| Table | Owner | Columns |
|---|---|---|
| `{prefix}wpog_consent_log` | `WPOG_Logger` | `id, consent_id, consent_date, ip_address, user_agent, necessary, functional, analytics, marketing, action, policy_version, created_at`. |
| `{prefix}wpog_detections`  | `WPOG_Tracking` | `id, type, value, domain, page_url, first_seen, last_seen, hits, status, category_hint, value_hash`. Unique key on `(type, value_hash)`. |
| `{prefix}wpog_form_consent_log` | `WPOG_Form_Consent_Logger` | `id, consent_id, form_id, form_type, form_title, page_url, consent_given, marketing_consent, consent_text, privacy_version, ip_address, user_agent, consent_date, created_at`. Audit-trail proof of form consent (GDPR Art. 7). IP always anonymised; form payload never stored. |

Detection `status` values: `new` (default), `blocked`, `allowed`, `ignored`.
Detection `type` values: `script`, `iframe`, `img`, `link`, `cookie`.

---

## 7. REST surface

| Route | Method | Auth | Body | Returns |
|---|---|---|---|---|
| `/wpog/v1/track` | POST | `manage_options` + `X-WP-Nonce: <wp_rest nonce>` header | `{ items: [ { type, value, domain?, page_url?, category_hint? } ] }` (capped at 500) | `{ ok: true, stored: N }` |

AJAX (still on `admin-ajax.php`):

| Action | Auth | Body | Behaviour |
|---|---|---|---|
| `wpog_save_consent` | public + private | `payload` JSON + `nonce` (`wpog_consent`) | Writes a row in `wpog_consent_log` via `WPOG_Logger::log()`. |

---

## 8. Frontend events

| Event | Dispatched by | Payload | Listeners |
|---|---|---|---|
| `wpog:consent` | `wpog-consent.js` after a save | `{ detail: payload }` | `wpog-blocker.js` (re-evaluates lazy-loaded nodes); site code reacting to consent. |

---

## 9. Cron hooks

| Hook | Registered by | Frequency | Handler |
|---|---|---|---|
| `wpog_daily_event` | `WPOG_Core::activate()` | `daily` | `WPOG_Logger::purge_old()` — purges old consent log rows; `WPOG_Form_Consent_Logger::purge_old()` — purges old form-consent log rows (both hooked to the same event). |

---

## 10. Translation keys (added v1.0.x)

`popup_cookies_summary`, `popup_col_name`, `popup_col_provider`,
`popup_col_duration`, `popup_col_purpose`.

---

## 11. Conventions

- All PHP files start with the `ABSPATH` guard.
- Class names: `WPOG_*` in `PascalCase` with underscores.
- Filenames: `class-wpog-<dashed>.php` mirroring the class.
- Public, frontend asset slugs: `wpog-<feature>`.
- Data attributes on the frontend: `data-wpog-*` (`data-wpog-src`,
  `data-wpog-category`, `data-wpog-domain`, `data-wpog-blocked`,
  `data-wpog-unblocked`, `data-wpog-action`, `data-wpog-cat`).
- CSS placeholder class for blocked elements: `wpog-lazyload`.
- Nonces: admin forms use `wpog_admin` (`WPOG_Admin::NONCE`); AJAX consent uses
  `wpog_consent`; REST tracking uses the standard `wp_rest` nonce
  (`X-WP-Nonce` header).
- Blocker rule uniqueness: `(domain, path)` — same domain may have multiple
  path-scoped rules.
- Form consent admin POST inputs use the `wpog_fc[...]` prefix; `wpog_form`
  router values: `form_general`, `form_texts`, `form_integrations`, `form_logs`.
  CSV export action: `wpog_export_form_logs`.
- Form consent field names: `wpog-privacy-consent` (mandatory),
  `wpog-marketing-consent` (optional). Manual CF7 form-tags:
  `[wpog_privacy_consent]`, `[wpog_marketing_consent]`. Both auto and manual
  modes render identical markup with the same field names; checkbox CSS classes:
  `wpog-form-consent-wrapper`, `wpog-consent-field`, `wpog-consent-label`,
  `wpog-consent-error`.

---

## 12. Form privacy consent module (GDPR Art. 6/7)

Legally and technically separate from the cookie consent (ePrivacy Art. 5(3)).
Disabling one does not affect the other; both `WPOG_Form_Consent::init()` and
the cookie stack are wired independently in `WPOG_Core`.

Flow (Contact Form 7):

1. `WPOG_CF7_Integration::inject_consent_checkbox()` (`wpcf7_form_elements`)
   inserts the mandatory (and optional marketing) checkbox before the submit
   button — or appended for `after_fields`; skipped entirely in `manual` mode,
   where `[wpog_privacy_consent]` / `[wpog_marketing_consent]` form-tags are used
   instead. `cf7_form_ids` (empty = all) scopes which forms receive injection.
2. Client side: native `required` + `wpog-form-consent.js` give immediate UX.
3. `validate_consent_on_submit()` (`wpcf7_spam`) blocks the submission when the
   mandatory checkbox is missing (only if `block_submit_without_consent`).
4. `save_consent_log()` (`wpcf7_before_send_mail`, respects `$abort`) writes the
   audit-trail row via `WPOG_Form_Consent_Logger::log()` — recording the EXACT
   checkbox text shown (with `{privacy_url}` resolved), the policy version, the
   anonymised IP and which boxes were ticked. The form payload is never stored.

The checkboxes are never pre-ticked. IP anonymisation is mandatory and not
exposed in the UI.

Flow (WPForms, Lite + Pro):

1. `WPOG_WPForms_Integration::inject_consent_checkbox()` (`wpforms_display_submit_before`)
   echoes the same checkbox markup right before the submit button.
   `wpforms_form_ids` (empty = all) scopes which forms receive injection.
2. Client side: native `required` + `wpog-form-consent.js` (also binds
   `.wpforms-form`) give immediate UX.
3. `validate_consent()` (`wpforms_process`) blocks the submission by setting
   `wpforms()->process->errors[$form_id]['header']` when the mandatory checkbox
   is missing (only if `block_submit_without_consent`).
4. `save_consent_log()` (`wpforms_process_complete`) writes the audit-trail row
   via `WPOG_Form_Consent_Logger::log()` with `form_type = 'wpforms'`, recording
   the same proof data as the CF7 path. The form payload is never stored.

---

## 13. Open follow-ups

- Vendor-purpose mapping (multi-category rules per domain) — for now one rule is
  one category.
- Google Consent Mode v2 default emission.
- Automatic "suggested rules" library (well-known third-parties auto-classified).
- Cookie expiry / size capture in the tracking probe (currently only names are
  recorded).
- Detection page: `iframe`, `img`, `link` types have no action buttons yet.
- Form consent: only Contact Form 7 is implemented; Gravity Forms, WPForms,
  Elementor Forms and a generic WordPress-hook integration are stubbed as
  "coming soon" in the Form Integrations page.
- Form consent appearance is functional-only CSS; no admin colour controls yet.
