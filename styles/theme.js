/**
 * Market Plier - Theme Switcher
 * Gère le basculement entre thème clair (#F2EFE9) et thème sombre (#171615)
 * Utilise Bootstrap 5.3 data-bs-theme + cookies (persistant entre sessions)
 */
(function () {
    "use strict";

    var COOKIE_NAME = "mp-theme";
    var COOKIE_DAYS = 365;

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
        var match = document.cookie.match(
            new RegExp("(^| )" + name + "=([^;]+)"),
        );
        return match ? decodeURIComponent(match[2]) : null;
    }

    function getStoredTheme() {
        return getCookie(COOKIE_NAME);
    }

    function setStoredTheme(theme) {
        setCookie(COOKIE_NAME, theme, COOKIE_DAYS);
    }

    function getPreferredTheme() {
        var stored = getStoredTheme();
        if (stored) return stored;
        return window.matchMedia("(prefers-color-scheme: dark)").matches
            ? "dark"
            : "light";
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute("data-bs-theme", theme);
    }

    // Appliquer immédiatement pour éviter le flash
    applyTheme(getPreferredTheme());

    // Réagir aux changements de préférence système
    window
        .matchMedia("(prefers-color-scheme: dark)")
        .addEventListener("change", function () {
            var stored = getStoredTheme();
            if (!stored) {
                applyTheme(getPreferredTheme());
            }
        });

    window.addEventListener("DOMContentLoaded", function () {
        // Tous les boutons toggle de thème (header desktop + menu mobile)
        var toggleButtons = document.querySelectorAll("[data-theme-toggle]");
        toggleButtons.forEach(function (btn) {
            btn.addEventListener("click", function (e) {
                e.preventDefault();
                var current =
                    document.documentElement.getAttribute("data-bs-theme") ||
                    "light";
                var next = current === "light" ? "dark" : "light";
                applyTheme(next);
                setStoredTheme(next);
                updateToggleLabels(next);
            });
        });

        updateToggleLabels(getPreferredTheme());
    });

    function updateToggleLabels(theme) {
        // Mettre à jour le texte du bouton mobile
        var mobileLabel = document.querySelector(".theme-toggle-label");
        if (mobileLabel) {
            mobileLabel.textContent =
                theme === "dark" ? "Mode clair" : "Mode sombre";
        }
    }

    // Menu mobile
    window.addEventListener("DOMContentLoaded", function () {
        var hamburger = document.querySelector(".hamburger-btn");
        var mobileMenu = document.querySelector(".mobile-menu");
        var overlay = document.querySelector(".mobile-menu-overlay");
        var closeBtn = document.querySelector(".mobile-menu-close");

        if (!hamburger || !mobileMenu) return;

        function openMenu() {
            mobileMenu.classList.add("active");
            if (overlay) {
                overlay.style.display = "block";
                // Force reflow pour l'animation
                overlay.offsetHeight;
                overlay.classList.add("active");
            }
            document.body.style.overflow = "hidden";
        }

        function closeMenu() {
            mobileMenu.classList.remove("active");
            if (overlay) {
                overlay.classList.remove("active");
                setTimeout(function () {
                    overlay.style.display = "none";
                }, 300);
            }
            document.body.style.overflow = "";
        }

        hamburger.addEventListener("click", openMenu);
        if (closeBtn) closeBtn.addEventListener("click", closeMenu);
        if (overlay) overlay.addEventListener("click", closeMenu);

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") closeMenu();
        });
    });
})();
