import { Controller } from '@hotwired/stimulus';

/*
 * GDPR cookie consent (RGPD) with Google Consent Mode v2.
 *
 * Behaviour:
 *  - Consent Mode v2 defaults to "denied" for every storage type (set inline in
 *    base.html.twig BEFORE this controller runs).
 *  - The banner offers three equally-easy actions: Accept all / Refuse all /
 *    Customise. No checkbox is pre-checked beyond the non-disableable essentials.
 *  - On explicit analytics consent we call gtag('consent','update',...) and load
 *    Google Analytics. GA never loads without consent.
 *  - The choice is stored in localStorage for 6 months, and a cookie mirror
 *    (cookie_consent) is kept so the server can read it if needed.
 *  - openPreferences() re-opens the panel so users can change their mind (linked
 *    from the cookie policy page).
 *
 * Values:
 *  - gaIdValue: Google Analytics measurement id.
 */
export default class extends Controller {
    static targets = ['banner', 'panel', 'analyticsToggle'];
    static values = {
        gaId: String,
        storageKey: { type: String, default: 'cookie_consent_v2' },
        // 6 months in milliseconds.
        maxAge: { type: Number, default: 1000 * 60 * 60 * 24 * 182 },
    };

    connect() {
        this.gaLoaded = false;
        const stored = this.readConsent();

        if (stored === null) {
            this.showBanner();
            return;
        }

        // Re-apply a previously granted analytics consent on every page load.
        if (stored.analytics === true) {
            this.grantAnalytics();
        }
    }

    // --- UI ---------------------------------------------------------------

    showBanner() {
        if (this.hasBannerTarget) {
            this.bannerTarget.hidden = false;
        }
    }

    hideBanner() {
        if (this.hasBannerTarget) {
            this.bannerTarget.hidden = true;
        }
        this.closePreferences();
    }

    openPreferences() {
        this.showBanner();
        if (this.hasPanelTarget) {
            this.panelTarget.hidden = false;
        }
        // Reflect any stored choice into the toggle (default: unchecked).
        const stored = this.readConsent();
        if (this.hasAnalyticsToggleTarget) {
            this.analyticsToggleTarget.checked = stored ? stored.analytics === true : false;
        }
    }

    closePreferences() {
        if (this.hasPanelTarget) {
            this.panelTarget.hidden = true;
        }
    }

    // --- Actions ----------------------------------------------------------

    acceptAll() {
        this.persist({ analytics: true });
        this.grantAnalytics();
        this.hideBanner();
    }

    refuseAll() {
        this.persist({ analytics: false });
        this.denyAnalytics();
        this.hideBanner();
    }

    savePreferences() {
        const analytics = this.hasAnalyticsToggleTarget && this.analyticsToggleTarget.checked;
        this.persist({ analytics });
        if (analytics) {
            this.grantAnalytics();
        } else {
            this.denyAnalytics();
        }
        this.hideBanner();
    }

    // --- Consent Mode v2 --------------------------------------------------

    grantAnalytics() {
        this.gtag('consent', 'update', {
            analytics_storage: 'granted',
        });
        this.loadGa();
    }

    denyAnalytics() {
        this.gtag('consent', 'update', {
            analytics_storage: 'denied',
        });
    }

    loadGa() {
        if (this.gaLoaded || !this.gaIdValue) {
            return;
        }
        this.gaLoaded = true;

        const s = document.createElement('script');
        s.async = true;
        s.src = `https://www.googletagmanager.com/gtag/js?id=${this.gaIdValue}`;
        document.head.appendChild(s);

        this.gtag('js', new Date());
        this.gtag('config', this.gaIdValue, { anonymize_ip: true });
    }

    gtag() {
        window.dataLayer = window.dataLayer || [];
        // gtag must push the raw `arguments` object, not an array copy.
        // eslint-disable-next-line prefer-rest-params
        window.dataLayer.push(arguments);
    }

    // --- Persistence ------------------------------------------------------

    persist(choice) {
        const payload = { ...choice, ts: Date.now() };
        try {
            window.localStorage.setItem(this.storageKeyValue, JSON.stringify(payload));
        } catch (e) {
            // localStorage unavailable (private mode) — fall back to cookie only.
        }
        const value = choice.analytics ? 'accepted' : 'refused';
        const maxAgeSeconds = Math.floor(this.maxAgeValue / 1000);
        document.cookie = `cookie_consent=${value};path=/;max-age=${maxAgeSeconds};SameSite=Lax`;
    }

    readConsent() {
        try {
            const raw = window.localStorage.getItem(this.storageKeyValue);
            if (!raw) {
                return null;
            }
            const parsed = JSON.parse(raw);
            if (typeof parsed.ts === 'number' && Date.now() - parsed.ts > this.maxAgeValue) {
                // Choice expired — ask again.
                window.localStorage.removeItem(this.storageKeyValue);
                return null;
            }
            return parsed;
        } catch (e) {
            return null;
        }
    }
}
