(function ($) {
    'use strict';

    const settings = window.SmoothBookingNotifications || {};
    let editorPrepared = false;

    function getEditorId() {
        if (settings.editorId) {
            return settings.editorId;
        }

        return 'smooth-booking-notification-body-html';
    }

    function prepareEditor() {
        const editorId = getEditorId();

        if (!editorId) {
            return;
        }

        const textarea = document.getElementById(editorId);

        if (!textarea) {
            return;
        }

        const hasTinymce = typeof window.tinymce !== 'undefined';
        const editor = hasTinymce ? window.tinymce.get(editorId) : null;

        if (editor) {
            window.setTimeout(function () {
                try {
                    if (typeof editor.show === 'function') {
                        editor.show();
                    }

                    editor.fire('show');
                    editor.execCommand('mceRepaint');
                    editor.focus();
                } catch (error) {
                    // Ignore TinyMCE repaint issues; editor will still render.
                }
            }, 0);

            editorPrepared = true;
            return;
        }

        if (editorPrepared) {
            return;
        }

        if (!window.wp || !window.wp.editor || typeof window.wp.editor.initialize !== 'function') {
            return;
        }

        let defaults = {};

        if (typeof window.wp.editor.getDefaultSettings === 'function') {
            defaults = window.wp.editor.getDefaultSettings();
        }

        const custom = settings.editorSettings || {};
        const config = $.extend(true, {}, defaults, custom);

        try {
            window.wp.editor.initialize(editorId, config);
            editorPrepared = true;
        } catch (error) {
            // Swallow initialisation errors to avoid breaking the form drawer.
        }
    }

    function syncTriggerState($trigger, isOpen) {
        if (!$trigger || !$trigger.length) {
            return;
        }

        const $label = $trigger.find('.smooth-booking-open-form__label');

        if (typeof $trigger.data('initialLabel') === 'undefined') {
            const initial = $label.length ? $label.text() : $trigger.text();
            $trigger.data('initialLabel', initial);
        }

        const openLabel = $trigger.data('openLabel');
        const closeLabel = $trigger.data('closeLabel');
        const fallback = $trigger.data('initialLabel') || '';
        const nextLabel = isOpen ? (closeLabel || fallback) : (openLabel || fallback);

        if ($label.length && nextLabel) {
            $label.text(nextLabel);
        } else if (nextLabel) {
            $trigger.text(nextLabel);
        }

        $trigger.attr('aria-expanded', isOpen ? 'true' : 'false');
    }

    function toggleDrawer(target, forceOpen) {
        if (!target) {
            return;
        }

        const $drawer = $('.smooth-booking-form-drawer[data-context="' + target + '"]');

        if (!$drawer.length) {
            return;
        }

        const isOpen = $drawer.hasClass('is-open') && !$drawer.attr('hidden');
        const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : !isOpen;
        const $trigger = $('.smooth-booking-open-form[data-target="' + target + '"]');

        if (shouldOpen) {
            $drawer.removeAttr('hidden').addClass('is-open');
            syncTriggerState($trigger, true);
            prepareEditor();

            const focusSelector = $drawer.data('focusSelector');
            if (focusSelector) {
                window.setTimeout(function () {
                    const $focus = $(focusSelector).first();
                    if ($focus.length) {
                        $focus.trigger('focus');
                    }
                }, 75);
            }
        } else {
            $drawer.attr('hidden', true).removeClass('is-open');
            syncTriggerState($trigger, false);
        }
    }

    function initNotifications() {
        const scopeSelect = document.getElementById('smooth-booking-notification-service-scope');
        const servicesWrapper = document.querySelector('.smooth-booking-notification-services-select');
        const recipients = document.querySelectorAll('input[name="notification_recipients[]"]');
        const customContainer = document.querySelector('.smooth-booking-notification-custom-emails');
        const codesToggle = document.querySelector('.smooth-booking-toggle-codes');
        const codesPanel = document.getElementById('smooth-booking-notification-codes');

        if (typeof $.fn.select2 === 'function') {
            $('.smooth-booking-select2').select2({ width: '100%' });
        }

        function toggleServices(value) {
            if (!servicesWrapper) {
                return;
            }

            if ('selected' === value) {
                servicesWrapper.classList.add('is-visible');
            } else {
                servicesWrapper.classList.remove('is-visible');
            }
        }

        if (scopeSelect) {
            toggleServices(scopeSelect.value);
            scopeSelect.addEventListener('change', function () {
                toggleServices(this.value);
            });
        }

        function toggleCustomEmails() {
            if (!customContainer) {
                return;
            }

            let hasCustom = false;
            recipients.forEach(function (input) {
                if ('custom' === input.value && input.checked) {
                    hasCustom = true;
                }
            });

            if (hasCustom) {
                customContainer.classList.add('is-visible');
            } else {
                customContainer.classList.remove('is-visible');
            }
        }

        if (recipients.length) {
            toggleCustomEmails();
            recipients.forEach(function (input) {
                input.addEventListener('change', toggleCustomEmails);
            });
        }

        if (codesToggle && codesPanel) {
            codesToggle.addEventListener('click', function (event) {
                event.preventDefault();

                const isHidden = codesPanel.hasAttribute('hidden');

                if (isHidden) {
                    codesPanel.removeAttribute('hidden');
                    codesToggle.textContent = settings.hideCodes || codesToggle.textContent;
                } else {
                    codesPanel.setAttribute('hidden', 'hidden');
                    codesToggle.textContent = settings.showCodes || codesToggle.textContent;
                }
            });
        }
    }

    $(function () {
        initNotifications();

        $('.smooth-booking-form-drawer.is-open').each(function () {
            const target = $(this).data('context');
            toggleDrawer(target, true);
        });

        const $openDrawer = $('.smooth-booking-form-drawer.is-open[data-context="notification-form"]');

        if ($openDrawer.length) {
            prepareEditor();
        }
    });

    $(document).on('click', '.smooth-booking-open-form', function (event) {
        event.preventDefault();
        toggleDrawer($(this).data('target'));
    });

    $(document).on('click', '.smooth-booking-form-dismiss', function (event) {
        const target = $(this).data('target');
        if (!target) {
            return;
        }

        event.preventDefault();
        toggleDrawer(target, false);
    });
})(jQuery);
