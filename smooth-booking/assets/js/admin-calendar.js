(function (window, document) {
    'use strict';

    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    function ensureArray(value) {
        return Array.isArray(value) ? value : [];
    }

    function renderEmptyState(wrapper, hasEvents) {
        if (!wrapper) {
            return;
        }

        var empty = wrapper.querySelector('[data-calendar-empty]');

        if (!empty) {
            return;
        }

        empty.hidden = !!hasEvents;
    }

    function buildEventContent(arg, i18n) {
        var container = document.createElement('div');
        container.className = 'smooth-booking-calendar-event';

        var title = document.createElement('div');
        title.className = 'smooth-booking-calendar-event__service';
        title.textContent = arg.event.title || '';
        container.appendChild(title);

        if (arg.event.extendedProps) {
            var props = arg.event.extendedProps;

            if (props.timeRange) {
                var time = document.createElement('div');
                time.className = 'smooth-booking-calendar-event__time';
                time.textContent = props.timeRange;
                container.appendChild(time);
            }

            if (props.customer) {
                var customer = document.createElement('div');
                customer.className = 'smooth-booking-calendar-event__customer';
                customer.textContent = (i18n.customerLabel || 'Customer') + ': ' + props.customer;
                container.appendChild(customer);
            }
        }

        return { domNodes: [container] };
    }

    function createCalendar() {
        var settings = window.SmoothBookingCalendar || {};
        var data = window.SmoothBookingCalendarData || settings.data || {};
        var i18n = settings.i18n || {};
        var target = document.getElementById('smooth-booking-calendar');

        if (!target) {
            return;
        }

        if (typeof EventCalendar === 'undefined' || typeof EventCalendar.create !== 'function') {
            renderEmptyState(target.closest('.smooth-booking-calendar-board'), false);
            return;
        }

        var options = {
            view: 'resourceTimeGridDay',
            initialDate: data.selectedDate || new Date().toISOString().slice(0, 10),
            headerToolbar: {
                start: 'prev,next today',
                center: 'title',
                end: ''
            },
            timeZone: data.timezone || 'local',
            resources: ensureArray(data.resources),
            events: ensureArray(data.events),
            nowIndicator: true,
            locale: data.locale || 'en',
            slotMinTime: data.openTime || '08:00:00',
            slotMaxTime: data.closeTime || '18:00:00',
            scrollTime: data.scrollTime || data.openTime || '08:00:00',
            slotDuration: data.slotDuration || '00:30',
            resourceAreaHeaderContent: i18n.resourceColumn || 'Employees',
            dayMaxEvents: true,
            selectable: false,
            eventContent: function (arg) {
                return buildEventContent(arg, i18n);
            }
        };

        var calendar = EventCalendar.create(target, options);

        var wrapper = target.closest('.smooth-booking-calendar-board');
        renderEmptyState(wrapper, ensureArray(data.events).length > 0);

        if (!calendar || typeof calendar.on !== 'function') {
            return;
        }

        calendar.on('datesSet', function (payload) {
            if (!payload || !payload.start) {
                return;
            }

            var iso = payload.start.toISOString().slice(0, 10);
            var dateInput = document.querySelector('input[name="calendar_date"]');

            if (dateInput && dateInput.value !== iso) {
                dateInput.value = iso;
            }
        });
    }

    ready(createCalendar);
})(window, document);
