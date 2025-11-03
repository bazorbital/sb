(function ($, window) {
    'use strict';

    var settings = window.SmoothBookingCalendar || {};
    var calendarData = window.SmoothBookingCalendarData || (settings.data || {});

    function toArray(value) {
        if (Array.isArray(value)) {
            return value.slice();
        }

        if (typeof value === 'undefined' || value === null || value === '') {
            return [];
        }

        return [String(value)];
    }

    function normaliseArray(values) {
        return values.slice().sort();
    }

    function arraysEqual(a, b) {
        if (a.length !== b.length) {
            return false;
        }

        var first = normaliseArray(a);
        var second = normaliseArray(b);

        for (var i = 0; i < first.length; i++) {
            if (first[i] !== second[i]) {
                return false;
            }
        }

        return true;
    }

    function setSelectValues($select, values) {
        var current = toArray($select.val()).map(String);
        var target = values.map(String);

        if (arraysEqual(current, target)) {
            return;
        }

        $select.val(target).trigger('change.select2');
    }

    function getAllOptionValue($select) {
        var value = $select.data('all-value');

        if (typeof value === 'undefined' || value === null || value === '') {
            return '';
        }

        return String(value);
    }

    function enforceAllOption($select) {
        var allValue = getAllOptionValue($select);

        if (!allValue) {
            return;
        }

        var values = toArray($select.val()).map(String);
        var hasAll = values.indexOf(allValue) !== -1;
        var others = values.filter(function (value) {
            return value !== allValue && value !== '';
        });

        if (hasAll && others.length) {
            setSelectValues($select, others);
            return;
        }

        if (!others.length && !hasAll && $select.find('option[value="' + allValue + '"]').length) {
            setSelectValues($select, [allValue]);
        }
    }

    function initSelect2() {
        if (typeof $.fn.select2 !== 'function') {
            return;
        }

        $('.smooth-booking-select2').each(function () {
            var $select = $(this);
            var placeholder = $select.data('placeholder') || '';

            var dropdownParent = $select.closest('.smooth-booking-form-card');

            if (!dropdownParent.length) {
                dropdownParent = $(document.body);
            }

            $select.select2({
                width: 'resolve',
                placeholder: placeholder,
                allowClear: true,
                dropdownParent: dropdownParent
            });

            $select.on('change', function () {
                enforceAllOption($select);
            });

            enforceAllOption($select);
        });
    }

    function syncEndOptions() {
        var startValue = $('#smooth-booking-appointment-start').val();
        var $endSelect = $('#smooth-booking-appointment-end');

        if (!$endSelect.length || !startValue) {
            $endSelect.find('option').prop('disabled', false);
            return;
        }

        var disable = true;
        $endSelect.find('option').each(function () {
            var $option = $(this);
            var value = $option.val();

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

    function setEndToNextSlot() {
        var $start = $('#smooth-booking-appointment-start');
        var $end = $('#smooth-booking-appointment-end');
        var startValue = $start.val();
        if (!startValue) {
            return;
        }

        var options = $end.find('option').filter(function () {
            var value = $(this).val();
            return value && value > startValue && !$(this).prop('disabled');
        });

        if (options.length) {
            $end.val($(options[0]).val());
        }
    }

    function openModal() {
        $('#smooth-booking-calendar-modal').attr('hidden', false).addClass('is-open');
        $('body').addClass('smooth-booking-modal-open');
        setTimeout(function () {
            $('#smooth-booking-appointment-provider').trigger('focus');
        }, 50);
    }

    function closeModal() {
        $('#smooth-booking-calendar-modal').attr('hidden', true).removeClass('is-open');
        $('body').removeClass('smooth-booking-modal-open');
    }

    function prepareForm(employeeId, slotTime) {
        var $dateInput = $('input[name="calendar_date"]');
        var selectedDate = $dateInput.val();
        $('#smooth-booking-appointment-date').val(selectedDate);

        if (employeeId) {
            $('#smooth-booking-appointment-provider').val(String(employeeId)).trigger('change');
        }

        if (slotTime) {
            $('#smooth-booking-appointment-start').val(slotTime);
            syncEndOptions();
            setEndToNextSlot();
        }
    }

    function bindAppointmentDelete() {
        $(document).on('click', '.smooth-booking-calendar-appointment__delete', function (event) {
            event.stopPropagation();
        });
    }

    function bindModalEvents() {
        $(document).on('click', '[data-calendar-close]', function (event) {
            event.preventDefault();
            closeModal();
        });

        $(document).on('keydown', function (event) {
            if (27 === event.which && $('#smooth-booking-calendar-modal').hasClass('is-open')) {
                closeModal();
            }
        });

        $(document).on('change', '#smooth-booking-appointment-start', function () {
            syncEndOptions();
            setEndToNextSlot();
        });
    }

    function getEmployeeSelect() {
        return $('#smooth-booking-calendar-employees');
    }

    function getSelectedEmployeeIds($select) {
        if (!$select.length) {
            return [];
        }

        var value = $select.val();

        if (!value) {
            return [];
        }

        var values = Array.isArray(value) ? value.slice() : [String(value)];
        var allValue = getAllOptionValue($select);
        var pattern = /^\d+$/;
        var hasAll = allValue && values.indexOf(allValue) !== -1;

        if (hasAll) {
            return getAllEmployeeIds($select);
        }

        return values.filter(function (val) {
            return pattern.test(String(val));
        }).map(String);
    }

    function getAllEmployeeIds($select) {
        var ids = [];
        var allValue = getAllOptionValue($select);
        var pattern = /^\d+$/;

        $select.find('option').each(function () {
            var val = $(this).val();
            if (!val) {
                return;
            }

            if (allValue && String(val) === allValue) {
                return;
            }

            if (pattern.test(String(val))) {
                ids.push(String(val));
            }
        });

        return ids;
    }

    function setEmployeeSelection($select, ids) {
        if (!$select.length) {
            return;
        }

        var allValue = getAllOptionValue($select);
        var allIds = getAllEmployeeIds($select);
        var values = ids.slice().map(String);

        if (!values.length && allValue) {
            setSelectValues($select, [allValue]);
            return;
        }

        if (allValue && allIds.length && values.length === allIds.length) {
            values = [allValue].concat(values);
        }

        setSelectValues($select, values);
    }

    function updateEmployeeButtons() {
        var $select = getEmployeeSelect();

        if (!$select.length) {
            return;
        }

        var selected = getSelectedEmployeeIds($select);
        var allIds = getAllEmployeeIds($select);
        var allActive = allIds.length && selected.length === allIds.length;

        $('[data-employee-toggle]').each(function () {
            var $button = $(this);
            var toggleId = String($button.data('employee-toggle'));

            if (toggleId === 'all') {
                $button.toggleClass('is-active', !!allActive);
            } else {
                $button.toggleClass('is-active', selected.indexOf(toggleId) !== -1);
            }
        });
    }

    function bindEmployeeQuickFilters() {
        var $select = getEmployeeSelect();

        if (!$select.length) {
            return;
        }

        updateEmployeeButtons();

        $(document).on('change', '#smooth-booking-calendar-employees', function () {
            updateEmployeeButtons();
        });

        $(document).on('click', '[data-employee-toggle]', function (event) {
            var $button = $(this);
            var target = String($button.data('employee-toggle'));
            var allIds = getAllEmployeeIds($select);
            var selected = getSelectedEmployeeIds($select);

            if (target === 'all') {
                setEmployeeSelection($select, allIds);
                event.preventDefault();
                return;
            }

            if (selected.indexOf(target) === -1) {
                selected.push(target);
            } else {
                selected = selected.filter(function (id) {
                    return id !== target;
                });
            }

            setEmployeeSelection($select, selected);
            event.preventDefault();
        });
    }

    function initEventCalendar() {
        if (typeof window.EventCalendar !== 'function') {
            return;
        }

        var container = document.getElementById('smooth-booking-calendar-view');
        if (!container) {
            return;
        }

        var data = calendarData || {};
        var slots = Array.isArray(data.slots) ? data.slots : [];
        var resources = Array.isArray(data.resources) ? data.resources : [];
        var events = Array.isArray(data.events) ? data.events : [];
        var labels = data.labels || {};

        window.SmoothBookingCalendarInstance = new window.EventCalendar(container, {
            slots: slots,
            resources: resources,
            events: events,
            labels: labels,
            onTimeSlotClick: function (info) {
                prepareForm(info.resourceId, info.slot);
                openModal();
            },
            onEventClick: function (info) {
                if (!info || !info.event) {
                    return;
                }

                if (info.originalEvent && info.originalEvent.target) {
                    var actionable = info.originalEvent.target.closest('a, button, form');
                    if (actionable) {
                        return;
                    }
                }

                if (info.event.editUrl) {
                    window.location.href = info.event.editUrl;
                }
            }
        });
    }

    function initVanillaCalendar() {
        if (typeof window.VanillaCalendar !== 'function') {
            return;
        }

        var picker = document.getElementById('smooth-booking-calendar-picker');
        if (!picker) {
            return;
        }

        var input = document.querySelector('[data-calendar-input]');
        var initialDate = picker.getAttribute('data-initial-date') || (input ? input.value : null);

        var calendar = new window.VanillaCalendar(picker, {
            selectedDate: initialDate ? new Date(initialDate) : new Date(),
            initialDate: initialDate,
            onSelect: function (dateString) {
                if (input) {
                    input.value = dateString;
                }
                Array.prototype.forEach.call(picker.querySelectorAll('.vanilla-calendar__day'), function (dayButton) {
                    dayButton.classList.toggle('is-selected', dayButton.dataset.date === dateString);
                });
            }
        });

        calendar.init();
    }

    $(function () {
        initSelect2();
        syncEndOptions();
        bindAppointmentDelete();
        bindModalEvents();
        initVanillaCalendar();
        bindEmployeeQuickFilters();
        initEventCalendar();
    });
})(jQuery, window);
