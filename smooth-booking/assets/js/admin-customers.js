(function ($) {
    'use strict';

    const settings = window.SmoothBookingCustomers || {};

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

    function getPlaceholderHtml($field) {
        const fromAttr = $field.attr('data-placeholder');
        return fromAttr || settings.placeholderHtml || '';
    }

    function updateAvatar($field, attachment) {
        const $preview = $field.find('.smooth-booking-avatar-preview');
        const $removeButton = $field.find('.smooth-booking-avatar-remove');
        const $input = $field.find('input[name="customer_profile_image_id"]');

        if (attachment && attachment.id) {
            const imageUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
            const altText = attachment.alt || attachment.title || '';
            const imageTag = $('<img />', {
                src: imageUrl,
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

    function toggleExistingUserField(action) {
        const $field = $('.smooth-booking-existing-user-field');
        if ('assign' === action) {
            $field.show();
        } else {
            $field.hide();
            $field.find('select').val('0');
        }
    }

    $(function () {
        $('.smooth-booking-form-drawer.is-open').each(function () {
            const target = $(this).data('context');
            toggleDrawer(target, true);
        });

        const initialAction = $('#smooth-booking-customer-user-action').val();
        if (initialAction) {
            toggleExistingUserField(initialAction);
        }
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

    $(document).on('change', '#smooth-booking-customer-user-action', function () {
        toggleExistingUserField($(this).val());
    });
})(jQuery);
