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

    function normalizeDate(value) {
        if (!value) {
            return '';
        }

        if (value instanceof Date && typeof value.toISOString === 'function') {
            return value.toISOString().slice(0, 10);
        }

        return String(value).slice(0, 10);
    }

    function hasEventsForDate(events, dateStr) {
        var normalized = normalizeDate(dateStr);

        if (!normalized) {
            return ensureArray(events).length > 0;
        }

        return ensureArray(events).some(function (event) {
            if (!event || !event.start) {
                return false;
            }

            return normalizeDate(event.start) === normalized;
        });
    }

    function isWithinRange(dateStr, start, end) {
        var value = normalizeDate(dateStr);

        if (!value) {
            return false;
        }

        if (start && value < normalizeDate(start)) {
            return false;
        }

        if (end && value > normalizeDate(end)) {
            return false;
        }

        return true;
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

            if (props.customerEmail) {
                var email = document.createElement('div');
                email.className = 'smooth-booking-calendar-event__contact';
                email.textContent = (i18n.emailLabel || 'Email') + ': ' + props.customerEmail;
                container.appendChild(email);
            }

            if (props.customerPhone) {
                var phone = document.createElement('div');
                phone.className = 'smooth-booking-calendar-event__contact';
                phone.textContent = (i18n.phoneLabel || 'Phone') + ': ' + props.customerPhone;
                container.appendChild(phone);
            }
        }

        return { domNodes: [container] };
    }

    function submitCalendarFilters() {
        var form = document.querySelector('.smooth-booking-calendar-filters');

        if (form) {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
            return;
        }

        var params = new URLSearchParams(window.location.search || '');
        var dateInput = document.querySelector('input[name="calendar_date"]');
        var locationField = document.querySelector('select[name="location_id"]');

        if (dateInput && dateInput.value) {
            params.set('calendar_date', dateInput.value);
        }

        if (locationField && locationField.value) {
            params.set('location_id', locationField.value);
        }

        var query = params.toString();
        window.location.search = query ? '?' + query : '';
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

        var events = ensureArray(data.events);

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
            events: events,
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
        renderEmptyState(wrapper, data.hasSelectedDayEvents || hasEventsForDate(events, data.selectedDate));

        if (!calendar || typeof calendar.on !== 'function') {
            return;
        }

        var initialised = false;

        calendar.on('datesSet', function (payload) {
            if (!payload || !payload.start) {
                return;
            }

            var startStr = payload.startStr || payload.start.toISOString();
            var iso = startStr.slice(0, 10);
            var dateInput = document.querySelector('input[name="calendar_date"]');

            if (dateInput && dateInput.value !== iso) {
                dateInput.value = iso;
            }

            if (!initialised) {
                initialised = true;
                return;
            }

            if (iso === data.selectedDate) {
                return;
            }

            if (isWithinRange(iso, data.rangeStart, data.rangeEnd)) {
                data.selectedDate = iso;
                data.hasSelectedDayEvents = hasEventsForDate(events, iso);
                renderEmptyState(wrapper, data.hasSelectedDayEvents);
                return;
            }

            data.selectedDate = iso;
            submitCalendarFilters();
        });
    }

    ready(createCalendar);
})(window, document);
