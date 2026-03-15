/**
 * Market Plier - Cookie Consent Manager
 * Gère le bandeau de consentement RGPD et le stockage des préférences cookies.
 */
(function () {
    "use strict";

    var COOKIE_NAME = "mp_cookie_consent";
    var COOKIE_DAYS = 365;

    // Helpers cookies

    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie =
            name +
            "=" +
            encodeURIComponent(value) +
            ";expires=" +
            d.toUTCString() +
            ";path=/;SameSite=Lax";
    }

    function getCookie(name) {
        var match = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
        return match ? decodeURIComponent(match[2]) : null;
    }

    function getConsent() {
        var raw = getCookie(COOKIE_NAME);
        if (!raw) return null;
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function saveConsent(consent) {
        setCookie(COOKIE_NAME, JSON.stringify(consent), COOKIE_DAYS);
    }

    // UI

    function showBanner() {
        var banner = document.getElementById("cookieBanner");
        if (banner) {
            banner.classList.add("visible");
        }
    }

    function hideBanner() {
        var banner = document.getElementById("cookieBanner");
        if (banner) {
            banner.classList.remove("visible");
        }
    }

    function showModal() {
        var modal = document.getElementById("cookieModal");
        if (modal) modal.classList.add("visible");
    }

    function hideModal() {
        var modal = document.getElementById("cookieModal");
        if (modal) modal.classList.remove("visible");
    }

    // Actions

    function applyConsent(analytics, marketing, preferences) {
        var consent = {
            necessary: true,
            analytics: analytics,
            marketing: marketing,
            preferences: preferences,
        };
        saveConsent(consent);
        hideBanner();
        hideModal();
    }

    function acceptAll() {
        applyConsent(true, true, true);
    }

    function refuseAll() {
        applyConsent(false, false, false);
    }

    function saveCustom() {
        applyConsent(
            !!document.getElementById("cookieAnalytics").checked,
            !!document.getElementById("cookieMarketing").checked,
            !!document.getElementById("cookiePreferences").checked,
        );
    }

    // Init

    window.addEventListener("DOMContentLoaded", function () {
        // Si déjà consenti, ne rien afficher
        if (getConsent()) return;

        showBanner();

        // Boutons du bandeau
        var btnAccept = document.getElementById("cookieAcceptAll");
        var btnRefuse = document.getElementById("cookieRefuseAll");
        var btnSettings = document.getElementById("cookieSettings");

        if (btnAccept) btnAccept.addEventListener("click", acceptAll);
        if (btnRefuse) btnRefuse.addEventListener("click", refuseAll);
        if (btnSettings)
            btnSettings.addEventListener("click", function () {
                showModal();
            });

        // Boutons de la modal
        var modalAccept = document.getElementById("cookieModalAccept");
        var modalSave = document.getElementById("cookieModalSave");
        var modalClose = document.getElementById("cookieModalClose");

        if (modalAccept) modalAccept.addEventListener("click", acceptAll);
        if (modalSave) modalSave.addEventListener("click", saveCustom);
        if (modalClose) modalClose.addEventListener("click", hideModal);

        // Fermer modal au clic sur l'overlay
        var overlay = document.getElementById("cookieModal");
        if (overlay) {
            overlay.addEventListener("click", function (e) {
                if (e.target === overlay) hideModal();
            });
        }
    });

    // Exposer pour accès externe (ex: lien "Gérer les cookies" dans le footer)
    window.MarketPlierCookies = {
        getConsent: getConsent,
        showSettings: function () {
            showModal();
        },
    };
})();
