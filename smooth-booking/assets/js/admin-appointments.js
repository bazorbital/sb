(function ($) {
    'use strict';

    const settings = window.SmoothBookingAppointments || {};

    function closeMenus() {
        $('.smooth-booking-actions-list').attr('hidden', true);
        $('.smooth-booking-actions-toggle').attr('aria-expanded', 'false');
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

    function initSelect2() {
        if (typeof $.fn.select2 !== 'function') {
            return;
        }

        $('.smooth-booking-select2').each(function () {
            const $select = $(this);
            const placeholder = $select.data('placeholder') || '';

            $select.select2({
                width: 'resolve',
                placeholder: placeholder,
                allowClear: true,
                dropdownParent: $select.closest('.smooth-booking-form-card')
            });
        });
    }

    function syncEndOptions() {
        const startValue = $('#smooth-booking-appointment-start').val();
        const $endSelect = $('#smooth-booking-appointment-end');

        if (!$endSelect.length || !startValue) {
            $endSelect.find('option').prop('disabled', false);
            return;
        }

        let disable = true;
        $endSelect.find('option').each(function () {
            const $option = $(this);
            const value = $option.val();

            if (!value) {
                return;
            }

            if (disable && value <= startValue) {
                $option.prop('disabled', true);
            } else {
                disable = false;
                $option.prop('disabled', false);
            }
        });

        if ($endSelect.val() && $endSelect.find('option:selected').is(':disabled')) {
            $endSelect.val('');
        }
    }

    $(function () {
        initSelect2();
        syncEndOptions();

        $('.smooth-booking-form-drawer.is-open').each(function () {
            const target = $(this).data('context');
            toggleDrawer(target, true);
        });
    });

    $(document).on('change', '#smooth-booking-appointment-start', syncEndOptions);

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

    $(document).on('click', '.smooth-booking-actions-toggle', function (event) {
        event.preventDefault();
        const $toggle = $(this);
        const $menu = $toggle.closest('.smooth-booking-actions-menu').find('.smooth-booking-actions-list');
        const isOpen = !$menu.attr('hidden');

        closeMenus();

        if (!isOpen) {
            $menu.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
        }
    });

    $(document).on('click', function (event) {
        if ($(event.target).closest('.smooth-booking-actions-menu').length === 0) {
            closeMenus();
        }
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenus();
        }
    });

    $(document).on('submit', '.smooth-booking-delete-form', function (event) {
        const message = settings.confirmDelete || '';
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });

    $(document).on('submit', '.smooth-booking-restore-form', function (event) {
        const message = settings.confirmRestore || '';
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
})(jQuery);
