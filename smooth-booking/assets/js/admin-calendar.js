(function ($, window) {
    'use strict';

    var settings = window.SmoothBookingCalendar || {};

    function initSelect2() {
        if (typeof $.fn.select2 !== 'function') {
            return;
        }

        $('.smooth-booking-select2').each(function () {
            var $select = $(this);
            var placeholder = $select.data('placeholder') || '';

            $select.select2({
                width: 'resolve',
                placeholder: placeholder,
                allowClear: true,
                dropdownParent: $select.closest('.smooth-booking-form-card')
            });
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

        if (Array.isArray(value)) {
            return value.slice();
        }

        return [String(value)];
    }

    function getAllEmployeeIds($select) {
        var ids = [];

        $select.find('option').each(function () {
            var val = $(this).val();
            if (val) {
                ids.push(String(val));
            }
        });

        return ids;
    }

    function setEmployeeSelection($select, ids) {
        if (!$select.length) {
            return;
        }

        $select.val(ids).trigger('change');
    }

    function updateEmployeeButtons() {
        var $select = getEmployeeSelect();

        if (!$select.length) {
            return;
        }

        var selected = getSelectedEmployeeIds($select);
        var allIds = getAllEmployeeIds($select);
        var allActive = selected.length && selected.length === allIds.length;

        $('[data-employee-toggle]').each(function () {
            var $button = $(this);
            var toggleId = String($button.data('employee-toggle'));

            if (toggleId === 'all') {
                $button.toggleClass('is-active', allActive);
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

        var data = settings.data || {};
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
