(function ($) {
    'use strict';

    const settings = window.SmoothBookingServices || {};
    const randomPreferenceKeys = ['most_expensive', 'least_expensive', 'least_occupied_day', 'most_occupied_day'];
    const occupancyPreferenceKeys = ['least_occupied_day', 'most_occupied_day'];

    function closeMenus() {
        $('.smooth-booking-actions-list').attr('hidden', true);
        $('.smooth-booking-actions-toggle').attr('aria-expanded', 'false');
    }

    function getPlaceholderHtml() {
        return settings.placeholderHtml || '';
    }

    function updateAvatar($field, attachment) {
        const $preview = $field.find('.smooth-booking-avatar-preview');
        const $removeButton = $field.find('.smooth-booking-service-avatar-remove');
        const $input = $field.find('input[name="service_profile_image_id"]');

        if (attachment && attachment.id) {
            const url = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            const alt = attachment.alt || attachment.title || '';
            const $img = $('<img />', {
                src: url,
                alt: alt,
                class: 'smooth-booking-avatar-image'
            });

            $preview.html($('<span/>', { 'class': 'smooth-booking-avatar-wrapper' }).append($img));
            $input.val(attachment.id);
            $removeButton.show();
        } else {
            $preview.html(getPlaceholderHtml());
            $input.val('');
            $removeButton.hide();
        }
    }

    function updatePreferenceSections($select) {
        const value = $select.val();
        const $cell = $select.closest('td');
        const $random = $cell.find('.smooth-booking-providers-random');
        const $occupancy = $cell.find('.smooth-booking-providers-occupancy');

        if (randomPreferenceKeys.indexOf(value) !== -1) {
            $random.removeAttr('hidden');
        } else {
            $random.attr('hidden', 'hidden');
        }

        if (occupancyPreferenceKeys.indexOf(value) !== -1) {
            $occupancy.removeAttr('hidden');
        } else {
            $occupancy.attr('hidden', 'hidden');
        }
    }

    function updateFinalStepVisibility($select) {
        const value = $select.val();
        const $input = $select.closest('td').find('.smooth-booking-final-step-input');

        if (value === 'enabled') {
            $input.show();
        } else {
            $input.hide();
        }
    }

    function updateSelectAllState($container) {
        const $checkboxes = $container.find('.smooth-booking-provider-checkbox');
        const $toggleAll = $container.find('.smooth-booking-providers-toggle-all');

        if (!$checkboxes.length) {
            $toggleAll.prop('checked', false);
            return;
        }

        const total = $checkboxes.length;
        const selected = $checkboxes.filter(':checked').length;
        $toggleAll.prop('checked', total > 0 && selected === total);
    }

    $(function () {
        $('.smooth-booking-color-field').wpColorPicker();

        $('.smooth-booking-service-tabs .nav-tab').on('click', function (event) {
            event.preventDefault();
            const $tab = $(this);
            const target = $tab.attr('data-tab');

            $tab.closest('.nav-tab-wrapper').find('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');

            const $panels = $tab.closest('.smooth-booking-service-tabs').find('.smooth-booking-service-tab-panel');
            $panels.removeClass('is-active');
            $panels.filter('#smooth-booking-service-tab-' + target).addClass('is-active');
        });

        $('.smooth-booking-service-providers-preference').each(function () {
            updatePreferenceSections($(this));
        });

        $('.smooth-booking-final-step-toggle').each(function () {
            updateFinalStepVisibility($(this));
        });

        $('.smooth-booking-providers').each(function () {
            updateSelectAllState($(this));
        });
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

    $(document).on('click', '.smooth-booking-service-avatar-select', function (event) {
        event.preventDefault();
        const $field = $(this).closest('.smooth-booking-service-avatar-field');

        const frame = wp.media({
            title: settings.chooseImage || '',
            button: {
                text: settings.useImage || ''
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        frame.on('select', function () {
            const attachment = frame.state().get('selection').first().toJSON();
            updateAvatar($field, attachment);
        });

        frame.open();
    });

    $(document).on('click', '.smooth-booking-service-avatar-remove', function (event) {
        event.preventDefault();
        const $field = $(this).closest('.smooth-booking-service-avatar-field');
        updateAvatar($field, null);
    });

    $(document).on('change', '.smooth-booking-providers-toggle-all', function () {
        const $toggle = $(this);
        const checked = $toggle.is(':checked');
        const $container = $toggle.closest('.smooth-booking-providers');
        $container.find('.smooth-booking-provider-checkbox').prop('checked', checked);
    });

    $(document).on('change', '.smooth-booking-provider-checkbox', function () {
        const $container = $(this).closest('.smooth-booking-providers');
        updateSelectAllState($container);
    });

    $(document).on('change', '.smooth-booking-service-providers-preference', function () {
        updatePreferenceSections($(this));
    });

    $(document).on('change', '.smooth-booking-final-step-toggle', function () {
        updateFinalStepVisibility($(this));
    });
})(jQuery);
