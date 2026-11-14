/**
 * Light, dark or automatic (following the operating system) theme for the admin area.
 *
 * Load this in <head>, before the stylesheets, so the theme is set before the page is drawn and it does not flash white.
 * It sets data-bs-theme on <html>, which Bootstrap and css/dark.css use. The choice is kept in localStorage, so it is
 * remembered by each browser, and other open tabs change with it.
 *
 * The switch is any element with data-theme-value="light|dark|auto". An element with data-theme-icon shows the icon of
 * the current choice.
 */
(function () {
    'use strict';

    const STORAGE_KEY = 'fws-admin-theme';
    const THEMES = ['light', 'dark', 'auto'];
    const ICONS = {light: 'bi-sun', dark: 'bi-moon-stars', auto: 'bi-circle-half'};
    const systemDark = window.matchMedia('(prefers-color-scheme: dark)');

    // Storage can be blocked, such as in some private windows, so the theme then falls back to automatic
    function storedTheme() {
        try {
            const theme = localStorage.getItem(STORAGE_KEY);
            return THEMES.includes(theme) ? theme : 'auto';
        } catch (e) {
            return 'auto';
        }
    }

    function storeTheme(theme) {
        try {
            localStorage.setItem(STORAGE_KEY, theme);
        } catch (e) {
            // The theme still changes for this page
        }
    }

    function applyTheme(theme) {
        const resolved = theme === 'auto' ? (systemDark.matches ? 'dark' : 'light') : theme;
        document.documentElement.setAttribute('data-bs-theme', resolved);
    }

    function showChoice(theme) {
        document.querySelectorAll('[data-theme-value]').forEach(function (option) {
            const chosen = option.getAttribute('data-theme-value') === theme;
            option.classList.toggle('active', chosen);
            option.setAttribute('aria-pressed', chosen ? 'true' : 'false');
        });

        document.querySelectorAll('[data-theme-icon]').forEach(function (icon) {
            icon.className = 'bi ' + ICONS[theme];
        });
    }

    applyTheme(storedTheme());

    // Follow the operating system when it changes, if the choice is automatic
    systemDark.addEventListener('change', function () {
        if (storedTheme() === 'auto') {
            applyTheme('auto');
        }
    });

    // Change with the other open admin tabs
    window.addEventListener('storage', function (event) {
        if (event.key === STORAGE_KEY) {
            applyTheme(storedTheme());
            showChoice(storedTheme());
        }
    });

    window.addEventListener('DOMContentLoaded', function () {
        showChoice(storedTheme());

        document.querySelectorAll('[data-theme-value]').forEach(function (option) {
            option.addEventListener('click', function () {
                const theme = option.getAttribute('data-theme-value');
                storeTheme(theme);
                applyTheme(theme);
                showChoice(theme);
            });
        });
    });
})();
