/**
 * Asks before sending a form that has a data-confirm attribute, such as a delete button, with that attribute's text
 */
(function () {
    'use strict';

    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            event.preventDefault();
        }
    });
})();
