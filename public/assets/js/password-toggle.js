/**
 * Show and hide buttons for password fields, replacing the old site's show-hide-password.js. A button with
 * data-toggle-password="id" switches the field with that id between hidden and shown.
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
        const field = document.getElementById(button.dataset.togglePassword);
        if (!field) {
            return;
        }

        const label = button.querySelector('.visually-hidden');
        const icon = button.querySelector('i');

        button.setAttribute('aria-pressed', 'false');
        button.addEventListener('click', function () {
            const show = field.type === 'password';
            field.type = show ? 'text' : 'password';
            button.setAttribute('aria-pressed', show ? 'true' : 'false');
            if (icon) {
                icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
            }
            if (label) {
                label.textContent = show ? 'Hide password' : 'Show password';
            }
        });
    });
})();
