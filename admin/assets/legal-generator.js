/* =========================================================================
 * WP OpenGDPR — Legal Document Prompt Generator (admin wizard)
 *
 * Self-contained vanilla-JS wizard. All logic — validation, conditional
 * fields, pre-fill, draft persistence and prompt assembly — runs client-side.
 * The plugin never sends data to any external AI service: the assembled
 * prompt is copied by the user. The only server interaction is saving /
 * loading prompts through the wpog/v1/legal-generator REST routes.
 *
 * Globals provided via wp_localize_script as `wpogLegalGen`:
 *   restUrl, nonce, prefill, authorities
 * ========================================================================= */
(function () {
    'use strict';

    var i18n = (window.wp && window.wp.i18n) || {};
    var __ = i18n.__ || function (s) {
        return s;
    };
    var sprintf = i18n.sprintf || function (s) {
        return s;
    };
    var TXT = 'wp-opengdpr';

    var CFG = window.wpogLegalGen || {};
    var AUTH = CFG.authorities || {};
    var PREFILL = CFG.prefill || {};
    var DRAFT_KEY = 'wpog_legal_wizard_draft';
    var TOTAL = 8;

    /* --------------------------------------------------------------------- */
    /* Reference data                                                        */
    /* --------------------------------------------------------------------- */

// EU/EEA member states (+ recognised adequacy peers handled separately).
    var EU = {
        AT: 1, BE: 1, BG: 1, HR: 1, CY: 1, CZ: 1, DK: 1, EE: 1, FI: 1, FR: 1, DE: 1, GR: 1,
        HU: 1, IE: 1, IT: 1, LV: 1, LT: 1, LU: 1, MT: 1, NL: 1, PL: 1, PT: 1, RO: 1, SK: 1,
        SI: 1, ES: 1, SE: 1, IS: 1, LI: 1, NO: 1
    };

    var COUNTRIES = [
        ['IT', 'Italy'], ['DE', 'Germany'], ['FR', 'France'], ['ES', 'Spain'], ['NL', 'Netherlands'],
        ['BE', 'Belgium'], ['AT', 'Austria'], ['PT', 'Portugal'], ['IE', 'Ireland'], ['PL', 'Poland'],
        ['SE', 'Sweden'], ['DK', 'Denmark'], ['FI', 'Finland'], ['GR', 'Greece'], ['CZ', 'Czechia'],
        ['RO', 'Romania'], ['HU', 'Hungary'], ['BG', 'Bulgaria'], ['HR', 'Croatia'], ['SK', 'Slovakia'],
        ['SI', 'Slovenia'], ['LU', 'Luxembourg'], ['LT', 'Lithuania'], ['LV', 'Latvia'], ['EE', 'Estonia'],
        ['CY', 'Cyprus'], ['MT', 'Malta'], ['NO', 'Norway'], ['IS', 'Iceland'], ['LI', 'Liechtenstein'],
        ['GB', 'United Kingdom'], ['CH', 'Switzerland'], ['US', 'United States'], ['CA', 'Canada'],
        ['AU', 'Australia'], ['OTHER', 'Other / not listed']
    ];

    var ENTITY_TYPES = ['Sole trader', 'Partnership', 'SRL', 'SpA', 'Ltd', 'GmbH', 'SAS', 'SA', 'Other'];

    var LANGUAGES = [
        ['IT', 'Italian'], ['EN', 'English'], ['DE', 'German'], ['FR', 'French'], ['ES', 'Spanish']
    ];

    var DATA_CATEGORIES = [
        'Name & surname', 'Email address', 'Phone number', 'IP address', 'Location data',
        'Device identifiers', 'Browsing behavior', 'Purchase history', 'Payment card data',
        'Health data', 'Biometric data', 'Genetic data', 'Political opinions', 'Religious beliefs', 'Other'
    ];
    var SPECIAL_CATS = ['Health data', 'Biometric data', 'Genetic data', 'Political opinions', 'Religious beliefs'];

    var LEGAL_BASIS = [
        ['6(1)(a)', '6(1)(a) Consent'],
        ['6(1)(b)', '6(1)(b) Contract'],
        ['6(1)(c)', '6(1)(c) Legal obligation'],
        ['6(1)(d)', '6(1)(d) Vital interests'],
        ['6(1)(e)', '6(1)(e) Public task'],
        ['6(1)(f)', '6(1)(f) Legitimate interests']
    ];
    var ART9_BASIS = [
        ['9(2)(a)', '9(2)(a) Explicit consent'],
        ['9(2)(b)', '9(2)(b) Employment / social security'],
        ['9(2)(c)', '9(2)(c) Vital interests'],
        ['9(2)(f)', '9(2)(f) Legal claims'],
        ['9(2)(j)', '9(2)(j) Scientific research']
    ];
    var RECIPIENTS = [
        'Payment processors', 'Analytics providers', 'Email providers', 'CRM',
        'Cloud hosting', 'Legal authorities', 'No third parties'
    ];

    var WEBSITE_TYPES = [
        ['ecommerce_physical', 'E-commerce (physical goods)'],
        ['ecommerce_digital', 'E-commerce (digital goods / SaaS)'],
        ['blog', 'Blog'],
        ['corporate', 'Corporate'],
        ['marketplace', 'Marketplace'],
        ['membership', 'Membership'],
        ['professional', 'Professional services'],
        ['other', 'Other']
    ];

// Service catalogs (id, label) per section.
    var ANALYTICS = [
        ['ga4', 'Google Analytics 4'], ['matomo', 'Matomo'], ['adobe', 'Adobe Analytics'],
        ['plausible', 'Plausible'], ['fathom', 'Fathom'], ['hotjar', 'Hotjar'], ['clarity', 'Microsoft Clarity'],
        ['other', 'Other']
    ];
    var ADVERTISING = [
        ['google_ads', 'Google Ads'], ['meta_pixel', 'Meta Pixel'], ['linkedin', 'LinkedIn Insight Tag'],
        ['tiktok', 'TikTok Pixel'], ['pinterest', 'Pinterest Tag'], ['twitter', 'Twitter/X Pixel'],
        ['microsoft_ads', 'Microsoft Ads'], ['other', 'Other']
    ];
    var EMBEDS = [
        ['youtube', 'YouTube videos'], ['vimeo', 'Vimeo'], ['google_maps', 'Google Maps'],
        ['facebook_widget', 'Facebook/Instagram widgets'], ['twitter_feed', 'Twitter/X feeds'],
        ['linkedin_widget', 'LinkedIn widgets'], ['spotify', 'Spotify'], ['other', 'Other']
    ];
    var LIVECHAT = [
        ['intercom', 'Intercom'], ['zendesk', 'Zendesk'], ['crisp', 'Crisp'], ['tidio', 'Tidio'],
        ['other', 'Other']
    ];
    var PAYMENTS = [
        ['stripe', 'Stripe'], ['paypal', 'PayPal'], ['braintree', 'Braintree'], ['adyen', 'Adyen'],
        ['nexi', 'Nexi'], ['klarna', 'Klarna'], ['satispay', 'Satispay'], ['apple_pay', 'Apple Pay'],
        ['google_pay', 'Google Pay'], ['other', 'Other']
    ];
    var CRM = [
        ['mailchimp', 'Mailchimp'], ['hubspot', 'HubSpot'], ['salesforce', 'Salesforce'],
        ['activecampaign', 'ActiveCampaign'], ['brevo', 'Brevo'], ['klaviyo', 'Klaviyo'], ['mailerlite', 'MailerLite'],
        ['other', 'Other']
    ];

    /* Per-service detail map: provider, country, category, cookies, purpose,
 * privacy URL, transfer mechanism. country !== EU member => triggers a
 * cross-border transfer entry. selfHosted services never transfer. */
    var SERVICE_DETAILS = {
        ga4: {
            name: 'Google Analytics 4',
            provider: 'Google Ireland Ltd / Google LLC',
            country: 'US',
            category: 'analytics',
            cookies: ['_ga (2 years)', '_ga_* (2 years)', '_gid (24 hours)'],
            purpose: 'Website traffic analysis and user behaviour statistics',
            privacyUrl: 'https://policies.google.com/privacy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        matomo: {
            name: 'Matomo',
            provider: 'InnoCraft Ltd / self-hosted',
            country: 'EU',
            category: 'analytics',
            cookies: ['_pk_id (13 months)', '_pk_ses (30 minutes)'],
            purpose: 'Privacy-friendly website analytics',
            privacyUrl: 'https://matomo.org/privacy-policy/',
            mechanism: 'N/A (EU / self-hosted)',
            selfHosted: true
        },
        adobe: {
            name: 'Adobe Analytics',
            provider: 'Adobe Inc.',
            country: 'US',
            category: 'analytics',
            cookies: ['s_cc', 's_sq', 'AMCV_*'],
            purpose: 'Enterprise web analytics',
            privacyUrl: 'https://www.adobe.com/privacy.html',
            mechanism: 'SCC'
        },
        plausible: {
            name: 'Plausible Analytics',
            provider: 'Plausible Insights OÜ',
            country: 'EU',
            category: 'analytics',
            cookies: ['(cookieless)'],
            purpose: 'Cookieless, privacy-first analytics',
            privacyUrl: 'https://plausible.io/privacy',
            mechanism: 'N/A (EU)',
            selfHosted: true
        },
        fathom: {
            name: 'Fathom Analytics',
            provider: 'Conva Ventures Inc.',
            country: 'CA',
            category: 'analytics',
            cookies: ['(cookieless)'],
            purpose: 'Privacy-focused analytics',
            privacyUrl: 'https://usefathom.com/privacy',
            mechanism: 'Adequacy decision (Canada)'
        },
        hotjar: {
            name: 'Hotjar',
            provider: 'Hotjar Ltd (Malta)',
            country: 'EU',
            category: 'analytics',
            cookies: ['_hjSession* (30 min)', '_hjid (1 year)'],
            purpose: 'Heatmaps and session recordings',
            privacyUrl: 'https://www.hotjar.com/legal/policies/privacy/',
            mechanism: 'N/A (EU)'
        },
        clarity: {
            name: 'Microsoft Clarity',
            provider: 'Microsoft Corporation',
            country: 'US',
            category: 'analytics',
            cookies: ['_clck (1 year)', '_clsk (1 day)', 'MUID (1 year)'],
            purpose: 'Session replay and heatmaps',
            privacyUrl: 'https://privacy.microsoft.com/privacystatement',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },

        google_ads: {
            name: 'Google Ads',
            provider: 'Google Ireland Ltd / Google LLC',
            country: 'US',
            category: 'marketing',
            cookies: ['_gcl_au (90 days)', 'IDE (13 months)'],
            purpose: 'Conversion tracking and remarketing',
            privacyUrl: 'https://policies.google.com/privacy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        meta_pixel: {
            name: 'Meta Pixel',
            provider: 'Meta Platforms Ireland Ltd / Meta Platforms Inc.',
            country: 'US',
            category: 'marketing',
            cookies: ['_fbp (90 days)', 'fr (90 days)'],
            purpose: 'Conversion tracking and remarketing on Facebook/Instagram',
            privacyUrl: 'https://www.facebook.com/privacy/policy/',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        linkedin: {
            name: 'LinkedIn Insight Tag',
            provider: 'LinkedIn Ireland / Microsoft Corporation',
            country: 'US',
            category: 'marketing',
            cookies: ['li_sugr (90 days)', 'bcookie (1 year)'],
            purpose: 'Conversion tracking and remarketing',
            privacyUrl: 'https://www.linkedin.com/legal/privacy-policy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        tiktok: {
            name: 'TikTok Pixel',
            provider: 'TikTok Technology Ltd / TikTok Inc.',
            country: 'US',
            category: 'marketing',
            cookies: ['_ttp (13 months)'],
            purpose: 'Conversion tracking and remarketing',
            privacyUrl: 'https://www.tiktok.com/legal/privacy-policy',
            mechanism: 'SCC'
        },
        pinterest: {
            name: 'Pinterest Tag',
            provider: 'Pinterest Europe Ltd / Pinterest Inc.',
            country: 'US',
            category: 'marketing',
            cookies: ['_pinterest_ct (1 year)'],
            purpose: 'Conversion tracking and remarketing',
            privacyUrl: 'https://policy.pinterest.com/privacy-policy',
            mechanism: 'SCC'
        },
        twitter: {
            name: 'Twitter/X Pixel',
            provider: 'Twitter International / X Corp.',
            country: 'US',
            category: 'marketing',
            cookies: ['personalization_id (2 years)'],
            purpose: 'Conversion tracking and remarketing',
            privacyUrl: 'https://twitter.com/privacy',
            mechanism: 'SCC'
        },
        microsoft_ads: {
            name: 'Microsoft Advertising',
            provider: 'Microsoft Corporation',
            country: 'US',
            category: 'marketing',
            cookies: ['MUID (1 year)', '_uetsid (1 day)', '_uetvid (13 months)'],
            purpose: 'Conversion tracking and remarketing',
            privacyUrl: 'https://privacy.microsoft.com/privacystatement',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },

        youtube: {
            name: 'YouTube (embedded videos)',
            provider: 'Google Ireland Ltd / Google LLC',
            country: 'US',
            category: 'marketing',
            cookies: ['VISITOR_INFO1_LIVE (6 months)', 'YSC (session)'],
            purpose: 'Embedded video playback (use privacy-enhanced mode where possible)',
            privacyUrl: 'https://policies.google.com/privacy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        vimeo: {
            name: 'Vimeo (embedded videos)',
            provider: 'Vimeo Inc.',
            country: 'US',
            category: 'marketing',
            cookies: ['vuid (2 years)'],
            purpose: 'Embedded video playback',
            privacyUrl: 'https://vimeo.com/privacy',
            mechanism: 'SCC'
        },
        google_maps: {
            name: 'Google Maps',
            provider: 'Google Ireland Ltd / Google LLC',
            country: 'US',
            category: 'marketing',
            cookies: ['NID (6 months)'],
            purpose: 'Embedded interactive maps',
            privacyUrl: 'https://policies.google.com/privacy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        facebook_widget: {
            name: 'Facebook/Instagram widgets',
            provider: 'Meta Platforms Ireland Ltd / Meta Platforms Inc.',
            country: 'US',
            category: 'marketing',
            cookies: ['_fbp (90 days)'],
            purpose: 'Embedded social content',
            privacyUrl: 'https://www.facebook.com/privacy/policy/',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        twitter_feed: {
            name: 'Twitter/X feeds',
            provider: 'Twitter International / X Corp.',
            country: 'US',
            category: 'marketing',
            cookies: ['guest_id (2 years)'],
            purpose: 'Embedded social feed',
            privacyUrl: 'https://twitter.com/privacy',
            mechanism: 'SCC'
        },
        linkedin_widget: {
            name: 'LinkedIn widgets',
            provider: 'LinkedIn Ireland / Microsoft Corporation',
            country: 'US',
            category: 'marketing',
            cookies: ['bcookie (1 year)'],
            purpose: 'Embedded social content',
            privacyUrl: 'https://www.linkedin.com/legal/privacy-policy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        spotify: {
            name: 'Spotify (embedded player)',
            provider: 'Spotify AB',
            country: 'EU',
            category: 'marketing',
            cookies: ['sp_t (1 year)'],
            purpose: 'Embedded audio playback',
            privacyUrl: 'https://www.spotify.com/legal/privacy-policy/',
            mechanism: 'N/A (EU)'
        },

        turnstile: {
            name: 'Cloudflare Turnstile',
            provider: 'Cloudflare Inc.',
            country: 'US',
            category: 'functional',
            cookies: ['cf_clearance (1 year)'],
            purpose: 'Bot detection and form spam prevention',
            privacyUrl: 'https://www.cloudflare.com/privacypolicy/',
            mechanism: 'Standard Contractual Clauses (SCC)'
        },
        recaptcha: {
            name: 'Google reCAPTCHA',
            provider: 'Google Ireland Ltd / Google LLC',
            country: 'US',
            category: 'functional',
            cookies: ['_GRECAPTCHA (6 months)'],
            purpose: 'Bot detection and form spam prevention',
            privacyUrl: 'https://policies.google.com/privacy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        google_fonts_cdn: {
            name: 'Google Fonts (CDN)',
            provider: 'Google Ireland Ltd / Google LLC',
            country: 'US',
            category: 'functional',
            cookies: ['(no cookies; logs IP at request time)'],
            purpose: 'Web font delivery via Google CDN',
            privacyUrl: 'https://policies.google.com/privacy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        google_fonts_self: {
            name: 'Google Fonts (self-hosted)',
            provider: 'self-hosted',
            country: 'EU',
            category: 'functional',
            cookies: ['(none)'],
            purpose: 'Self-hosted web fonts (no third-party request)',
            privacyUrl: '',
            mechanism: 'N/A (self-hosted)',
            selfHosted: true
        },

        intercom: {
            name: 'Intercom',
            provider: 'Intercom Inc.',
            country: 'US',
            category: 'functional',
            cookies: ['intercom-* (varies)'],
            purpose: 'Live chat and customer support',
            privacyUrl: 'https://www.intercom.com/legal/privacy',
            mechanism: 'SCC'
        },
        zendesk: {
            name: 'Zendesk',
            provider: 'Zendesk Inc.',
            country: 'US',
            category: 'functional',
            cookies: ['_zendesk_* (varies)'],
            purpose: 'Live chat and customer support',
            privacyUrl: 'https://www.zendesk.com/company/privacy-and-data-protection-policy/',
            mechanism: 'SCC'
        },
        crisp: {
            name: 'Crisp',
            provider: 'Crisp IM SARL',
            country: 'EU',
            category: 'functional',
            cookies: ['crisp-client/* (6 months)'],
            purpose: 'Live chat and customer support',
            privacyUrl: 'https://crisp.chat/en/privacy/',
            mechanism: 'N/A (EU)'
        },
        tidio: {
            name: 'Tidio',
            provider: 'Tidio LLC',
            country: 'US',
            category: 'functional',
            cookies: ['tidio_state_* (varies)'],
            purpose: 'Live chat and customer support',
            privacyUrl: 'https://www.tidio.com/privacy-policy/',
            mechanism: 'SCC'
        },

        stripe: {
            name: 'Stripe',
            provider: 'Stripe Payments Europe Ltd / Stripe Inc.',
            country: 'US',
            category: 'functional',
            cookies: ['__stripe_mid (1 year)', '__stripe_sid (30 minutes)'],
            purpose: 'Payment processing and fraud prevention (PCI DSS)',
            privacyUrl: 'https://stripe.com/privacy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        paypal: {
            name: 'PayPal',
            provider: 'PayPal (Europe) S.à r.l. / PayPal Inc.',
            country: 'US',
            category: 'functional',
            cookies: ['ts (varies)', 'LANG (session)'],
            purpose: 'Payment processing',
            privacyUrl: 'https://www.paypal.com/privacy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        braintree: {
            name: 'Braintree',
            provider: 'Braintree (PayPal)',
            country: 'US',
            category: 'functional',
            cookies: ['(varies)'],
            purpose: 'Payment processing',
            privacyUrl: 'https://www.paypal.com/privacy',
            mechanism: 'SCC'
        },
        adyen: {
            name: 'Adyen',
            provider: 'Adyen N.V.',
            country: 'EU',
            category: 'functional',
            cookies: ['(varies)'],
            purpose: 'Payment processing',
            privacyUrl: 'https://www.adyen.com/policies-and-disclaimer/privacy-policy',
            mechanism: 'N/A (EU)'
        },
        nexi: {
            name: 'Nexi',
            provider: 'Nexi S.p.A.',
            country: 'EU',
            category: 'functional',
            cookies: ['(varies)'],
            purpose: 'Payment processing',
            privacyUrl: 'https://www.nexi.it/privacy',
            mechanism: 'N/A (EU)'
        },
        klarna: {
            name: 'Klarna',
            provider: 'Klarna Bank AB',
            country: 'EU',
            category: 'functional',
            cookies: ['(varies)'],
            purpose: 'Payment processing / buy-now-pay-later',
            privacyUrl: 'https://www.klarna.com/international/privacy-policy/',
            mechanism: 'N/A (EU)'
        },
        satispay: {
            name: 'Satispay',
            provider: 'Satispay S.p.A.',
            country: 'EU',
            category: 'functional',
            cookies: ['(varies)'],
            purpose: 'Payment processing',
            privacyUrl: 'https://www.satispay.com/en-it/privacy/',
            mechanism: 'N/A (EU)'
        },
        apple_pay: {
            name: 'Apple Pay',
            provider: 'Apple Inc.',
            country: 'US',
            category: 'functional',
            cookies: ['(varies)'],
            purpose: 'Payment processing',
            privacyUrl: 'https://www.apple.com/legal/privacy/',
            mechanism: 'SCC'
        },
        google_pay: {
            name: 'Google Pay',
            provider: 'Google Ireland Ltd / Google LLC',
            country: 'US',
            category: 'functional',
            cookies: ['(varies)'],
            purpose: 'Payment processing',
            privacyUrl: 'https://policies.google.com/privacy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },

        mailchimp: {
            name: 'Mailchimp',
            provider: 'The Rocket Science Group LLC',
            country: 'US',
            category: 'functional',
            cookies: ['(email automation)'],
            purpose: 'Email marketing and automation',
            privacyUrl: 'https://www.intuit.com/privacy/statement/',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        hubspot: {
            name: 'HubSpot',
            provider: 'HubSpot Inc.',
            country: 'US',
            category: 'marketing',
            cookies: ['__hs* (varies)', 'hubspotutk (13 months)'],
            purpose: 'CRM, marketing automation and analytics',
            privacyUrl: 'https://legal.hubspot.com/privacy-policy',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        salesforce: {
            name: 'Salesforce',
            provider: 'Salesforce Inc.',
            country: 'US',
            category: 'functional',
            cookies: ['(CRM)'],
            purpose: 'CRM',
            privacyUrl: 'https://www.salesforce.com/company/privacy/',
            mechanism: 'SCC + EU-US Data Privacy Framework'
        },
        activecampaign: {
            name: 'ActiveCampaign',
            provider: 'ActiveCampaign LLC',
            country: 'US',
            category: 'functional',
            cookies: ['(email automation)'],
            purpose: 'Email marketing and automation',
            privacyUrl: 'https://www.activecampaign.com/legal/privacy-policy',
            mechanism: 'SCC'
        },
        brevo: {
            name: 'Brevo',
            provider: 'Sendinblue SAS',
            country: 'EU',
            category: 'functional',
            cookies: ['(email automation)'],
            purpose: 'Email marketing and automation',
            privacyUrl: 'https://www.brevo.com/legal/privacypolicy/',
            mechanism: 'N/A (EU)'
        },
        klaviyo: {
            name: 'Klaviyo',
            provider: 'Klaviyo Inc.',
            country: 'US',
            category: 'marketing',
            cookies: ['__kla_id (2 years)'],
            purpose: 'Email marketing and automation',
            privacyUrl: 'https://www.klaviyo.com/legal/privacy-policy',
            mechanism: 'SCC'
        },
        mailerlite: {
            name: 'MailerLite',
            provider: 'UAB MailerLite',
            country: 'EU',
            category: 'functional',
            cookies: ['(email automation)'],
            purpose: 'Email marketing and automation',
            privacyUrl: 'https://www.mailerlite.com/legal/privacy-policy',
            mechanism: 'N/A (EU)'
        }
    };

    /* --------------------------------------------------------------------- */
    /* Small DOM / utility helpers                                           */

    /* --------------------------------------------------------------------- */
    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'class') {
                    node.className = attrs[k];
                } else if (k === 'html') {
                    node.innerHTML = attrs[k];
                } else if (k === 'text') {
                    node.textContent = attrs[k];
                } else if (k.indexOf('data-') === 0 || k === 'for' || k === 'type' || k === 'value' || k === 'placeholder' || k === 'href' || k === 'target' || k === 'rel' || k === 'id' || k === 'rows' || k === 'min' || k === 'name' || k === 'title') {
                    node.setAttribute(k, attrs[k]);
                } else {
                    node[k] = attrs[k];
                }
            });
        }
        (children || []).forEach(function (c) {
            if (c == null) {
                return;
            }
            node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c);
        });
        return node;
    }

    function esc(s) {
        return (s == null ? '' : String(s));
    }

    function isEU(cc) {
        return !!EU[cc];
    }

    function inArr(arr, v) {
        return Array.isArray(arr) && arr.indexOf(v) !== -1;
    }

    function todayISO() {
        var d = new Date();
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }

    function getPath(obj, path) {
        var parts = path.split('.'), cur = obj;
        for (var i = 0; i < parts.length; i++) {
            if (cur == null) {
                return undefined;
            }
            cur = cur[parts[i]];
        }
        return cur;
    }

    function setPath(obj, path, val) {
        var parts = path.split('.'), cur = obj;
        for (var i = 0; i < parts.length - 1; i++) {
            if (cur[parts[i]] == null || typeof cur[parts[i]] !== 'object') {
                cur[parts[i]] = {};
            }
            cur = cur[parts[i]];
        }
        cur[parts[parts.length - 1]] = val;
    }

    /* --------------------------------------------------------------------- */
    /* Default form state                                                    */

    /* --------------------------------------------------------------------- */
    function defaultState() {
        return {
            step1: {
                companyName: '', entityType: 'SRL', country: 'IT', address: '',
                vatNumber: '', privacyEmail: '', privacyWebsite: '',
                hasDpo: false, dpoName: '', dpoEmail: '', dpoAddress: '',
                hasEuRep: false, euRepDetails: ''
            },
            step2: {
                websiteUrl: '', types: [], audience: 'b2c',
                minorsInvolved: false, minorsMechanism: 'none',
                hasAiFeatures: false, physicalProducts: false, gpsrPerson: ''
            },
            step3: {activities: []},
            step4: {
                analytics: [], ga4Anonymize: true, ga4AdSharing: false, matomoHosting: 'cloud',
                advertising: [], programmatic: false,
                embeds: [],
                turnstile: false, recaptcha: false, googleFonts: false, fontHosting: 'cdn', cdnProvider: '',
                liveChat: [], paymentProcessors: [], crm: [],
                analyticsOther: [], advertisingOther: [], embedsOther: [],
                liveChatOther: [], paymentProcessorsOther: [], crmOther: []
            },
            step5: {
                hasTransfers: false, countries: [], dpfCertified: false, sccVersion: '2021'
            },
            step6: {
                channels: ['email'], rightsEmail: '', formUrl: '',
                responseTime: '1month', extensionPossible: true,
                hasAutomatedDecisions: false, automatedDecisionDescription: '', offersHumanReview: false
            },
            step7: {
                language: 'IT', generatePrivacy: true, generateCookie: true, generateTerms: true,
                tone: 'plain', includeCitations: false, includeWithdrawalForm: true,
                documentVersion: '1.0', effectiveDate: todayISO()
            }
        };
    }

    var state = defaultState();
    var currentStep = 1;
    var view = 'wizard'; // 'wizard' | 'saved'
    var validatedOnce = {}; // step -> bool, whether validation has been shown

    /* --------------------------------------------------------------------- */
    /* Pre-fill from plugin configuration                                    */
    /* --------------------------------------------------------------------- */
    var PREFILLED = {}; // dotted-path -> true, drives the 🔗 badge
    function markPrefilled(path) {
        PREFILLED[path] = true;
    }

    function applyPrefill() {
        if (PREFILL.blog_name) {
            state.step1.companyName = PREFILL.blog_name;
            markPrefilled('step1.companyName');
        }
        if (PREFILL.admin_email) {
            state.step1.privacyEmail = PREFILL.admin_email;
            markPrefilled('step1.privacyEmail');
        }
        if (PREFILL.site_url) {
            state.step2.websiteUrl = PREFILL.site_url;
            markPrefilled('step2.websiteUrl');
        }

        var cookie = PREFILL.cookie || {};
        if (cookie.privacy_url) {
            state.step1.privacyWebsite = cookie.privacy_url;
            markPrefilled('step1.privacyWebsite');
        }
        if (cookie.policy_version) {
            state.step7.documentVersion = String(cookie.policy_version);
            markPrefilled('step7.documentVersion');
        }

// Pre-check functional services already configured in the Script Manager.
        var cats = cookie.script_categories || [];
        if (inArr(cats, 'functional')) {
// leave individual toggles to the user; flag analytics/marketing presence
        }
        if (cookie.analytics_enabled && !inArr(state.step4.analytics, 'ga4')) {
            state.step4.analytics.push('ga4');
            markPrefilled('step4.analytics');
        }
        if (cookie.marketing_enabled && !inArr(state.step4.advertising, 'google_ads')) {
            state.step4.advertising.push('google_ads');
            markPrefilled('step4.advertising');
        }

// E-commerce detection toggles the physical-products / T&C defaults.
        if (PREFILL.has_ecommerce) {
            state.step2.physicalProducts = true;
            markPrefilled('step2.physicalProducts');
            if (inArr(state.step2.types, 'ecommerce_physical') === false) {
                state.step2.types.push('ecommerce_physical');
                markPrefilled('step2.types');
            }
        }
        var rightsEmail = state.step1.privacyEmail;
        if (rightsEmail) {
            state.step6.rightsEmail = rightsEmail;
        }
    }

    function loadDraft() {
        try {
            var raw = window.localStorage.getItem(DRAFT_KEY);
            if (!raw) {
                return false;
            }
            var saved = JSON.parse(raw);
            if (saved && saved.state) {
                state = mergeState(defaultState(), saved.state);
                if (saved.currentStep) {
                    currentStep = Math.max(1, Math.min(TOTAL, parseInt(saved.currentStep, 10) || 1));
                }
                return true;
            }
        } catch (e) {
        }
        return false;
    }

    function mergeState(base, over) {
        Object.keys(over || {}).forEach(function (k) {
            // Guard against prototype pollution from localStorage / saved prompts.
            if (k === "__proto__" || k === "constructor" || k === "prototype") { return; }
            if (over[k] && typeof over[k] === 'object' && !Array.isArray(over[k]) && base[k]) {
                base[k] = mergeState(base[k], over[k]);
            } else {
                base[k] = over[k];
            }
        });
        return base;
    }

    function saveDraft() {
        try {
            window.localStorage.setItem(DRAFT_KEY, JSON.stringify({
                state: state,
                currentStep: currentStep,
                ts: Date.now()
            }));
        } catch (e) {
        }
    }

    function clearDraft() {
        try {
            window.localStorage.removeItem(DRAFT_KEY);
        } catch (e) {
        }
    }

    /* --------------------------------------------------------------------- */
    /* Derived data                                                          */

    /* --------------------------------------------------------------------- */
    function supervisoryAuthority() {
        var cc = state.step1.country;
        return AUTH[cc] || null;
    }

// Flat list of all selected service ids (with resolved Google Fonts variant).
    function selectedServiceIds() {
        var s4 = state.step4;
        var ids = []
            .concat(s4.analytics || [])
            .concat(s4.advertising || [])
            .concat(s4.embeds || [])
            .concat(s4.liveChat || [])
            .concat(s4.paymentProcessors || [])
            .concat(s4.crm || []);
        if (s4.turnstile) {
            ids.push('turnstile');
        }
        if (s4.recaptcha) {
            ids.push('recaptcha');
        }
        if (s4.googleFonts) {
            ids.push(s4.fontHosting === 'self' ? 'google_fonts_self' : 'google_fonts_cdn');
        }
// de-dup and strip the sentinel 'other' value (custom names handled separately)
        return ids.filter(function (v, i) {
            return v !== 'other' && ids.indexOf(v) === i;
        });
    }

    function selectedServices() {
        return selectedServiceIds().map(function (id) {
            var d = SERVICE_DETAILS[id];
            return d ? Object.assign({id: id}, d) : {
                id: id,
                name: id,
                provider: 'Unknown',
                country: 'US',
                category: 'functional',
                cookies: [],
                purpose: '',
                privacyUrl: '',
                mechanism: 'SCC'
            };
        });
    }

// Derive cross-border transfers from selected services + manual step5 input.
    function derivedTransfers() {
        var map = {}; // country -> { country, providers:[], mechanisms:[] }
        selectedServices().forEach(function (s) {
            if (s.selfHosted || s.country === 'EU') {
                return;
            }
            var key = s.country;
            if (!map[key]) {
                map[key] = {country: key, providers: [], mechanisms: []};
            }
            if (map[key].providers.indexOf(s.provider) === -1) {
                map[key].providers.push(s.provider);
            }
            if (s.mechanism && map[key].mechanisms.indexOf(s.mechanism) === -1) {
                map[key].mechanisms.push(s.mechanism);
            }
        });
// Manual countries from step5
        (state.step5.countries || []).forEach(function (c) {
            if (!map[c]) {
                map[c] = {country: c, providers: [], mechanisms: []};
            }
        });
        return Object.keys(map).map(function (k) {
            return map[k];
        });
    }

    function hasUSTransfer() {
        return derivedTransfers().some(function (t) {
            return t.country === 'US' || t.country === 'USA';
        });
    }

    function isEcommerce() {
        var t = state.step2.types || [];
        return inArr(t, 'ecommerce_physical') || inArr(t, 'ecommerce_digital') || PREFILL.has_ecommerce;
    }

    var COUNTRY_NAMES = {};
    COUNTRIES.forEach(function (c) {
        COUNTRY_NAMES[c[0]] = c[1];
    });
    COUNTRY_NAMES.US = 'United States';
    COUNTRY_NAMES.USA = 'United States';

    function countryName(cc) {
        return COUNTRY_NAMES[cc] || cc;
    }

    var LANG_NAMES = {IT: 'Italian', EN: 'English', DE: 'German', FR: 'French', ES: 'Spanish'};

    /* --------------------------------------------------------------------- */
    /* AI legal disclaimer (mandatory in every output)                       */
    /* --------------------------------------------------------------------- */
    var AI_DISCLAIMER = '\u26A0\uFE0F AI Legal Notice: This document was generated with the assistance of an artificial intelligence system. While it is based on the regulatory framework in force as of the date of generation, it may contain errors, omissions, or outdated provisions. It does NOT constitute legal advice. Before publication, this document must be reviewed and validated by a qualified legal professional admitted to practice in your jurisdiction. The developers of wp-opengdpr accept no liability for the content of AI-generated documents.';

    /* --------------------------------------------------------------------- */
    /* Prompt builder (spec section 4)                                       */

    /* --------------------------------------------------------------------- */
    function buildLegalFramework() {
        var s1 = state.step1, s2 = state.step2;
        var lines = [];
        var country = s1.country;
        var ecom = isEcommerce();
        lines.push('GDPR — Regulation (EU) 2016/679 (Arts. 13-14 disclosures, Arts. 15-22 data subject rights).');
        lines.push('ePrivacy Directive 2002/58/EC and EDPB Guidelines 2/2023 (all tracking technologies: cookies, pixels, fingerprinting, URL tracking, localStorage).');
        if (country === 'IT') {
            lines.push('Italian D.Lgs. 196/2003 as amended by D.Lgs. 101/2018 (national implementation of GDPR).');
        }
        if (country === 'DE') {
            lines.push('German BDSG, including \u00A738 BDSG (DPO mandatory at 20+ employees engaged in automated processing of personal data).');
        }
        if (ecom) {
            lines.push('Directive (EU) 2019/2161 (Omnibus) / D.Lgs. 26/2023 (price-reduction transparency: 30-day lowest-price rule; prohibition of fake reviews).');
            if (country === 'IT') {
                lines.push('D.Lgs. 70/2003 (E-Commerce Directive: pre-contractual information and order-confirmation obligations).');
            }
            lines.push('Regulation GPSR (EU) 2023/988 — in force since 13/12/2024 (a responsible person established in the EU must be named).');
            if (country === 'IT') {
                lines.push('D.Lgs. 209/2025 — Art. 54-bis Codice del Consumo, in force since 08/01/2026: the trader MUST provide an online withdrawal function (funzione di recesso digitale) and describe it in the Terms & Conditions.');
            }
            lines.push('Directive (EU) 2024/825 (Empowering Consumers): environmental-claims and product-sustainability information where applicable.');
            lines.push('Consumer Code Arts. 52-59: 14-day right of withdrawal, procedure, cost allocation and the Art. 59 exceptions list.');
            lines.push('D.Lgs. 170/2021: 2-year legal guarantee of conformity for physical goods.');
            lines.push('PSD2 / D.Lgs. 11/2010: payment-method disclosures and strong customer authentication.');
            lines.push('Accessibility Act (EAA) / D.Lgs. 82/2024 (enforceable since June 2025): acknowledge WCAG 2.1 AA compliance where applicable.');
        }
        if (s2.hasAiFeatures) {
            lines.push('EU AI Act — Regulation (EU) 2024/1689, Art. 50: transparency obligations for AI-generated content, AI recommendations and chatbots.');
        }
        lines.push('CRITICAL EXCLUSION: The EU ODR platform was abolished on 20/03/2025. Do NOT include any reference, link or mention of the ODR platform in any document. Any alternative dispute resolution mentioned must be private/sector-specific.');
        return lines.map(function (l) {
            return '- ' + l;
        }).join('\n');
    }

    function buildDataTable() {
        var acts = state.step3.activities || [];
        if (!acts.length) {
            return '(No processing activities were specified — instruct the AI to request them.)';
        }
        var rows = acts.map(function (a, i) {
            var cats = (a.dataCategories || []).join(', ') || '(unspecified)';
            var basis = a.legalBasis || '(unspecified)';
            var li = (a.legalBasis === '6(1)(f)' && a.legitimateInterest) ? (' [legitimate interest: ' + a.legitimateInterest + ']') : '';
            var art9 = a.isSpecial ? (' | Art. 9 basis: ' + (a.art9Basis || '(unspecified)')) : '';
            var rec = (a.recipients || []).join(', ') || 'None';
            return (i + 1) + '. Data: ' + cats + '\n   Purpose: ' + (a.purpose || '(unspecified)') +
                '\n   Legal basis: ' + basis + li + art9 +
                '\n   Retention: ' + (a.retention || '(unspecified)') +
                '\n   Recipients: ' + rec;
        });
        return rows.join('\n');
    }

    function buildCookieTable() {
        var svcs = selectedServices();
        var s4 = state.step4;

        // Gather custom "other" service names per group with their implied category
        var customGroups = [
            {names: inArr(s4.analytics, 'other') ? (s4.analyticsOther || []).filter(Boolean) : [], cat: 'analytics'},
            {names: inArr(s4.advertising, 'other') ? (s4.advertisingOther || []).filter(Boolean) : [], cat: 'marketing'},
            {names: inArr(s4.embeds, 'other') ? (s4.embedsOther || []).filter(Boolean) : [], cat: 'marketing'},
            {names: inArr(s4.liveChat, 'other') ? (s4.liveChatOther || []).filter(Boolean) : [], cat: 'functional'},
            {names: inArr(s4.paymentProcessors, 'other') ? (s4.paymentProcessorsOther || []).filter(Boolean) : [], cat: 'functional'},
            {names: inArr(s4.crm, 'other') ? (s4.crmOther || []).filter(Boolean) : [], cat: 'functional'}
        ];
        var hasCustom = customGroups.some(function (g) { return g.names.length > 0; });

        if (!svcs.length && !hasCustom) {
            return '(No third-party services selected — only strictly-necessary first-party cookies apply.)';
        }
        var byCat = {functional: [], analytics: [], marketing: []};
        svcs.forEach(function (s) {
            (byCat[s.category] || byCat.functional).push(s);
        });
        // Inject custom services into the appropriate category bucket
        customGroups.forEach(function (g) {
            g.names.forEach(function (name) {
                (byCat[g.cat] || byCat.functional).push({
                    name: name,
                    provider: '(provider to be specified)',
                    cookies: ['(cookies/tech to be specified)'],
                    purpose: '(purpose to be specified)',
                    privacyUrl: '(privacy policy URL to be specified)'
                });
            });
        });
        var out = [];
        ['functional', 'analytics', 'marketing'].forEach(function (cat) {
            if (!byCat[cat].length) {
                return;
            }
            out.push('### ' + cat.charAt(0).toUpperCase() + cat.slice(1) + ' (requires consent)');
            byCat[cat].forEach(function (s) {
                out.push('- ' + s.name + ' | Provider: ' + s.provider + ' | Cookies/tech: ' + (s.cookies.join(', ') || 'n/a') +
                    ' | Purpose: ' + s.purpose + ' | Party: third-party | Privacy policy: ' + (s.privacyUrl || 'n/a'));
            });
        });
        out.unshift('### Strictly Necessary (no consent required)\n- Session cookies, CSRF protection token, cookie-consent record.');
        return out.join('\n');
    }

    function buildTransferTable() {
        var transfers = derivedTransfers();
        if (!transfers.length) {
            return 'No personal data is transferred outside the EU/EEA. All processors are established in the EU/EEA or are self-hosted.';
        }
        var s5 = state.step5;
        var out = transfers.map(function (t) {
            var mech = t.mechanisms.length ? t.mechanisms.join('; ') : 'Standard Contractual Clauses (EU Commission Decision 2021/914)';
            var providers = t.providers.length ? t.providers.join(', ') : '(provider list to be completed)';
            var dpf = ((t.country === 'US' || t.country === 'USA') && s5.dpfCertified) ? ' The recipient(s) are certified under the EU-US Data Privacy Framework (adequacy decision of 10/07/2023).' : '';
            return '- ' + countryName(t.country) + ' | Recipients: ' + providers + ' | Safeguard: ' + mech + '.' + dpf;
        });
        out.unshift('Transfer safeguards rely on the 2021 Standard Contractual Clauses (EU Commission Decision 2021/914). Pre-2021 SCCs are invalid and must not be referenced.');
        return out.join('\n');
    }

    function buildPrivacyRights() {
        var s6 = state.step6;
        var channels = (s6.channels || []).map(function (c) {
            return {
                email: 'dedicated email (' + (s6.rightsEmail || state.step1.privacyEmail || 'to be provided') + ')',
                form: 'online request form (' + (s6.formUrl || 'URL to be provided') + ')',
                postal: 'postal mail to the registered address',
                phone: 'telephone'
            }[c] || c;
        }).join(', ');
        var rt = {
            '1month': 'within one month (extendable by two further months for complex requests, GDPR Art. 12(3))',
            '15days': 'within 15 days',
            '72hours': 'within 72 hours'
        }[s6.responseTime] || 'within one month';
        var lines = [];
        lines.push('Rights to be described in full: access (Art. 15), rectification (Art. 16), erasure (Art. 17), restriction (Art. 18), data portability (Art. 20), objection (Art. 21), and the right to withdraw consent at any time (Art. 7(3)).');
        lines.push('Request channels: ' + (channels || 'dedicated email') + '. Response time: ' + rt + '.');
        if (s6.hasAutomatedDecisions) {
            lines.push('Automated decision-making / profiling (Art. 22) IS used: ' + (s6.automatedDecisionDescription || '(describe the logic, significance and envisaged consequences)') + (s6.offersHumanReview ? ' The data subject is offered the right to obtain human intervention, to express their point of view and to contest the decision.' : ' Note: where such processing produces legal or similarly significant effects, the right to human intervention must be offered.'));
        } else {
            lines.push('No automated decision-making producing legal or similarly significant effects is carried out.');
        }
        var auth = supervisoryAuthority();
        if (auth) {
            lines.push('Right to lodge a complaint with the competent supervisory authority: ' + auth.name + ' (' + (auth.url || '') + ', ' + (auth.address || '') + ').');
        } else {
            lines.push('Right to lodge a complaint with the competent national supervisory authority.');
        }
        return lines.map(function (l) {
            return '- ' + l;
        }).join('\n');
    }

    function buildTermsBlock() {
        var s1 = state.step1, s2 = state.step2;
        var lines = [];
        lines.push('Full seller identity: ' + (s1.companyName || '(company name)') + ', ' + (s1.entityType || '') + ', registered address: ' + (s1.address || '(address)') + ', VAT/Tax ID: ' + (s1.vatNumber || '(VAT)') + ', contact: ' + (s1.privacyEmail || '(email)') + '.');
        lines.push('Description of the goods/services offered and the full ordering and order-confirmation procedure (D.Lgs. 70/2003 Arts. 12-13).');
        lines.push('All prices VAT-included, with every accessory cost disclosed clearly BEFORE checkout.');
        lines.push('Accepted payment methods and PSD2 strong-customer-authentication obligations.');
        lines.push('Delivery times and methods.');
        lines.push('Right of withdrawal: 14 days, full procedure and cost allocation (Consumer Code Arts. 52-59).');
        if (s1.country === 'IT') {
            lines.push('NEW (2026): describe the mandatory online withdrawal function (funzione di recesso digitale) under Art. 54-bis Codice del Consumo (D.Lgs. 209/2025, in force 08/01/2026).');
        }
        lines.push('Exceptions to the right of withdrawal (Art. 59 Codice del Consumo list).');
        lines.push('Legal guarantee of conformity: 2 years for physical goods (D.Lgs. 170/2021).');
        if (s2.physicalProducts) {
            lines.push('GPSR (Reg. (EU) 2023/988): name the responsible person established in the EU' + (s2.gpsrPerson ? (': ' + s2.gpsrPerson) : ' (to be provided)') + '.');
        }
        lines.push('Do NOT reference the ODR platform (abolished 20/03/2025). Any ADR mentioned must be private/sector-specific.');
        lines.push('Governing law and competent jurisdiction: for B2C, the mandatory consumer-residence rules apply; for B2B, the chosen forum may be stated.');
        lines.push('Omnibus 30-day lowest-price rule: describe it if promotions/price reductions are run.');
        lines.push('Intellectual property and prohibited uses; liability limitations within the bounds permitted for B2C; force majeure; amendments clause (how users are notified).');
        if (state.step7.includeWithdrawalForm) {
            lines.push('Include the model withdrawal form (Consumer Code Annex I, part B).');
        }
        return lines.map(function (l) {
            return '- ' + l;
        }).join('\n');
    }

    function buildPrompt() {
        var s1 = state.step1, s2 = state.step2, s7 = state.step7;
        var langName = LANG_NAMES[s7.language] || 'Italian';
        var docs = [];
        if (s7.generatePrivacy) {
            docs.push('Privacy Policy');
        }
        if (s7.generateCookie) {
            docs.push('Cookie Policy');
        }
        if (s7.generateTerms && isEcommerce()) {
            docs.push('Terms & Conditions');
        }
        var toneText = {
            formal: 'formal and technical',
            plain: 'clear, plain language understandable by an average consumer (recommended by GDPR Art. 12)',
            both: 'two versions — one formal/technical and one in clear plain language'
        }[s7.tone] || 'clear plain language';

        var P = [];
        P.push('You are an expert EU data-protection and consumer-law legal drafter. Draft the following legal document(s) for the website described below, fully compliant with the regulatory framework listed. Write the output in ' + langName + '.');
        P.push('');
        if (s2.websiteUrl) {
            P.push('=== PRELIMINARY STEP — WEBSITE ANALYSIS (mandatory before drafting) ===');
            P.push('Before writing any document, use your web browsing capability to visit and navigate the website at: ' + s2.websiteUrl);
            P.push('While browsing, examine: the homepage, any product/service pages, checkout or sign-up flows, existing privacy/cookie/terms pages, footer links, cookie banners, and any embedded third-party widgets or scripts you can detect. Use what you observe to:');
            P.push('1. Verify and supplement the structured data provided below (fill placeholders where you can confirm the real value from the live site).');
            P.push('2. Identify any additional third-party services, cookies or data-collection practices not explicitly listed.');
            P.push('3. Ensure the documents accurately reflect the real purpose, tone and scope of the website.');
            P.push('If the site is unavailable or browsing is not possible in your environment, proceed with the structured data provided and flag any unverified details with a "(unverified — please check)" note.');
            P.push('');
        }
        P.push('DOCUMENTS TO PRODUCE: ' + (docs.join(', ') || 'Privacy Policy') + '.');
        P.push('TONE: ' + toneText + '.');
        P.push('CITATIONS: ' + (s7.includeCitations ? 'include the relevant article citation in parentheses after each section heading.' : 'do NOT clutter the body with article numbers; keep it readable.'));
        P.push('VERSION: ' + (s7.documentVersion || '1.0') + ' — EFFECTIVE DATE: ' + (s7.effectiveDate || todayISO()) + ' (place in the document header/footer).');
        P.push('');
        P.push('=== MANDATORY AI LEGAL NOTICE (reproduce verbatim at the top of every generated document) ===');
        P.push(AI_DISCLAIMER);
        P.push('');
        P.push('=== APPLICABLE LEGAL FRAMEWORK ===');
        P.push(buildLegalFramework());
        P.push('');
        P.push('=== DATA CONTROLLER ===');
        P.push('- Name: ' + (s1.companyName || '(to be provided)') + ' (' + (s1.entityType || 'entity type n/a') + '), country of incorporation: ' + countryName(s1.country) + '.');
        P.push('- Registered address: ' + (s1.address || '(to be provided)') + '.');
        P.push('- VAT/Tax ID: ' + (s1.vatNumber || '(to be provided)') + '.');
        P.push('- Privacy contact: ' + (s1.privacyEmail || '(to be provided)') + (s1.privacyWebsite ? (' / ' + s1.privacyWebsite) : '') + '.');
        if (s1.hasDpo) {
            P.push('- Data Protection Officer: ' + (s1.dpoName || '(name)') + ', ' + (s1.dpoEmail || '(email)') + (s1.dpoAddress ? (', ' + s1.dpoAddress) : '') + ' (GDPR Art. 37-39).');
        } else {
            P.push('- No DPO appointed (confirm the Art. 37 criteria are not met).');
        }
        if (s1.hasEuRep && s1.euRepDetails) {
            P.push('- EU Representative (Art. 27): ' + s1.euRepDetails + '.');
        }
        P.push('');
        P.push('=== WEBSITE SCOPE ===');
        P.push('- URL: ' + (s2.websiteUrl || '(to be provided)') + '.');
        P.push('- Nature: ' + ((s2.types || []).map(function (t) {
            var f = WEBSITE_TYPES.filter(function (w) {
                return w[0] === t;
            })[0];
            return f ? f[1] : t;
        }).join(', ') || '(unspecified)') + '.');
        P.push('- Audience: ' + ({
            b2c: 'B2C only',
            b2b: 'B2B only',
            both: 'both B2C and B2B'
        }[s2.audience] || s2.audience) + '.');
        if (s2.minorsInvolved) {
            P.push('- The site targets or may collect data from minors under 18: include a GDPR Art. 8 section (parental consent, age verification: ' + ({
                none: 'none in place',
                agegate: 'age gate',
                parental: 'parental consent'
            }[s2.minorsMechanism] || s2.minorsMechanism) + ') and a COPPA caution for any US minors.');
        }
        if (s2.hasAiFeatures) {
            P.push('- The site uses AI-generated content / recommendations / chatbots: include an EU AI Act Art. 50 transparency disclosure in the documents.');
        }
        P.push('');
        if (s7.generatePrivacy) {
            P.push('=== PRIVACY POLICY — required content (GDPR Arts. 13-14) ===');
            P.push('Cover ALL of: controller identity & contacts; DPO contacts (if any); specific purposes (one per data category); lawful basis per purpose (Art. 6, plus Art. 9 for special categories); specific legitimate interests where relied upon; categories of recipients; international transfers; retention periods/criteria; all data-subject rights; right to complain to the supervisory authority; whether data provision is a statutory/contractual requirement and the consequences; existence and logic of any automated decision-making/profiling.');
            P.push('');
            P.push('Processing activities (data -> purpose -> basis -> retention -> recipients):');
            P.push(buildDataTable());
            P.push('');
            P.push('Data-subject rights handling:');
            P.push(buildPrivacyRights());
            P.push('');
        }
        if (s7.generateCookie) {
            P.push('=== COOKIE POLICY — required content (ePrivacy + EDPB 2/2023) ===');
            P.push('Define cookies AND similar technologies (pixels, fingerprinting, URL tracking, localStorage). Use the four categories. List every third-party service individually with name, provider, purpose, duration, first/third party, and a link to its own privacy policy. Describe the consent mechanism (accept, reject, customise, revoke), the persistent preference-management access point, and the consent-record retention (≈6 months per EDPB).');
            P.push('');
            P.push('Technologies in use:');
            P.push(buildCookieTable());
            P.push('');
        }
        P.push('=== INTERNATIONAL DATA TRANSFERS (GDPR Chapter V) ===');
        P.push(buildTransferTable());
        P.push('');
        if (s7.generateTerms && isEcommerce()) {
            P.push('=== TERMS & CONDITIONS — required content (EU/Italian e-commerce B2C) ===');
            P.push(buildTermsBlock());
            P.push('');
        }
        P.push('=== OUTPUT REQUIREMENTS ===');
        P.push('- Produce each document as a SEPARATE FILE. Preferred format order: (1) .docx, (2) .md if .docx is not available in your environment, (3) .pdf if neither .docx nor .md is available, (4) plain text as a last resort. Do not merge documents into a single file.');
        P.push('- Name each file clearly after the document type (e.g. privacy-policy.docx, cookie-policy.docx, terms-and-conditions.docx).');
        P.push('- Do not invent facts: where a value is marked "(to be provided)", insert a clearly bracketed placeholder for the user to complete.');
        P.push('- Never reference the abolished ODR platform. Never reference pre-2021 SCCs. Never reference fax numbers.');
        P.push('- Reproduce the AI Legal Notice verbatim at the top of every document.');
        return P.join('\n');
    }

    /* --------------------------------------------------------------------- */
    /* Reusable field builders                                               */
    /* --------------------------------------------------------------------- */
    var rerenderStep = function () {
    }; // assigned later

    function badges(path, opts) {
        opts = opts || {};
        var kids = [];
        if (PREFILLED[path]) {
            kids.push(el('span', {
                'class': 'wpog-tag-prefill',
                title: __('Pre-filled from your plugin configuration.', TXT)
            }, ['\uD83D\uDD17']));
        }
        if (opts.legal) {
            kids.push(el('span', {'class': 'wpog-tag-legal', title: opts.legal}, ['\u2139\uFE0F']));
        }
        return kids;
    }

    function labelRow(text, path, opts) {
        opts = opts || {};
        var cls = 'wpog-field__label' + (opts.required ? ' req' : '');
        return el('label', {'class': cls, 'for': opts.id || ''}, [text].concat(badges(path, opts)));
    }

    function fieldError(path) {
        return el('div', {'class': 'wpog-field__error', 'data-error-for': path});
    }

    function textField(path, label, opts) {
        opts = opts || {};
        var id = 'f_' + path.replace(/\./g, '_');
        var val = getPath(state, path);
        var input = el(opts.textarea ? 'textarea' : 'input', {
            id: id, 'class': 'wpog-input', placeholder: opts.placeholder || '',
            type: opts.type || 'text', rows: opts.rows || '3'
        });
        if (!opts.textarea) {
            input.value = val == null ? '' : val;
        } else {
            input.value = val == null ? '' : val;
        }
        input.addEventListener('input', function () {
            setPath(state, path, input.value);
            saveDraft();
            if (opts.onChange) {
                opts.onChange();
            }
        });
        var wrap = el('div', {'class': 'wpog-field'}, [
            labelRow(label, path, {required: opts.required, legal: opts.legal, id: id}),
            input
        ]);
        if (opts.hint) {
            wrap.appendChild(el('div', {'class': 'wpog-field__hint'}, [opts.hint]));
        }
        wrap.appendChild(fieldError(path));
        return wrap;
    }

    function selectField(path, label, options, opts) {
        opts = opts || {};
        var id = 'f_' + path.replace(/\./g, '_');
        var val = getPath(state, path);
        var sel = el('select', {id: id, 'class': 'wpog-input'});
        options.forEach(function (o) {
            var ov = Array.isArray(o) ? o[0] : o;
            var ol = Array.isArray(o) ? o[1] : o;
            var optn = el('option', {value: ov}, [ol]);
            if (String(val) === String(ov)) {
                optn.selected = true;
            }
            sel.appendChild(optn);
        });
        sel.addEventListener('change', function () {
            setPath(state, path, sel.value);
            saveDraft();
            if (opts.onChange) {
                opts.onChange();
            }
            if (opts.rerender) {
                rerenderStep();
            }
        });
        var wrap = el('div', {'class': 'wpog-field'}, [
            labelRow(label, path, {required: opts.required, legal: opts.legal, id: id}), sel
        ]);
        if (opts.hint) {
            wrap.appendChild(el('div', {'class': 'wpog-field__hint'}, [opts.hint]));
        }
        wrap.appendChild(fieldError(path));
        return wrap;
    }

    function toggleField(path, label, opts) {
        opts = opts || {};
        var val = !!getPath(state, path);
        var knob = el('span', {'class': 'wpog-switch'});
        var sw = el('span', {'class': 'wpog-toggle' + (val ? ' is-on' : '')}, [knob]);
        var text = el('span', {'class': 'wpog-toggle__text'}, [label]);
        var wrap = el('div', {'class': 'wpog-field'}, [
            el('label', {'class': 'wpog-toggle-row'}, [sw, text].concat(badges(path, opts)))
        ]);
        sw.addEventListener('click', function () {
            val = !val;
            setPath(state, path, val);
            sw.className = 'wpog-toggle' + (val ? ' is-on' : '');
            saveDraft();
            if (opts.onChange) {
                opts.onChange();
            }
            if (opts.rerender) {
                rerenderStep();
            }
        });
        if (opts.hint) {
            wrap.appendChild(el('div', {'class': 'wpog-field__hint'}, [opts.hint]));
        }
        return wrap;
    }

    function radioField(path, label, options, opts) {
        opts = opts || {};
        var val = getPath(state, path);
        var group = el('div', {'class': 'wpog-radios'});
        options.forEach(function (o) {
            var ov = Array.isArray(o) ? o[0] : o;
            var ol = Array.isArray(o) ? o[1] : o;
            var lab = el('label', {'class': 'wpog-radio' + (String(val) === String(ov) ? ' is-checked' : '')}, [ol]);
            lab.addEventListener('click', function () {
                setPath(state, path, ov);
                saveDraft();
                Array.prototype.forEach.call(group.children, function (c) {
                    c.className = 'wpog-radio';
                });
                lab.className = 'wpog-radio is-checked';
                if (opts.onChange) {
                    opts.onChange();
                }
                if (opts.rerender) {
                    rerenderStep();
                }
            });
            group.appendChild(lab);
        });
        var wrap = el('div', {'class': 'wpog-field'}, [labelRow(label, path, opts), group]);
        if (opts.hint) {
            wrap.appendChild(el('div', {'class': 'wpog-field__hint'}, [opts.hint]));
        }
        return wrap;
    }

    function chipsField(path, label, options, opts) {
        opts = opts || {};
        var arr = getPath(state, path) || [];
        var box = el('div', {'class': 'wpog-chips'});
        options.forEach(function (o) {
            var ov = Array.isArray(o) ? o[0] : o;
            var ol = Array.isArray(o) ? o[1] : o;
            var chip = el('span', {'class': 'wpog-chip' + (inArr(arr, ov) ? ' is-checked' : '')}, [ol]);
            chip.addEventListener('click', function () {
                arr = getPath(state, path) || [];
                if (inArr(arr, ov)) {
                    arr = arr.filter(function (x) {
                        return x !== ov;
                    });
                } else {
                    arr = arr.concat([ov]);
                }
                setPath(state, path, arr);
                saveDraft();
                chip.className = 'wpog-chip' + (inArr(arr, ov) ? ' is-checked' : '');
                if (opts.onChange) {
                    opts.onChange();
                }
                if (opts.rerender) {
                    rerenderStep();
                }
            });
            box.appendChild(chip);
        });
        var wrap = el('div', {'class': 'wpog-field'}, [labelRow(label, path, opts), box]);
        if (opts.hint) {
            wrap.appendChild(el('div', {'class': 'wpog-field__hint'}, [opts.hint]));
        }
        wrap.appendChild(fieldError(path));
        return wrap;
    }

    function otherServicesPanel(statePath, labelText) {
        var vals = getPath(state, statePath) || [];
        if (!vals.length) {
            vals = [''];
            setPath(state, statePath, vals);
        }
        var wrap = el('div', {'class': 'wpog-other-panel'});
        wrap.appendChild(el('label', {'class': 'wpog-field__label wpog-other-panel__label'}, [labelText]));
        var list = el('div', {'class': 'wpog-other-list'});

        function renderRows() {
            list.innerHTML = '';
            var current = getPath(state, statePath) || [''];
            current.forEach(function (v, i) {
                var inp = el('input', {type: 'text', 'class': 'wpog-input', placeholder: __('Service name', TXT)});
                inp.value = v || '';
                inp.addEventListener('input', function () {
                    var a = getPath(state, statePath) || [];
                    a[i] = inp.value;
                    setPath(state, statePath, a);
                    saveDraft();
                });
                var row = el('div', {'class': 'wpog-other-row'}, [inp]);
                if (current.length > 1) {
                    var rem = el('button', {type: 'button', 'class': 'button-link wpog-row-remove'}, ['×']);
                    rem.addEventListener('click', function () {
                        var a = getPath(state, statePath) || [];
                        a.splice(i, 1);
                        setPath(state, statePath, a.length ? a : ['']);
                        saveDraft();
                        renderRows();
                    });
                    row.appendChild(rem);
                }
                list.appendChild(row);
            });
        }

        renderRows();
        var addBtn = el('button', {type: 'button', 'class': 'button-secondary wpog-other-add'}, ['+ ' + __('Add another', TXT)]);
        addBtn.addEventListener('click', function () {
            var a = getPath(state, statePath) || [];
            a.push('');
            setPath(state, statePath, a);
            saveDraft();
            renderRows();
        });
        wrap.appendChild(list);
        wrap.appendChild(addBtn);
        return wrap;
    }

    function conditional(open, builder) {
        var inner = el('div', {'class': 'wpog-cond__inner'});
        (builder() || []).forEach(function (c) {
            if (c) {
                inner.appendChild(c);
            }
        });
        return el('div', {'class': 'wpog-cond' + (open ? ' is-open' : '')}, [inner]);
    }

    function notice(kind, htmlText) {
        return el('div', {'class': 'wpog-inline-notice is-' + kind, html: htmlText});
    }

    /* --------------------------------------------------------------------- */
    /* Step renderers                                                        */
    /* --------------------------------------------------------------------- */
    var STEP_META = [
        {n: 1, icon: '\uD83C\uDFE2', label: __('Business', TXT)},
        {n: 2, icon: '\uD83C\uDF10', label: __('Scope', TXT)},
        {n: 3, icon: '\uD83D\uDDC2\uFE0F', label: __('Data', TXT)},
        {n: 4, icon: '\uD83E\uDDE9', label: __('Services', TXT)},
        {n: 5, icon: '\u2708\uFE0F', label: __('Transfers', TXT)},
        {n: 6, icon: '\u2696\uFE0F', label: __('Rights', TXT)},
        {n: 7, icon: '\uD83D\uDDA8\uFE0F', label: __('Output', TXT)},
        {n: 8, icon: '\u2705', label: __('Review', TXT)}
    ];

    function card(title, sub, kids) {
        var c = el('div', {'class': 'wpog-card'}, [
            el('h2', {'class': 'wpog-card__title'}, [title])
        ]);
        if (sub) {
            c.appendChild(el('p', {'class': 'wpog-card__sub'}, [sub]));
        }
        (kids || []).forEach(function (k) {
            if (k) {
                c.appendChild(k);
            }
        });
        return c;
    }

    function renderStep1() {
        var nonEu = !isEU(state.step1.country);
        return card(
            __('Business Identity', TXT),
            __('The data controller identity, as required by GDPR Art. 13(1)(a)-(b).', TXT),
            [
                textField('step1.companyName', __('Company / Organisation name', TXT), {
                    required: true,
                    legal: 'GDPR Art. 13(1)(a)'
                }),
                selectField('step1.entityType', __('Legal entity type', TXT), ENTITY_TYPES, {required: true}),
                selectField('step1.country', __('Country of incorporation', TXT), COUNTRIES, {
                    required: true,
                    rerender: true,
                    hint: __('Determines the applicable national law and supervisory authority.', TXT)
                }),
                textField('step1.address', __('Registered address (full)', TXT), {
                    required: true,
                    textarea: true,
                    legal: 'GDPR Art. 13(1)(a)'
                }),
                textField('step1.vatNumber', __('VAT / Tax ID number', TXT), {
                    legal: 'D.Lgs. 70/2003',
                    hint: __('Required for e-commerce.', TXT)
                }),
                textField('step1.privacyEmail', __('Privacy contact email', TXT), {
                    required: true,
                    type: 'email',
                    legal: 'GDPR Art. 13(1)(a)'
                }),
                textField('step1.privacyWebsite', __('Website for privacy inquiries', TXT), {type: 'url'}),
                toggleField('step1.hasDpo', __('A Data Protection Officer (DPO) is appointed', TXT), {
                    legal: 'GDPR Art. 37',
                    rerender: true
                }),
                conditional(state.step1.hasDpo, function () {
                    return [
                        textField('step1.dpoName', __('DPO name', TXT), {required: true, legal: 'GDPR Art. 13(1)(b)'}),
                        textField('step1.dpoEmail', __('DPO email', TXT), {
                            required: true,
                            type: 'email',
                            legal: 'GDPR Art. 13(1)(b)'
                        }),
                        textField('step1.dpoAddress', __('DPO postal address', TXT), {legal: 'GDPR Art. 13(1)(b)'})
                    ];
                }),
                nonEu ? toggleField('step1.hasEuRep', __('An EU Representative is appointed (non-EU controller)', TXT), {
                    legal: 'GDPR Art. 27',
                    rerender: true
                }) : null,
                nonEu ? conditional(state.step1.hasEuRep, function () {
                    return [textField('step1.euRepDetails', __('EU Representative name & address', TXT), {
                        required: true,
                        textarea: true,
                        legal: 'GDPR Art. 27'
                    })];
                }) : null
            ]
        );
    }

    function renderStep2() {
        var minors = state.step2.minorsInvolved;
        var phys = state.step2.physicalProducts;
        var auth = supervisoryAuthority();
        return card(
            __('Website Scope', TXT),
            __('Defines the processing context and which legal framework applies.', TXT),
            [
                textField('step2.websiteUrl', __('Website URL', TXT), {required: true, type: 'url'}),
                chipsField('step2.types', __('Nature of the website', TXT), WEBSITE_TYPES, {
                    required: true,
                    rerender: true,
                    hint: __('Selecting an e-commerce type enables Terms & Conditions generation.', TXT)
                }),
                radioField('step2.audience', __('Primary target audience', TXT), [['b2c', 'B2C only'], ['b2b', 'B2B only'], ['both', 'Both B2C and B2B']], {
                    required: true,
                    hint: __('B2C triggers consumer-protection sections.', TXT)
                }),
                toggleField('step2.minorsInvolved', __('The site targets or knowingly collects data from minors under 18', TXT), {
                    legal: 'GDPR Art. 8',
                    rerender: true
                }),
                conditional(minors, function () {
                    return [
                        radioField('step2.minorsMechanism', __('Minimum age verification', TXT), [['none', 'No verification'], ['agegate', 'Via age gate'], ['parental', 'Via parental consent']], {}),
                        notice('warning', __('Processing minors\u2019 data triggers GDPR Art. 8 (parental consent below the national age threshold) and may trigger COPPA for US minors.', TXT))
                    ];
                }),
                toggleField('step2.hasAiFeatures', __('The site uses AI-generated content, AI recommendations or chatbots', TXT), {legal: 'EU AI Act Art. 50'}),
                toggleField('step2.physicalProducts', __('The site sells physical products / has a physical presence', TXT), {
                    legal: 'GPSR Reg. (EU) 2023/988',
                    rerender: true
                }),
                conditional(phys, function () {
                    return [textField('step2.gpsrPerson', __('Name of the EU responsible person (GPSR)', TXT), {
                        legal: 'Reg. (EU) 2023/988',
                        hint: __('A responsible person established in the EU must be named in the Terms & Conditions.', TXT)
                    })];
                }),
                el('div', {'class': 'wpog-field'}, [
                    el('div', {'class': 'wpog-field__label'}, [__('Supervisory authority for your jurisdiction', TXT)]),
                    el('div', {'class': 'wpog-readonly'}, [auth ? (auth.name + ' — ' + (auth.url || '')) : __('(Determined by country of incorporation.)', TXT)])
                ])
            ]
        );
    }

    function exampleActivity() {
        if (isEcommerce()) {
            return {
                dataCategories: ['Name & surname', 'Email address', 'Purchase history'],
                purpose: 'To process and fulfil orders',
                legalBasis: '6(1)(b)',
                legitimateInterest: '',
                isSpecial: false,
                art9Basis: '',
                retention: '10 years (tax/accounting obligation)',
                recipients: ['Payment processors', 'Cloud hosting']
            };
        }
        return {
            dataCategories: ['Name & surname', 'Email address'],
            purpose: 'To respond to contact requests',
            legalBasis: '6(1)(a)',
            legitimateInterest: '',
            isSpecial: false,
            art9Basis: '',
            retention: '24 months from last contact',
            recipients: ['Email providers']
        };
    }

    function renderActivityRow(act, idx) {
        var base = 'step3.activities.' + idx;
        var anySpecial = (act.dataCategories || []).some(function (c) {
            return SPECIAL_CATS.indexOf(c) !== -1;
        });
        var head = el('div', {'class': 'wpog-activity__head'}, [
            el('strong', {}, [__('Processing activity', TXT) + ' #' + (idx + 1)]),
            el('button', {'class': 'button-link wpog-row-remove', type: 'button'}, [__('Remove', TXT)])
        ]);
        head.querySelector('.wpog-row-remove').addEventListener('click', function () {
            state.step3.activities.splice(idx, 1);
            saveDraft();
            rerenderStep();
        });
        var kids = [
            head,
            chipsField(base + '.dataCategories', __('Data categories', TXT), DATA_CATEGORIES, {rerender: true}),
            textField(base + '.purpose', __('Purpose', TXT), {placeholder: 'e.g. To process and fulfil orders'}),
            selectField(base + '.legalBasis', __('Legal basis (GDPR Art. 6)', TXT), LEGAL_BASIS, {rerender: true}),
            conditional(act.legalBasis === '6(1)(f)', function () {
                return [textField(base + '.legitimateInterest', __('Legitimate interest description (must be specific)', TXT), {})];
            })
        ];
        if (anySpecial) {
            kids.push(toggleField(base + '.isSpecial', __('This is Art. 9 special-category data', TXT), {rerender: true}));
            kids.push(conditional(act.isSpecial, function () {
                return [
                    selectField(base + '.art9Basis', __('Art. 9 legal basis', TXT), ART9_BASIS, {}),
                    notice('warning', __('Processing special-category data requires explicit consent (Art. 9(2)(a)) or another specific Art. 9 basis, and typically a DPIA under Art. 35 GDPR. Consult a legal professional before publishing.', TXT))
                ];
            }));
        }
        kids.push(textField(base + '.retention', __('Retention period', TXT), {placeholder: 'e.g. 24 months from last contact'}));
        kids.push(chipsField(base + '.recipients', __('Data recipients', TXT), RECIPIENTS, {}));
        return el('div', {'class': 'wpog-activity'}, kids);
    }

    function renderStep3() {
        if (!state.step3.activities.length) {
            state.step3.activities.push(exampleActivity());
        }
        var list = el('div', {'class': 'wpog-activities'});
        state.step3.activities.forEach(function (act, idx) {
            list.appendChild(renderActivityRow(act, idx));
        });
        var add = el('button', {
            'class': 'button button-secondary',
            type: 'button'
        }, ['+ ' + __('Add processing activity', TXT)]);
        add.addEventListener('click', function () {
            state.step3.activities.push({
                dataCategories: [],
                purpose: '',
                legalBasis: '6(1)(b)',
                legitimateInterest: '',
                isSpecial: false,
                art9Basis: '',
                retention: '',
                recipients: []
            });
            saveDraft();
            rerenderStep();
        });
        return card(
            __('Data Collection', TXT),
            __('Map each personal-data category to its purpose and legal basis (GDPR Arts. 6 & 9). At least one activity is required.', TXT),
            [list, add, fieldError('step3.activities')]
        );
    }

    function renderStep4() {
        var s4 = state.step4;
        var ecom = isEcommerce();
        var summary = el('table', {'class': 'wpog-summary-table'});
        summary.appendChild(el('tr', {}, [el('th', {}, [__('Category', TXT)]), el('th', {}, [__('Services included', TXT)]), el('th', {}, [__('Requires consent?', TXT)])]));
        var svcs = selectedServices();

        function catList(cat) {
            return svcs.filter(function (s) {
                return s.category === cat;
            }).map(function (s) {
                return s.name;
            }).join(', ') || '\u2014';
        }

        summary.appendChild(el('tr', {}, [el('td', {}, ['Strictly Necessary']), el('td', {}, ['Session, CSRF, consent record']), el('td', {'class': 'wpog-no'}, ['\u274C No'])]));
        summary.appendChild(el('tr', {}, [el('td', {}, ['Functional']), el('td', {}, [catList('functional')]), el('td', {'class': 'wpog-yes'}, ['\u2705 Yes'])]));
        summary.appendChild(el('tr', {}, [el('td', {}, ['Analytics']), el('td', {}, [catList('analytics')]), el('td', {'class': 'wpog-yes'}, ['\u2705 Yes'])]));
        summary.appendChild(el('tr', {}, [el('td', {}, ['Marketing']), el('td', {}, [catList('marketing')]), el('td', {'class': 'wpog-yes'}, ['\u2705 Yes'])]));

        return card(
            __('Third-Party Services & Cookies', TXT),
            __('Document every third-party processor and cookie category for the Cookie Policy.', TXT),
            [
                el('h3', {'class': 'wpog-subhead'}, [__('A — Analytics', TXT)]),
                chipsField('step4.analytics', __('Analytics tools used', TXT), ANALYTICS, {rerender: true}),
                conditional(inArr(s4.analytics, 'ga4'), function () {
                    return [
                        toggleField('step4.ga4Anonymize', __('GA4: IP anonymisation enabled', TXT), {}),
                        toggleField('step4.ga4AdSharing', __('GA4: data sharing with Google Ads', TXT), {})
                    ];
                }),
                conditional(inArr(s4.analytics, 'matomo'), function () {
                    return [radioField('step4.matomoHosting', __('Matomo hosting', TXT), [['self', 'Self-hosted'], ['cloud', 'Cloud']], {})];
                }),
                inArr(s4.analytics, 'other') ? otherServicesPanel('step4.analyticsOther', __('Other analytics services', TXT)) : null,
                el('h3', {'class': 'wpog-subhead'}, [__('B — Advertising & Remarketing', TXT)]),
                chipsField('step4.advertising', __('Advertising platforms', TXT), ADVERTISING, {rerender: true}),
                toggleField('step4.programmatic', __('Programmatic advertising (DSPs, ad networks)', TXT), {}),
                inArr(s4.advertising, 'other') ? otherServicesPanel('step4.advertisingOther', __('Other advertising platforms', TXT)) : null,
                el('h3', {'class': 'wpog-subhead'}, [__('C — Social Media & Embeds', TXT)]),
                chipsField('step4.embeds', __('Embedded content', TXT), EMBEDS, {rerender: true}),
                notice('info', __('Each embedded service that loads tracking cookies requires separate user consent. Embedding a player does not transfer your compliance obligations.', TXT)),
                inArr(s4.embeds, 'other') ? otherServicesPanel('step4.embedsOther', __('Other embedded content', TXT)) : null,
                el('h3', {'class': 'wpog-subhead'}, [__('D — Functional & Security', TXT)]),
                toggleField('step4.turnstile', __('Cloudflare Turnstile', TXT), {rerender: true}),
                toggleField('step4.recaptcha', __('Google reCAPTCHA', TXT), {rerender: true}),
                toggleField('step4.googleFonts', __('Google Fonts', TXT), {rerender: true}),
                conditional(s4.googleFonts, function () {
                    return [radioField('step4.fontHosting', __('Font hosting method', TXT), [['cdn', 'Google CDN'], ['self', 'Self-hosted']], {rerender: true})];
                }),
                textField('step4.cdnProvider', __('CDN provider (optional)', TXT), {placeholder: 'e.g. Cloudflare, AWS CloudFront, Fastly'}),
                chipsField('step4.liveChat', __('Live chat / support widget', TXT), LIVECHAT, {rerender: true}),
                inArr(s4.liveChat, 'other') ? otherServicesPanel('step4.liveChatOther', __('Other live chat services', TXT)) : null,
                ecom ? el('h3', {'class': 'wpog-subhead'}, [__('E — Payment Processors', TXT)]) : null,
                ecom ? chipsField('step4.paymentProcessors', __('Payment processors', TXT), PAYMENTS, {rerender: true}) : null,
                ecom && inArr(s4.paymentProcessors, 'other') ? otherServicesPanel('step4.paymentProcessorsOther', __('Other payment processors', TXT)) : null,
                ecom ? notice('info', __('Payment processors typically process card data under PCI DSS. Confirm your PCI DSS scope with your provider.', TXT)) : null,
                el('h3', {'class': 'wpog-subhead'}, [__('F — CRM & Marketing Automation', TXT)]),
                chipsField('step4.crm', __('CRM / email platforms', TXT), CRM, {rerender: true}),
                inArr(s4.crm, 'other') ? otherServicesPanel('step4.crmOther', __('Other CRM / email platforms', TXT)) : null,
                el('h3', {'class': 'wpog-subhead'}, [__('G — Cookie Category Summary', TXT)]),
                summary
            ]
        );
    }

    function renderStep5() {
        var transfers = derivedTransfers();
        var auto = transfers.length > 0;
        if (auto && !state.step5.hasTransfers) {
            state.step5.hasTransfers = true;
        }
        var usTransfer = hasUSTransfer();
        var kids = [];
        if (!transfers.length) {
            kids.push(notice('info', __('No cross-border transfers detected. All selected services are EU/EEA-based or self-hosted. You may continue to the next step.', TXT)));
        } else {
            kids.push(notice('info', __('Transfers were auto-detected from the services selected in the previous step. Review and complete the safeguards below.', TXT)));
            var tbl = el('table', {'class': 'wpog-summary-table'});
            tbl.appendChild(el('tr', {}, [el('th', {}, [__('Country', TXT)]), el('th', {}, [__('Recipients', TXT)]), el('th', {}, [__('Safeguard', TXT)])]));
            transfers.forEach(function (t) {
                tbl.appendChild(el('tr', {}, [
                    el('td', {}, [countryName(t.country)]),
                    el('td', {}, [t.providers.join(', ') || '\u2014']),
                    el('td', {}, [t.mechanisms.join('; ') || 'SCC (2021/914)'])
                ]));
            });
            kids.push(tbl);
            kids.push(chipsField('step5.countries', __('Additional countries data is transferred to', TXT), [['US', 'USA'], ['GB', 'UK'], ['IN', 'India'], ['CA', 'Canada'], ['JP', 'Japan'], ['KR', 'South Korea'], ['OTHER', 'Other']], {}));
            if (usTransfer) {
                kids.push(toggleField('step5.dpfCertified', __('US recipients are EU-US Data Privacy Framework certified', TXT), {
                    legal: 'EU-US DPF adequacy decision of 10/07/2023',
                    rerender: true
                }));
            }
            kids.push(radioField('step5.sccVersion', __('Standard Contractual Clauses version', TXT), [['2021', 'EU Commission Decision 2021/914 (current)'], ['old', 'Older version (pre-2021)']], {rerender: true}));
            if (state.step5.sccVersion === 'old') {
                kids.push(notice('error', __('Pre-2021 SCCs are no longer valid. Update to the 2021/914 SCCs immediately.', TXT)));
            }
            if (usTransfer && !state.step5.dpfCertified && state.step5.sccVersion !== '2021') {
                kids.push(notice('error', __('Transfer mechanism required. Without a valid safeguard under GDPR Art. 46, data transfers to the USA are unlawful.', TXT)));
            }
        }
        return card(__('Data Transfers', TXT), __('Cross-border transfers under GDPR Chapter V (Arts. 44-49).', TXT), kids);
    }

    function renderStep6() {
        var s6 = state.step6;
        var auth = supervisoryAuthority();
        return card(
            __('User Rights', TXT),
            __('How the organisation handles data-subject rights requests (GDPR Arts. 15-22).', TXT),
            [
                chipsField('step6.channels', __('Channels for rights requests', TXT), [['email', 'Dedicated email'], ['form', 'Online form'], ['postal', 'Postal address'], ['phone', 'Phone']], {
                    required: true,
                    rerender: true
                }),
                textField('step6.rightsEmail', __('Rights request email', TXT), {type: 'email'}),
                conditional(inArr(s6.channels, 'form'), function () {
                    return [textField('step6.formUrl', __('Online request form URL', TXT), {type: 'url'})];
                }),
                selectField('step6.responseTime', __('Response time commitment', TXT), [['1month', 'Within 1 month (GDPR standard)'], ['15days', 'Within 15 days'], ['72hours', 'Within 72 hours']], {hint: __('GDPR mandates a maximum of one month; a shorter commitment is allowed.', TXT)}),
                toggleField('step6.extensionPossible', __('The deadline may be extended for complex requests', TXT), {hint: __('GDPR allows up to two additional months (Art. 12(3)).', TXT)}),
                el('div', {'class': 'wpog-field'}, [
                    el('div', {'class': 'wpog-field__label'}, [__('Supervisory authority', TXT)]),
                    el('div', {'class': 'wpog-readonly'}, [auth ? (auth.name + (auth.address ? (' — ' + auth.address) : '')) : __('(Determined by country of incorporation.)', TXT)])
                ]),
                toggleField('step6.hasAutomatedDecisions', __('The site uses automated decision-making or profiling', TXT), {
                    legal: 'GDPR Art. 22',
                    rerender: true
                }),
                conditional(s6.hasAutomatedDecisions, function () {
                    return [
                        textField('step6.automatedDecisionDescription', __('Description of the automated processing and its logic', TXT), {textarea: true}),
                        toggleField('step6.offersHumanReview', __('It produces legal or similarly significant effects (human review offered)', TXT), {})
                    ];
                })
            ]
        );
    }

    function renderStep7() {
        var ecom = isEcommerce();
        var termsToggle = toggleField('step7.generateTerms', __('Generate Terms & Conditions', TXT), {rerender: true});
        if (!ecom) {
            state.step7.generateTerms = false;
            termsToggle.style.opacity = '0.5';
            termsToggle.title = __('Enabled only when an e-commerce website type is selected in Step 2.', TXT);
        }
        return card(
            __('Document Language & Preferences', TXT),
            __('Choose the output language and which documents to generate.', TXT),
            [
                selectField('step7.language', __('Output language', TXT), LANGUAGES, {}),
                toggleField('step7.generatePrivacy', __('Generate Privacy Policy', TXT), {}),
                toggleField('step7.generateCookie', __('Generate Cookie Policy', TXT), {}),
                termsToggle,
                radioField('step7.tone', __('Tone of documents', TXT), [['formal', 'Formal & technical'], ['plain', 'Clear & plain language (recommended)'], ['both', 'Both (generate two versions)']], {hint: __('Plain language is recommended by GDPR Art. 12.', TXT)}),
                toggleField('step7.includeCitations', __('Include legal article citations in documents', TXT), {}),
                ecom ? toggleField('step7.includeWithdrawalForm', __('Include the model withdrawal form', TXT), {hint: __('Required by Consumer Code Annex I.', TXT)}) : null,
                textField('step7.documentVersion', __('Document version number', TXT), {placeholder: '1.0'}),
                textField('step7.effectiveDate', __('Effective date', TXT), {type: 'date'})
            ]
        );
    }

    /* --------------------------------------------------------------------- */
    /* Validation                                                            */

    /* --------------------------------------------------------------------- */
    function isEmail(v) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v || ''));
    }

    function isUrl(v) {
        return /^https?:\/\/.+/.test(String(v || ''));
    }

// Returns { ok:bool, errors:{ path:message } }
    function validateStep(n) {
        var errors = {};
        var s = state;

        function req(path, msg) {
            var v = getPath(s, path);
            if (v == null || v === '') {
                errors[path] = msg || __('This field is required.', TXT);
            }
        }

        if (n === 1) {
            req('step1.companyName');
            req('step1.address');
            if (!isEmail(s.step1.privacyEmail)) {
                errors['step1.privacyEmail'] = __('A valid email is required.', TXT);
            }
            if (s.step1.privacyWebsite && !isUrl(s.step1.privacyWebsite)) {
                errors['step1.privacyWebsite'] = __('Enter a valid URL.', TXT);
            }
            if (s.step1.hasDpo) {
                req('step1.dpoName');
                if (!isEmail(s.step1.dpoEmail)) {
                    errors['step1.dpoEmail'] = __('A valid DPO email is required.', TXT);
                }
            }
            if (!isEU(s.step1.country) && s.step1.hasEuRep) {
                req('step1.euRepDetails');
            }
        } else if (n === 2) {
            if (!isUrl(s.step2.websiteUrl)) {
                errors['step2.websiteUrl'] = __('Enter a valid URL.', TXT);
            }
            if (!(s.step2.types || []).length) {
                errors['step2.types'] = __('Select at least one website type.', TXT);
            }
            if (isEcommerce() && !s.step1.vatNumber) {
                errors['step1.vatNumber'] = __('VAT/Tax ID is required for e-commerce (set in Step 1).', TXT);
            }
        } else if (n === 3) {
            if (!(s.step3.activities || []).length) {
                errors['step3.activities'] = __('Add at least one processing activity.', TXT);
            }
            (s.step3.activities || []).forEach(function (a, i) {
                if (!(a.dataCategories || []).length) {
                    errors['step3.activities.' + i + '.dataCategories'] = __('Select at least one data category.', TXT);
                }
                if (!a.purpose) {
                    errors['step3.activities.' + i + '.purpose'] = __('Describe the purpose.', TXT);
                }
            });
        } else if (n === 5) {
            if (hasUSTransfer() && !s.step5.dpfCertified && s.step5.sccVersion !== '2021') {
                errors['step5.countries'] = __('A valid US transfer safeguard (DPF or 2021 SCCs) is required.', TXT);
            }
        } else if (n === 6) {
            if (!(s.step6.channels || []).length) {
                errors['step6.channels'] = __('Select at least one channel.', TXT);
            }
            if (inArr(s.step6.channels, 'email') && !isEmail(s.step6.rightsEmail)) {
                errors['step6.rightsEmail'] = __('A valid rights-request email is required.', TXT);
            }
        }
        return {ok: Object.keys(errors).length === 0, errors: errors};
    }

    function paintErrors(errors) {
// clear
        Array.prototype.forEach.call(document.querySelectorAll('.wpog-field__error'), function (e) {
            e.textContent = '';
            e.classList.remove('has-error');
        });
        Object.keys(errors).forEach(function (path) {
            var node = document.querySelector('[data-error-for="' + path + '"]');
            if (node) {
                node.textContent = errors[path];
                node.classList.add('has-error');
            }
        });
    }

    function allStepsValid() {
        for (var i = 1; i <= 7; i++) {
            if (!validateStep(i).ok) {
                return false;
            }
        }
        return true;
    }

    /* --------------------------------------------------------------------- */
    /* Step 8 — Review & confirm                                             */

    /* --------------------------------------------------------------------- */
    function reviewPanel(stepN, title, rows) {
        var v = validateStep(stepN);
        var bar = el('div', {'class': 'wpog-review__bar'}, [
            el('span', {}, [title]),
            v.ok ? el('span', {'class': 'wpog-badge-ok'}, ['\u2713']) : el('span', {'class': 'wpog-badge-warn'}, ['\u26A0\uFE0F ' + __('Incomplete', TXT)])
        ]);
        var edit = el('button', {'class': 'button-link', type: 'button'}, [__('Edit', TXT)]);
        edit.addEventListener('click', function () {
            goToStep(stepN);
        });
        bar.appendChild(edit);
        var dl = el('dl', {'class': 'wpog-review__body'});
        rows.forEach(function (r) {
            dl.appendChild(el('dt', {}, [r[0]]));
            dl.appendChild(el('dd', {}, [r[1] || '\u2014']));
        });
        return el('div', {'class': 'wpog-review__panel' + (v.ok ? '' : ' has-error')}, [bar, dl]);
    }

    function joinTypes(arr, map) {
        return (arr || []).map(function (t) {
            var f = map.filter(function (w) {
                return w[0] === t;
            })[0];
            return f ? f[1] : t;
        }).join(', ');
    }

    function renderStep8() {
        var s = state;
        var disclaimer = el('div', {'class': 'wpog-disclaimer'}, [
            el('strong', {}, ['\u26A0\uFE0F ' + __('IMPORTANT LEGAL NOTICE', TXT)]),
            el('p', {}, [__('The prompt you are about to generate will instruct an AI to draft legal documents. AI-generated legal documents may contain errors, omissions or outdated provisions; do not constitute legal advice; and must be reviewed by a qualified legal professional before publication. By proceeding, you acknowledge that wp-opengdpr and its developers accept no liability for the content of AI-generated legal documents.', TXT)])
        ]);
        var panels = el('div', {'class': 'wpog-review'}, [
            reviewPanel(1, __('Business Identity', TXT), [
                [__('Company', TXT), s.step1.companyName],
                [__('Country', TXT), countryName(s.step1.country)],
                [__('Privacy email', TXT), s.step1.privacyEmail],
                [__('DPO', TXT), s.step1.hasDpo ? s.step1.dpoName : __('None', TXT)]
            ]),
            reviewPanel(2, __('Website Scope', TXT), [
                [__('URL', TXT), s.step2.websiteUrl],
                [__('Nature', TXT), joinTypes(s.step2.types, WEBSITE_TYPES)],
                [__('Audience', TXT), s.step2.audience],
                [__('AI features', TXT), s.step2.hasAiFeatures ? __('Yes', TXT) : __('No', TXT)]
            ]),
            reviewPanel(3, __('Data Collection', TXT), [
                [__('Activities', TXT), String((s.step3.activities || []).length)]
            ]),
            reviewPanel(4, __('Services', TXT), [
                [__('Selected services', TXT), String(selectedServiceIds().length)]
            ]),
            reviewPanel(5, __('Transfers', TXT), [
                [__('Cross-border', TXT), derivedTransfers().length ? derivedTransfers().map(function (t) {
                    return countryName(t.country);
                }).join(', ') : __('None', TXT)]
            ]),
            reviewPanel(6, __('User Rights', TXT), [
                [__('Channels', TXT), (s.step6.channels || []).join(', ')],
                [__('Automated decisions', TXT), s.step6.hasAutomatedDecisions ? __('Yes', TXT) : __('No', TXT)]
            ]),
            reviewPanel(7, __('Output', TXT), [
                [__('Language', TXT), LANG_NAMES[s.step7.language]],
                [__('Documents', TXT), [s.step7.generatePrivacy ? 'Privacy' : null, s.step7.generateCookie ? 'Cookie' : null, (s.step7.generateTerms && isEcommerce()) ? 'T&C' : null].filter(Boolean).join(', ')]
            ])
        ]);
        var gen = el('button', {
            'class': 'button button-primary button-hero wpog-generate',
            type: 'button'
        }, ['\uD83E\uDE84 ' + __('Generate Prompt', TXT)]);
        if (!allStepsValid()) {
            gen.disabled = true;
        }
        gen.addEventListener('click', onGenerate);
        return card(__('Review & Confirm', TXT), __('Review your answers. Mistakes caught here mean fewer AI hallucinations.', TXT), [disclaimer, panels, gen, el('div', {id: 'wpog-output-host'})]);
    }

    /* --------------------------------------------------------------------- */
    /* REST helpers                                                          */

    /* --------------------------------------------------------------------- */
    function restFetch(path, opts) {
        opts = opts || {};
        var url = (CFG.restUrl || '').replace(/\/$/, '') + path;
        return fetch(url, {
            method: opts.method || 'GET',
            headers: {'Content-Type': 'application/json', 'X-WP-Nonce': CFG.nonce || ''},
            credentials: 'same-origin',
            body: opts.body ? JSON.stringify(opts.body) : undefined
        }).then(function (r) {
            return r.json().then(function (j) {
                if (!r.ok) {
                    throw new Error((j && j.message) || ('HTTP ' + r.status));
                }
                return j;
            });
        });
    }

    /* --------------------------------------------------------------------- */
    /* Prompt output box                                                     */
    /* --------------------------------------------------------------------- */
    var lastPrompt = '';

    function renderOutput(promptText) {
        lastPrompt = promptText;
        var host = document.getElementById('wpog-output-host');
        if (!host) {
            return;
        }
        host.innerHTML = '';

        var ta = el('textarea', {'class': 'wpog-prompt-output__text', rows: '18'});
        ta.value = promptText;
        ta.readOnly = true;

        var copyBtn = el('button', {
            'class': 'button button-primary wpog-copy-btn',
            type: 'button'
        }, ['\uD83D\uDCCB ' + __('Copy prompt', TXT)]);
        copyBtn.addEventListener('click', function () {
            var done = function () {
                copyBtn.classList.add('copied');
                copyBtn.textContent = '\u2705 ' + __('Copied!', TXT);
                setTimeout(function () {
                    copyBtn.classList.remove('copied');
                    copyBtn.textContent = '\uD83D\uDCCB ' + __('Copy prompt', TXT);
                }, 2000);
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(promptText).then(done, function () {
                    ta.select();
                    document.execCommand('copy');
                    done();
                });
            } else {
                ta.select();
                document.execCommand('copy');
                done();
            }
        });

        var nameInput = el('input', {
            'class': 'wpog-input',
            type: 'text',
            placeholder: __('Name this prompt to save it…', TXT)
        });
        nameInput.value = (state.step1.companyName ? (state.step1.companyName + ' — ') : '') + (state.step7.documentVersion || '1.0');
        var saveBtn = el('button', {
            'class': 'button button-secondary',
            type: 'button'
        }, ['\uD83D\uDCBE ' + __('Save prompt', TXT)]);
        var saveMsg = el('span', {'class': 'wpog-save-msg'});
        saveBtn.addEventListener('click', function () {
            saveBtn.disabled = true;
            saveMsg.textContent = __('Saving…', TXT);
            var docs = [];
            if (state.step7.generatePrivacy) {
                docs.push('privacy');
            }
            if (state.step7.generateCookie) {
                docs.push('cookie');
            }
            if (state.step7.generateTerms && isEcommerce()) {
                docs.push('terms');
            }
            restFetch('/prompts', {
                method: 'POST', body: {
                    prompt_name: nameInput.value || (__('Untitled', TXT)),
                    form_data: state,
                    prompt_text: promptText,
                    documents: docs,
                    language: state.step7.language
                }
            }).then(function () {
                saveMsg.textContent = '\u2705 ' + __('Saved.', TXT);
                saveBtn.disabled = false;
            }).catch(function (e) {
                saveMsg.textContent = '\u26A0\uFE0F ' + e.message;
                saveBtn.disabled = false;
            });
        });

        host.appendChild(el('div', {'class': 'wpog-prompt-output'}, [
            el('div', {
                'class': 'wpog-disclaimer wpog-disclaimer--mini',
                html: '<strong>\u26A0\uFE0F ' + __('AI Legal Notice', TXT) + ':</strong> ' + __('This prompt instructs an AI to draft legal documents. Always have the output reviewed by a qualified legal professional before publication. wp-opengdpr accepts no liability for AI-generated content.', TXT)
            }),
            el('div', {'class': 'wpog-prompt-output__box'}, [ta]),
            el('div', {'class': 'wpog-prompt-output__actions'}, [copyBtn, nameInput, saveBtn, saveMsg])
        ]));
        host.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    function onGenerate() {
        var v = validateStep(8);
        if (!allStepsValid()) {
            return;
        }
        renderOutput(buildPrompt());
    }

    /* --------------------------------------------------------------------- */
    /* Saved prompts view                                                    */

    /* --------------------------------------------------------------------- */
    function renderSaved(host) {
        host.innerHTML = '';
        var wrap = el('div', {'class': 'wpog-saved'}, [
            el('h2', {'class': 'wpog-card__title'}, [__('Saved prompts', TXT)]),
            el('p', {'class': 'wpog-card__sub'}, [__('Previously generated prompts. Load one back into the wizard or delete it.', TXT)])
        ]);
        var list = el('div', {'class': 'wpog-saved-list'}, [el('p', {}, [__('Loading…', TXT)])]);
        wrap.appendChild(list);
        host.appendChild(wrap);

        restFetch('/prompts').then(function (resp) {
            var items = (resp && resp.rows) ? resp.rows : (Array.isArray(resp) ? resp : []);
            list.innerHTML = '';
            if (!items || !items.length) {
                list.appendChild(el('p', {'class': 'wpog-empty'}, [__('No saved prompts yet.', TXT)]));
                return;
            }
            items.forEach(function (it) {
                var docs = (it.documents || '').split(',').filter(Boolean);
                var pills = el('span', {'class': 'wpog-saved-item__docs'}, docs.map(function (d) {
                    return el('span', {'class': 'wpog-doc-pill'}, [d]);
                }));
                var loadBtn = el('button', {'class': 'button button-secondary', type: 'button'}, [__('Load', TXT)]);
                var copyBtn = el('button', {'class': 'button', type: 'button'}, [__('Copy', TXT)]);
                var delBtn = el('button', {'class': 'button-link-delete', type: 'button'}, [__('Delete', TXT)]);
                loadBtn.addEventListener('click', function () {
                    restFetch('/prompts/' + it.id).then(function (full) {
                        try {
                            var fd = typeof full.form_data === 'string' ? JSON.parse(full.form_data) : full.form_data;
                            if (fd) {
                                state = mergeState(defaultState(), fd);
                            }
                        } catch (e) {
                        }
                        saveDraft();
                        switchView('wizard');
                        goToStep(8);
                        if (full.prompt_text) {
                            setTimeout(function () {
                                renderOutput(full.prompt_text);
                            }, 50);
                        }
                    });
                });
                copyBtn.addEventListener('click', function () {
                    restFetch('/prompts/' + it.id).then(function (full) {
                        var t = full.prompt_text || '';
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(t);
                        }
                        copyBtn.textContent = '\u2705';
                        setTimeout(function () {
                            copyBtn.textContent = __('Copy', TXT);
                        }, 1500);
                    });
                });
                delBtn.addEventListener('click', function () {
                    if (!window.confirm(__('Delete this saved prompt?', TXT))) {
                        return;
                    }
                    restFetch('/prompts/' + it.id, {method: 'DELETE'}).then(function () {
                        renderSaved(host);
                    });
                });
                list.appendChild(el('div', {'class': 'wpog-saved-item'}, [
                    el('div', {'class': 'wpog-saved-item__meta'}, [
                        el('span', {'class': 'wpog-saved-item__name'}, [it.prompt_name || __('Untitled', TXT)]),
                        pills,
                        el('span', {'class': 'wpog-saved-item__date'}, [(it.created_at || '') + (it.language ? (' · ' + it.language) : '')])
                    ]),
                    el('div', {'class': 'wpog-saved-actions'}, [loadBtn, copyBtn, delBtn])
                ]));
            });
        }).catch(function (e) {
            list.innerHTML = '';
            list.appendChild(el('p', {'class': 'wpog-empty'}, ['\u26A0\uFE0F ' + e.message]));
        });
    }

    /* --------------------------------------------------------------------- */
    /* Wizard shell, stepper, navigation                                     */
    /* --------------------------------------------------------------------- */
    var ROOT = null;

    var STEP_RENDERERS = {
        1: renderStep1, 2: renderStep2, 3: renderStep3, 4: renderStep4,
        5: renderStep5, 6: renderStep6, 7: renderStep7, 8: renderStep8
    };

    function buildStepper() {
        var box = el('div', {'class': 'wpog-stepper'});
        STEP_META.forEach(function (m) {
            var st = 'wpog-stepper__item';
            if (m.n === currentStep) {
                st += ' is-current';
            } else if (m.n < currentStep) {
                st += ' is-done';
            }
            var item = el('div', {'class': st}, [
                el('span', {'class': 'wpog-stepper__num'}, [m.n < currentStep ? '\u2713' : m.icon]),
                el('span', {'class': 'wpog-stepper__label'}, [m.label])
            ]);
            item.addEventListener('click', function () {
                if (m.n < currentStep) {
                    goToStep(m.n);
                }
            });
            box.appendChild(item);
        });
        return box;
    }

    function buildNav() {
        var nav = el('div', {'class': 'wpog-wizard-nav'});
        var back = el('button', {'class': 'button button-secondary', type: 'button'}, ['\u2190 ' + __('Back', TXT)]);
        back.disabled = currentStep === 1;
        back.addEventListener('click', function () {
            if (currentStep > 1) {
                goToStep(currentStep - 1);
            }
        });
        var count = el('span', {'class': 'wpog-step-count'}, [sprintf(__('Step %1$d of %2$d', TXT), currentStep, TOTAL)]);
        nav.appendChild(back);
        nav.appendChild(count);
        if (currentStep < TOTAL) {
            var next = el('button', {'class': 'button button-primary', type: 'button'}, [__('Next', TXT) + ' \u2192']);
            next.addEventListener('click', function () {
                var v = validateStep(currentStep);
                validatedOnce[currentStep] = true;
                if (!v.ok) {
                    paintErrors(v.errors);
                    return;
                }
                goToStep(currentStep + 1);
            });
            nav.appendChild(next);
        } else {
            nav.appendChild(el('span', {}));
        }
        return nav;
    }

    function renderWizard() {
        ROOT.innerHTML = '';
        ROOT.appendChild(buildTabs());
        ROOT.appendChild(buildStepper());
        var body = el('div', {'class': 'wpog-step-body', id: 'wpog-step-body'});
        body.appendChild(STEP_RENDERERS[currentStep]());
        ROOT.appendChild(body);
        ROOT.appendChild(buildNav());
        if (validatedOnce[currentStep]) {
            paintErrors(validateStep(currentStep).errors);
        }
    }

// Re-render only the current step body (used by conditional/chips changes).
    rerenderStep = function () {
        var body = document.getElementById('wpog-step-body');
        if (!body) {
            renderWizard();
            return;
        }
        body.innerHTML = '';
        body.appendChild(STEP_RENDERERS[currentStep]());
        if (validatedOnce[currentStep]) {
            paintErrors(validateStep(currentStep).errors);
        }
// refresh stepper/nav state too
        var stepper = ROOT.querySelector('.wpog-stepper');
        if (stepper) {
            ROOT.replaceChild(buildStepper(), stepper);
        }
    };

    function goToStep(n) {
        currentStep = Math.max(1, Math.min(TOTAL, n));
        saveDraft();
        renderWizard();
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function buildTabs() {
        var tabs = el('div', {'class': 'wpog-legal__tabs'});
        var t1 = el('button', {
            'class': 'wpog-legal__tab' + (view === 'wizard' ? ' is-active' : ''),
            type: 'button'
        }, ['\uD83E\uDE84 ' + __('Generator', TXT)]);
        var t2 = el('button', {
            'class': 'wpog-legal__tab' + (view === 'saved' ? ' is-active' : ''),
            type: 'button'
        }, ['\uD83D\uDCBE ' + __('Saved prompts', TXT)]);
        t1.addEventListener('click', function () {
            switchView('wizard');
        });
        t2.addEventListener('click', function () {
            switchView('saved');
        });
        tabs.appendChild(t1);
        tabs.appendChild(t2);
        return tabs;
    }

    function switchView(v) {
        view = v;
        render();
    }

    function render() {
        if (view === 'saved') {
            ROOT.innerHTML = '';
            ROOT.appendChild(buildTabs());
            var host = el('div', {'class': 'wpog-card'});
            ROOT.appendChild(host);
            renderSaved(host);
        } else {
            renderWizard();
        }
    }

    /* --------------------------------------------------------------------- */
    /* Boot                                                                  */

    /* --------------------------------------------------------------------- */
    function boot() {
        ROOT = document.getElementById('wpog-legal-generator');
        if (!ROOT) {
            return;
        }
        ROOT.classList.add('wpog-legal');
        var loading = ROOT.querySelector('.wpog-legal-loading');
        if (loading) {
            loading.parentNode.removeChild(loading);
        }
        var hadDraft = loadDraft();
        if (!hadDraft) {
            applyPrefill();
        } else {
// still flag prefilled origins for the badges, without overwriting values
            var tmp = state;
            state = defaultState();
            applyPrefill();
            state = tmp;
        }
        ROOT.appendChild(el('div', {'class': 'wpog-legal__intro'}, [
            el('span', {'class': 'wpog-legal__badge'}, ['\u2728']),
            el('div', {}, [
                el('h1', {}, [__('Legal Document Prompt Generator', TXT)]),
                el('p', {}, [__('Answer the wizard to assemble a ready-to-use AI prompt for your Privacy Policy, Cookie Policy and Terms & Conditions. Nothing is sent to any AI service — you copy the prompt yourself.', TXT)])
            ])
        ]));
        var mount = el('div', {id: 'wpog-legal-mount'});
        ROOT.appendChild(mount);
        ROOT = mount;
        render();
    }

    window.WPOG_LEGAL = {
        buildPrompt: buildPrompt, getState: function () {
            return state;
        }, derivedTransfers: derivedTransfers
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

}());
