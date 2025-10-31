(function ($) {
    'use strict';

    const settings = window.SmoothBookingEmployees || {};
    const locationSchedules = settings.locationSchedules || {};
    const strings = settings.strings || {};

    let $breakTemplate = null;

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

    function activateTab($tab) {
        if (!$tab || !$tab.length) {
            return;
        }

        const target = $tab.data('target');
        if (!target) {
            return;
        }

        const $tabs = $('.smooth-booking-form-tab');
        const $panels = $('.smooth-booking-form-panel');
        const $panel = $('#' + target);

        $tabs.removeClass('is-active').attr('aria-selected', 'false');
        $panels.attr('hidden', true).removeClass('is-active');

        $tab.addClass('is-active').attr('aria-selected', 'true');

        if ($panel.length) {
            $panel.removeAttr('hidden').addClass('is-active');
        }
    }

    function focusAdjacentTab($current, forward) {
        const $tabs = $('.smooth-booking-form-tab');
        const index = $tabs.index($current);

        if (index === -1 || !$tabs.length) {
            return;
        }

        let nextIndex = forward ? index + 1 : index - 1;

        if (nextIndex < 0) {
            nextIndex = $tabs.length - 1;
        }

        if (nextIndex >= $tabs.length) {
            nextIndex = 0;
        }

        $tabs.eq(nextIndex).trigger('focus');
    }

    function focusExtremeTab(first) {
        const $tabs = $('.smooth-booking-form-tab');
        if (!$tabs.length) {
            return;
        }

        const index = first ? 0 : $tabs.length - 1;
        $tabs.eq(index).trigger('focus');
    }

    function setServicePriceState($checkbox) {
        const $item = $checkbox.closest('.smooth-booking-service-item');
        if (!$item.length) {
            return;
        }

        const $price = $item.find('.smooth-booking-service-price');
        const isChecked = $checkbox.is(':checked');

        $item.toggleClass('is-selected', isChecked);

        if ($price.length) {
            $price.prop('disabled', !isChecked);
            if (!isChecked) {
                $price.val('');
            }
        }
    }

    function updateGroupCheckbox(categoryId) {
        if (typeof categoryId === 'undefined') {
            return;
        }

        const $group = $('.smooth-booking-services-group[data-category="' + categoryId + '"]');
        if (!$group.length) {
            return;
        }

        const $toggles = $group.find('.smooth-booking-service-toggle');

        if (!$toggles.length) {
            $group.find('.smooth-booking-services-group-toggle').prop('checked', false);
            return;
        }

        const checkedCount = $toggles.filter(':checked').length;
        $group.find('.smooth-booking-services-group-toggle').prop('checked', checkedCount === $toggles.length);
    }

    function toggleServiceGroup(categoryId, isChecked) {
        if (typeof categoryId === 'undefined') {
            return;
        }

        const $group = $('.smooth-booking-services-group[data-category="' + categoryId + '"]');
        if (!$group.length) {
            return;
        }

        $group.find('.smooth-booking-service-toggle').each(function () {
            $(this).prop('checked', isChecked).trigger('change');
        });
    }

    function insertBreakRow($container, dayId, index, values) {
        if (!$breakTemplate || !$breakTemplate.length) {
            return null;
        }

        const $clone = $breakTemplate.clone();
        $clone.removeClass('smooth-booking-schedule-break--template').removeAttr('data-template').attr('hidden', false);

        const $startInput = $clone.find('[data-break-input="start"]');
        const $endInput = $clone.find('[data-break-input="end"]');

        if ($startInput.length) {
            $startInput.addClass('smooth-booking-schedule-break-start')
                .attr('name', 'employee_schedule[' + dayId + '][breaks][' + index + '][start]')
                .prop('disabled', false)
                .val(values && values.start_time ? values.start_time : '');
        }

        if ($endInput.length) {
            $endInput.addClass('smooth-booking-schedule-break-end')
                .attr('name', 'employee_schedule[' + dayId + '][breaks][' + index + '][end]')
                .prop('disabled', false)
                .val(values && values.end_time ? values.end_time : '');
        }

        $container.append($clone);

        return $clone;
    }

    function renumberBreaks($container, dayId) {
        $container.children('.smooth-booking-schedule-break').not('.smooth-booking-schedule-break--template').each(function (index) {
            $(this).find('.smooth-booking-schedule-break-start')
                .attr('name', 'employee_schedule[' + dayId + '][breaks][' + index + '][start]');
            $(this).find('.smooth-booking-schedule-break-end')
                .attr('name', 'employee_schedule[' + dayId + '][breaks][' + index + '][end]');
        });
    }

    function collectBreaks($row) {
        const breaks = [];

        $row.find('.smooth-booking-schedule-break').not('.smooth-booking-schedule-break--template').each(function () {
            const startVal = $(this).find('.smooth-booking-schedule-break-start').val() || '';
            const endVal = $(this).find('.smooth-booking-schedule-break-end').val() || '';

            if (startVal || endVal) {
                breaks.push({
                    start_time: startVal,
                    end_time: endVal
                });
            }
        });

        return breaks;
    }

    function addBreakRow(dayId, values) {
        const $container = $('.smooth-booking-schedule-breaks[data-day="' + dayId + '"]');
        if (!$container.length) {
            return;
        }

        const index = $container.children('.smooth-booking-schedule-break').not('.smooth-booking-schedule-break--template').length;
        insertBreakRow($container, dayId, index, values || { start_time: '', end_time: '' });
        renumberBreaks($container, dayId);
    }

    function getRowSchedule($row) {
        const $off = $row.find('.smooth-booking-schedule-off-toggle');
        const isOff = $off.is(':checked');

        if (isOff) {
            return {
                is_off_day: true,
                start_time: '',
                end_time: '',
                breaks: []
            };
        }

        const start = $row.find('.smooth-booking-schedule-start').val() || '';
        const end = $row.find('.smooth-booking-schedule-end').val() || '';

        return {
            is_off_day: false,
            start_time: start,
            end_time: end,
            breaks: collectBreaks($row)
        };
    }

    function applyScheduleToRow(dayId, definition) {
        const $row = $('.smooth-booking-schedule-row[data-day="' + dayId + '"]');
        if (!$row.length) {
            return;
        }

        const data = definition || {};
        const isOff = !!data.is_off_day;
        const start = data.start_time || '';
        const end = data.end_time || '';
        const breaks = Array.isArray(data.breaks) ? data.breaks : [];

        const $start = $row.find('.smooth-booking-schedule-start');
        const $end = $row.find('.smooth-booking-schedule-end');
        const $off = $row.find('.smooth-booking-schedule-off-toggle');
        const $breakContainer = $row.find('.smooth-booking-schedule-breaks');
        const $addBreak = $row.find('.smooth-booking-schedule-add-break');

        $off.prop('checked', isOff);

        if (isOff) {
            $start.val('').prop('disabled', true);
            $end.val('').prop('disabled', true);
            $addBreak.prop('disabled', true);
            $breakContainer.children('.smooth-booking-schedule-break').not('.smooth-booking-schedule-break--template').remove();
            return;
        }

        $start.prop('disabled', false).val(start);
        $end.prop('disabled', false).val(end);
        $addBreak.prop('disabled', false);

        $breakContainer.children('.smooth-booking-schedule-break').not('.smooth-booking-schedule-break--template').remove();

        breaks.forEach(function (breakDefinition, index) {
            insertBreakRow($breakContainer, dayId, index, breakDefinition);
        });

        renumberBreaks($breakContainer, dayId);
    }

    function refreshScheduleRow($row) {
        const dayId = parseInt($row.data('day'), 10);
        if (Number.isNaN(dayId)) {
            return;
        }

        const definition = getRowSchedule($row);
        applyScheduleToRow(dayId, definition);
    }

    function handleOffToggle($checkbox) {
        const $row = $checkbox.closest('.smooth-booking-schedule-row');
        const dayId = parseInt($row.data('day'), 10);

        if (Number.isNaN(dayId)) {
            return;
        }

        const isChecked = $checkbox.is(':checked');

        if (isChecked) {
            const stored = {
                is_off_day: false,
                start_time: $row.find('.smooth-booking-schedule-start').val() || '',
                end_time: $row.find('.smooth-booking-schedule-end').val() || '',
                breaks: collectBreaks($row)
            };

            $row.data('previousSchedule', stored);
            applyScheduleToRow(dayId, { is_off_day: true, start_time: '', end_time: '', breaks: [] });
        } else {
            const previous = $row.data('previousSchedule') || { start_time: '', end_time: '', breaks: [] };
            applyScheduleToRow(dayId, {
                is_off_day: false,
                start_time: previous.start_time || '',
                end_time: previous.end_time || '',
                breaks: Array.isArray(previous.breaks) ? previous.breaks : []
            });
        }
    }

    function copyScheduleToFollowing(dayId) {
        const $source = $('.smooth-booking-schedule-row[data-day="' + dayId + '"]');
        if (!$source.length) {
            return;
        }

        const definition = getRowSchedule($source);

        $('.smooth-booking-schedule-row').each(function () {
            const currentDay = parseInt($(this).data('day'), 10);

            if (Number.isNaN(currentDay) || currentDay <= dayId) {
                return;
            }

            applyScheduleToRow(currentDay, {
                is_off_day: definition.is_off_day,
                start_time: definition.start_time,
                end_time: definition.end_time,
                breaks: $.extend(true, [], definition.breaks)
            });
        });
    }

    function applyLocationSchedule(locationId) {
        const schedule = locationSchedules[locationId];

        for (let day = 1; day <= 7; day++) {
            const definition = schedule && schedule[day]
                ? {
                    is_off_day: !!schedule[day].is_off_day,
                    start_time: schedule[day].start_time || '',
                    end_time: schedule[day].end_time || '',
                    breaks: Array.isArray(schedule[day].breaks) ? $.extend(true, [], schedule[day].breaks) : []
                }
                : { is_off_day: true, start_time: '', end_time: '', breaks: [] };

            applyScheduleToRow(day, definition);
        }
    }

    function initColorPickers() {
        $('.smooth-booking-color-field').wpColorPicker();
    }

    function initDrawerState() {
        $('.smooth-booking-form-drawer.is-open').each(function () {
            const target = $(this).data('context');
            toggleDrawer(target, true);
        });
    }

    function initTabs() {
        const $active = $('.smooth-booking-form-tab.is-active').first();
        if ($active.length) {
            activateTab($active);
        } else {
            activateTab($('.smooth-booking-form-tab').first());
        }
    }

    function initServices() {
        $('.smooth-booking-service-toggle').each(function () {
            setServicePriceState($(this));
        });

        $('.smooth-booking-services-group').each(function () {
            const categoryId = $(this).data('category');
            if (typeof categoryId !== 'undefined') {
                updateGroupCheckbox(categoryId);
            }
        });
    }

    function initSchedule() {
        $breakTemplate = $('.smooth-booking-schedule-break--template[data-template="break"]').first();

        $('.smooth-booking-schedule-row').each(function () {
            refreshScheduleRow($(this));
        });
    }

    $(function () {
        initColorPickers();
        initDrawerState();
        initTabs();
        initServices();
        initSchedule();
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

    $(document).on('click', '.smooth-booking-form-tab', function (event) {
        event.preventDefault();
        activateTab($(this));
    });

    $(document).on('keydown', '.smooth-booking-form-tab', function (event) {
        switch (event.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                event.preventDefault();
                focusAdjacentTab($(this), true);
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                event.preventDefault();
                focusAdjacentTab($(this), false);
                break;
            case 'Home':
                event.preventDefault();
                focusExtremeTab(true);
                break;
            case 'End':
                event.preventDefault();
                focusExtremeTab(false);
                break;
            default:
                break;
        }
    });

    $(document).on('change', '.smooth-booking-services-group-toggle', function () {
        const categoryId = $(this).data('category');
        toggleServiceGroup(categoryId, $(this).is(':checked'));
    });

    $(document).on('change', '.smooth-booking-service-toggle', function () {
        const $checkbox = $(this);
        setServicePriceState($checkbox);
        updateGroupCheckbox($checkbox.data('category'));
    });

    $(document).on('click', '.smooth-booking-schedule-add-break', function (event) {
        event.preventDefault();

        if ($(this).is(':disabled')) {
            return;
        }

        const dayId = parseInt($(this).attr('data-day'), 10);

        if (Number.isNaN(dayId)) {
            return;
        }

        addBreakRow(dayId);
    });

    $(document).on('click', '.smooth-booking-schedule-remove-break', function (event) {
        event.preventDefault();

        const $break = $(this).closest('.smooth-booking-schedule-break');
        if (!$break.length || $break.hasClass('smooth-booking-schedule-break--template')) {
            return;
        }

        if (strings.removeBreak && !window.confirm(strings.removeBreak)) {
            return;
        }

        const $container = $break.closest('.smooth-booking-schedule-breaks');
        const dayId = parseInt($container.data('day'), 10);

        $break.remove();

        if (!Number.isNaN(dayId)) {
            renumberBreaks($container, dayId);
        }
    });

    $(document).on('change', '.smooth-booking-schedule-off-toggle', function () {
        handleOffToggle($(this));
    });

    $(document).on('click', '.smooth-booking-schedule-copy', function (event) {
        event.preventDefault();

        const dayId = parseInt($(this).attr('data-day'), 10);

        if (Number.isNaN(dayId)) {
            return;
        }

        if (strings.copySchedule && !window.confirm(strings.copySchedule)) {
            return;
        }

        copyScheduleToFollowing(dayId);
    });

    $(document).on('click', '.smooth-booking-schedule-apply', function (event) {
        event.preventDefault();

        const locationId = parseInt($('.smooth-booking-schedule-location').val(), 10);

        if (Number.isNaN(locationId) || locationId <= 0) {
            return;
        }

        if (strings.applyLocation && !window.confirm(strings.applyLocation)) {
            return;
        }

        applyLocationSchedule(locationId);
    });
})(jQuery);
