(function ($) {
    'use strict';

    const settings = window.SmoothBookingEmployees || {};

    function closeMenus() {
        $('.smooth-booking-actions-list').attr('hidden', true);
        $('.smooth-booking-actions-toggle').attr('aria-expanded', 'false');
    }

    function getPlaceholderHtml($field) {
        const fromAttr = $field.attr('data-placeholder');
        return fromAttr || settings.placeholderHtml || '';
    }

    function updateAvatar($field, attachment) {
        const $preview = $field.find('.smooth-booking-avatar-preview');
        const $removeButton = $field.find('.smooth-booking-avatar-remove');
        const $input = $field.find('input[name="employee_profile_image_id"]');

        if (attachment && attachment.id) {
            const imageHtml = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            const altText = attachment.alt || attachment.title || '';
            const imageTag = $('<img />', {
                src: imageHtml,
                alt: altText,
                class: 'smooth-booking-avatar-image'
            });

            $preview.html($('<span/>', { 'class': 'smooth-booking-avatar-wrapper' }).append(imageTag));
            $input.val(attachment.id);
            $removeButton.show();
        } else {
            $preview.html(getPlaceholderHtml($field));
            $input.val('');
            $removeButton.hide();
        }
    }

    $(function () {
        $('.smooth-booking-color-field').wpColorPicker();
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

    $(document).on('click', '.smooth-booking-avatar-select', function (event) {
        event.preventDefault();

        const $field = $(this).closest('.smooth-booking-avatar-field');

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

    $(document).on('click', '.smooth-booking-avatar-remove', function (event) {
        event.preventDefault();

        const $field = $(this).closest('.smooth-booking-avatar-field');
        updateAvatar($field, null);
    });
})(jQuery);
