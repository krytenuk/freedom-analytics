/**
 * Replaces the two factor code field with boxes, one for each character of the code.
 *
 * - The code from the user's authenticator app has 6 digit boxes, in two groups of 3.
 * - Where recovery codes are accepted, a link switches to 10 boxes, in two groups of 5 with a dash between them, to
 *   match how recovery codes are shown, such as "7kx2m-p9qrt".
 *
 * In either set of boxes:
 *
 * - Typing a character moves to the next box.
 * - Backspace clears the current box and moves back to the previous one.
 * - Pasting or autofilling the whole code fills every box.
 *
 * The boxes have no name, so only the original field is submitted, and the script keeps it up to date.
 */
(function () {
    'use strict';

    const MODES = {
        app: {
            length: 6,
            split: 3,
            separator: '',
            label: 'The 6 digit code from your authenticator app',
            boxLabel: 'Digit',
            inputMode: 'numeric',
            pattern: '[0-9]*',
            // The first box takes the whole code when the browser autofills it from a text message or password manager
            autocomplete: 'one-time-code',
            clean: function (value) {
                return value.replace(/\D/g, '');
            },
        },
        recovery: {
            length: 10,
            split: 5,
            separator: '-',
            label: 'One of your recovery codes',
            boxLabel: 'Character',
            inputMode: 'text',
            pattern: null,
            autocomplete: 'off',
            // The dash is shown between the boxes, so it is not typed into one
            clean: function (value) {
                return value.toLowerCase().replace(/[^0-9a-z]/g, '');
            },
        },
    };

    /**
     * Builds one set of boxes, which writes its code to the field whenever it changes.
     */
    function createBoxes(mode, field) {
        const group = document.createElement('div');
        group.className = 'two-factor-code-boxes';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', mode.label);
        group.hidden = true;

        const boxes = [];
        for (let i = 0; i < mode.length; i++) {
            if (i === mode.split) {
                const separator = document.createElement('span');
                separator.className = 'two-factor-code-separator';
                separator.setAttribute('aria-hidden', 'true');
                separator.textContent = mode.separator;
                group.appendChild(separator);
            }

            const box = document.createElement('input');
            box.type = 'text';
            box.className = 'form-control';
            box.inputMode = mode.inputMode;
            if (mode.pattern) {
                box.pattern = mode.pattern;
            }
            box.autocomplete = i === 0 ? mode.autocomplete : 'off';
            box.setAttribute('autocapitalize', 'off');
            box.spellcheck = false;
            box.setAttribute('aria-label', mode.boxLabel + ' ' + (i + 1) + ' of ' + mode.length);
            group.appendChild(box);
            boxes.push(box);
        }

        function sync() {
            const values = boxes.map(function (box) {
                return box.value;
            });
            field.value = values.slice(0, mode.split).join('') + mode.separator + values.slice(mode.split).join('');
            if (field.value === mode.separator) {
                field.value = '';
            }
        }

        function focusBox(index) {
            const box = boxes[Math.max(0, Math.min(mode.length - 1, index))];
            box.focus();
            box.select();
        }

        /**
         * Puts characters in the boxes from the given one onwards, and moves to the box after the last one filled.
         */
        function fill(index, characters) {
            let i = index;
            for (const character of characters) {
                if (i >= mode.length) {
                    break;
                }
                boxes[i].value = character;
                i++;
            }
            sync();
            focusBox(i);
        }

        boxes.forEach(function (box, index) {
            box.addEventListener('focus', function () {
                box.select();
            });

            box.addEventListener('input', function (event) {
                // Typing one character into a box that already had one keeps only the new character
                const typed = event.inputType === 'insertText' && event.data ? event.data : box.value;
                const characters = mode.clean(typed);

                box.value = '';
                if (characters === '') {
                    sync();
                    return;
                }

                fill(index, characters);
            });

            box.addEventListener('paste', function (event) {
                const characters = mode.clean((event.clipboardData || window.clipboardData).getData('text'));
                event.preventDefault();
                if (characters !== '') {
                    // A whole code always starts in the first box, wherever it was pasted
                    fill(characters.length >= mode.length ? 0 : index, characters);
                }
            });

            box.addEventListener('keydown', function (event) {
                switch (event.key) {
                    case 'Backspace':
                        event.preventDefault();
                        box.value = '';
                        sync();
                        focusBox(index - 1);
                        break;
                    case 'Delete':
                        event.preventDefault();
                        box.value = '';
                        sync();
                        break;
                    case 'ArrowLeft':
                        event.preventDefault();
                        focusBox(index - 1);
                        break;
                    case 'ArrowRight':
                        event.preventDefault();
                        focusBox(index + 1);
                        break;
                }
            });
        });

        return {
            group: group,
            show: function (focus) {
                group.hidden = false;
                boxes.forEach(function (box) {
                    box.value = '';
                });
                sync();
                if (focus) {
                    focusBox(0);
                }
            },
            hide: function () {
                group.hidden = true;
            },
            /**
             * Moves to the first empty box, and returns false, if the code is not finished.
             */
            check: function () {
                const empty = boxes.findIndex(function (box) {
                    return box.value === '';
                });
                if (empty === -1) {
                    return true;
                }
                focusBox(empty);
                return false;
            },
        };
    }

    document.querySelectorAll('[data-two-factor-code]').forEach(function (wrapper) {
        const text = wrapper.querySelector('[data-two-factor-code-text]');
        const field = text.querySelector('input');
        const form = field.form;

        // The field is only shown without this script. Hidden fields cannot show the browser's own "required" message,
        // so the submit handler checks instead.
        text.hidden = true;
        field.required = false;

        const app = createBoxes(MODES.app, field);
        text.after(app.group);

        let current = app;

        function use(boxes, focus) {
            current.hide();
            current = boxes;
            current.show(focus);
        }

        if (wrapper.dataset.recovery === 'true') {
            const recovery = createBoxes(MODES.recovery, field);
            app.group.after(recovery.group);

            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'btn btn-link btn-sm p-0 mt-2 d-block mx-auto';
            toggle.textContent = 'Use a recovery code instead';
            toggle.addEventListener('click', function () {
                if (current === app) {
                    use(recovery, true);
                    toggle.textContent = 'Use a code from your app instead';
                } else {
                    use(app, true);
                    toggle.textContent = 'Use a recovery code instead';
                }
            });
            wrapper.appendChild(toggle);
        }

        form.addEventListener('submit', function (event) {
            if (!current.check()) {
                event.preventDefault();
            }
        });

        app.show(field.autofocus);
    });
})();
