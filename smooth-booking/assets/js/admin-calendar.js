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

    function bindSlots() {
        $(document).on('click', '.smooth-booking-calendar-slot', function () {
            var employeeId = $(this).data('employee');
            var slotTime = $(this).data('slot');
            prepareForm(employeeId, slotTime);
            openModal();
        });

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
        bindSlots();
        bindModalEvents();
        initVanillaCalendar();
    });
})(jQuery, window);
