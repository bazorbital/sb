(function (window, document) {
    'use strict';

    /**
     * Invoke callback when DOM is ready.
     *
     * @param {Function} callback Callback to run when ready.
     * @returns {void}
     */
    function ready(callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
        } else {
            callback();
        }
    }

    /**
     * Safely coerce value to array.
     *
     * @param {*} value Potential array.
     * @returns {Array}
     */
    function ensureArray(value) {
        return Array.isArray(value) ? value : [];
    }

    /**
     * Convert value to YYYY-MM-DD string.
     *
     * @param {*} value Date input.
     * @returns {string}
     */
    function toDateString(value) {
        if (!value) {
            return '';
        }

        if (value instanceof Date && typeof value.toISOString === 'function') {
            return value.toISOString().slice(0, 10);
        }

        if (typeof value === 'string' && value.length >= 10) {
            return value.slice(0, 10);
        }

        return '';
    }

    /**
     * Build custom EventCalendar event nodes displaying booking meta.
     *
     * @param {Object} arg EventCalendar argument.
     * @param {Object} i18n Labels.
     * @returns {{domNodes: HTMLElement[]}}
     */
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

            if (props.customerPhone) {
                var phone = document.createElement('div');
                phone.className = 'smooth-booking-calendar-event__contact';
                phone.textContent = (i18n.phoneLabel || 'Phone') + ': ' + props.customerPhone;
                container.appendChild(phone);
            }

            if (props.customerEmail) {
                var email = document.createElement('div');
                email.className = 'smooth-booking-calendar-event__contact';
                email.textContent = (i18n.emailLabel || 'Email') + ': ' + props.customerEmail;
                container.appendChild(email);
            }
        }

        return { domNodes: [container] };
    }

    /**
     * Populate a select element with options.
     *
     * @param {HTMLSelectElement|null} select Select element.
     * @param {Array<{value:string,text:string}>} options Options list.
     * @returns {void}
     */
    function populateSelect(select, options) {
        if (!select) {
            return;
        }

        while (select.firstChild) {
            select.removeChild(select.firstChild);
        }

        options.forEach(function (item) {
            var option = document.createElement('option');
            option.value = item.value;
            option.textContent = item.text;
            if (item.selected) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    }

    /**
     * Retrieve selected option values as integers.
     *
     * @param {HTMLSelectElement|null} select Select element.
     * @returns {number[]}
     */
    function getSelectedIds(select) {
        if (!select) {
            return [];
        }

        var values = [];
        Array.prototype.forEach.call(select.selectedOptions || [], function (option) {
            var id = parseInt(option.value, 10);
            if (!Number.isNaN(id)) {
                values.push(id);
            }
        });

        return values;
    }

    /**
     * Apply service filter to the events collection.
     *
     * @param {Array} events Events payload.
     * @param {Set<number>} serviceFilter Selected service ids.
     * @returns {Array}
     */
    function filterEventsByService(events, serviceFilter) {
        if (!serviceFilter || serviceFilter.size === 0) {
            return events;
        }

        return ensureArray(events).filter(function (event) {
            if (!event || !event.extendedProps) {
                return false;
            }

            if (typeof event.extendedProps.serviceId === 'undefined') {
                return true;
            }

            return serviceFilter.has(parseInt(event.extendedProps.serviceId, 10));
        });
    }

    /**
     * Update empty state visibility.
     *
     * @param {HTMLElement|null} wrapper Calendar wrapper.
     * @param {boolean} hasEvents Whether the day has events.
     * @returns {void}
     */
    function renderEmptyState(wrapper, hasEvents) {
        if (!wrapper) {
            return;
        }

        var empty = wrapper.querySelector('[data-calendar-empty]');
        if (empty) {
            empty.hidden = !!hasEvents;
        }
    }

    ready(function initCalendar() {
        var settings = window.SmoothBookingCalendar || {};
        var data = window.SmoothBookingCalendarData || settings.data || {};
        var i18n = settings.i18n || {};
        var target = document.getElementById('smooth-booking-calendar');

        if (!target || typeof EventCalendar === 'undefined' || typeof EventCalendar.create !== 'function') {
            renderEmptyState(target ? target.closest('.smooth-booking-calendar-board') : null, false);
            return;
        }

        var calendarWrapper = target.closest('.smooth-booking-calendar-board');
        var resourceFilter = document.getElementById('smooth-booking-resource-filter');
        var serviceFilter = document.getElementById('smooth-booking-service-filter');
        var locationSelect = document.getElementById('smooth-booking-calendar-location');
        var dateInput = document.getElementById('smooth-booking-calendar-date');

        var state = {
            selectedDate: data.selectedDate || toDateString(new Date()),
            locationId: data.locationId || null,
            resources: ensureArray(data.resources),
            services: data.services || {},
            slotMinTime: data.slotMinTime || '06:00:00',
            slotMaxTime: data.slotMaxTime || '22:00:00',
            slotDuration: data.slotDuration || '00:30:00',
            resourceFilterIds: new Set(),
            serviceFilterIds: new Set(),
            bootstrapEvents: ensureArray(data.events),
        };

        var config = {
            endpoint: data.endpoint || '',
            nonce: data.nonce || '',
            locale: data.locale || 'en',
            timezone: data.timezone || 'local',
        };

        if (!state.locationId && locationSelect && locationSelect.value) {
            state.locationId = parseInt(locationSelect.value, 10) || null;
        }

        if (dateInput && !dateInput.value) {
            dateInput.value = state.selectedDate;
        }

        populateSelect(resourceFilter, state.resources.map(function (resource) {
            return {
                value: String(resource.id),
                text: resource.title || '',
            };
        }));

        /**
         * Filter resources based on the current selection.
         *
         * @returns {Array}
         */
        function getVisibleResources() {
            if (!state.resourceFilterIds || state.resourceFilterIds.size === 0) {
                return state.resources;
            }

            return state.resources.filter(function (resource) {
                return state.resourceFilterIds.has(parseInt(resource.id, 10));
            });
        }

        /**
         * Update service filter options.
         */
        function refreshServiceFilter() {
            var services = state.services || {};
            var options = Object.keys(services).map(function (key) {
                var service = services[key];
                return {
                    value: String(service.id || key),
                    text: service.name || key,
                    selected: state.serviceFilterIds.has(parseInt(service.id || key, 10)),
                };
            });

            populateSelect(serviceFilter, options);
        }

        /**
         * Sync slot options from the schedule payload.
         *
         * @param {Object} payload API payload.
         */
        function updateSlotOptions(payload) {
            if (!calendarInstance) {
                return;
            }

            if (payload.slotMinTime) {
                state.slotMinTime = payload.slotMinTime;
                calendarInstance.setOption('slotMinTime', payload.slotMinTime);
            }

            if (payload.slotMaxTime) {
                state.slotMaxTime = payload.slotMaxTime;
                calendarInstance.setOption('slotMaxTime', payload.slotMaxTime);
            }

            if (payload.slotDuration) {
                state.slotDuration = payload.slotDuration;
                calendarInstance.setOption('slotDuration', payload.slotDuration);
            }

            if (payload.scrollTime) {
                calendarInstance.setOption('scrollTime', payload.scrollTime);
            }
        }

        /**
         * Fetch schedule for the current date/location.
         */
        function fetchSchedule(fetchInfo, success, failure) {
            var hasBootstrap = Array.isArray(state.bootstrapEvents) && state.bootstrapEvents.length > 0;
            if (hasBootstrap) {
                var initialEvents = filterEventsByService(state.bootstrapEvents, state.serviceFilterIds);
                state.bootstrapEvents = null;
                success(initialEvents);
                renderEmptyState(calendarWrapper, initialEvents.length > 0);
                return;
            }
            state.bootstrapEvents = null;

            if (!config.endpoint) {
                failure(new Error('Missing calendar endpoint.'));
                return;
            }

            var params = new URLSearchParams();
            var requestedDate = toDateString((fetchInfo && fetchInfo.startStr) || state.selectedDate || new Date());
            params.set('date', requestedDate);

            if (state.locationId) {
                params.set('location_id', String(state.locationId));
            }

            if (state.resourceFilterIds.size > 0) {
                state.resourceFilterIds.forEach(function (id) {
                    params.append('resource_ids[]', String(id));
                });
            }

            fetch(config.endpoint + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-WP-Nonce': config.nonce || '',
                },
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Request failed with status ' + response.status);
                    }
                    return response.json();
                })
                .then(function (payload) {
                    state.selectedDate = payload.date || requestedDate;
                    state.resources = ensureArray(payload.resources || []);
                    state.services = payload.services || {};
                    state.slotMinTime = payload.slotMinTime || state.slotMinTime;
                    state.slotMaxTime = payload.slotMaxTime || state.slotMaxTime;
                    state.slotDuration = payload.slotDuration || state.slotDuration;

                    populateSelect(resourceFilter, state.resources.map(function (resource) {
                        return {
                            value: String(resource.id),
                            text: resource.title || '',
                            selected: state.resourceFilterIds.has(parseInt(resource.id, 10)),
                        };
                    }));

                    refreshServiceFilter();

                    if (calendarInstance) {
                        calendarInstance.setOption('resources', getVisibleResources());
                        updateSlotOptions(payload);
                    }

                    if (dateInput && state.selectedDate) {
                        dateInput.value = state.selectedDate;
                    }

                    var filteredEvents = filterEventsByService(payload.events || [], state.serviceFilterIds);
                    renderEmptyState(calendarWrapper, filteredEvents.length > 0);
                    success(filteredEvents);
                })
                .catch(function (error) {
                    if (window.console && typeof window.console.error === 'function') {
                        window.console.error('Calendar schedule failed', error);
                    }
                    if (failure) {
                        failure(error);
                    }
                });
        }

        /**
         * Handle date navigation.
         */
        function onDatesSet(payload) {
            if (!payload || !payload.start) {
                return;
            }
            var iso = toDateString(payload.startStr || payload.start.toISOString());
            if (iso && iso !== state.selectedDate) {
                state.selectedDate = iso;
                if (dateInput) {
                    dateInput.value = iso;
                }
                if (calendarInstance) {
                    calendarInstance.refetchEvents();
                }
            }
        }

        /**
         * Handle location change.
         */
        function onLocationChange(event) {
            var newId = parseInt(event.target.value, 10);
            state.locationId = Number.isNaN(newId) ? null : newId;
            state.resourceFilterIds.clear();
            state.serviceFilterIds.clear();
            populateSelect(resourceFilter, []);
            refreshServiceFilter();
            if (calendarInstance) {
                calendarInstance.refetchEvents();
            }
        }

        /**
         * Handle employee filter change.
         */
        function onResourceChange() {
            state.resourceFilterIds = new Set(getSelectedIds(resourceFilter));
            if (calendarInstance) {
                calendarInstance.setOption('resources', getVisibleResources());
                calendarInstance.refetchEvents();
            }
        }

        /**
         * Handle service filter change.
         */
        function onServiceChange() {
            state.serviceFilterIds = new Set(getSelectedIds(serviceFilter));
            if (calendarInstance) {
                calendarInstance.refetchEvents();
            }
        }

        /**
         * Handle manual date change from the filter.
         */
        function onDateChange(event) {
            var value = toDateString(event.target.value);
            if (!value) {
                return;
            }
            state.selectedDate = value;
            if (calendarInstance) {
                calendarInstance.setOption('date', new Date(value));
                calendarInstance.refetchEvents();
            }
        }

        var calendarInstance = EventCalendar.create(target, {
            view: 'resourceTimeGridDay',
            initialDate: state.selectedDate,
            date: state.selectedDate,
            headerToolbar: {
                start: 'prev,next today',
                center: 'title',
                end: '',
            },
            timeZone: config.timezone,
            resources: getVisibleResources(),
            eventSources: [
                {
                    events: fetchSchedule,
                },
            ],
            nowIndicator: true,
            locale: config.locale,
            slotMinTime: state.slotMinTime,
            slotMaxTime: state.slotMaxTime,
            slotDuration: state.slotDuration,
            resourceAreaHeaderContent: i18n.resourceColumn || 'Employees',
            dayMaxEvents: true,
            selectable: false,
            eventContent: function (arg) {
                return buildEventContent(arg, i18n);
            },
        });

        if (!calendarInstance || typeof calendarInstance.on !== 'function') {
            renderEmptyState(calendarWrapper, false);
            return;
        }

        calendarInstance.on('datesSet', onDatesSet);

        if (locationSelect) {
            locationSelect.addEventListener('change', onLocationChange);
        }

        if (resourceFilter) {
            resourceFilter.addEventListener('change', onResourceChange);
        }

        if (serviceFilter) {
            serviceFilter.addEventListener('change', onServiceChange);
        }

        if (dateInput) {
            dateInput.addEventListener('change', onDateChange);
        }

        renderEmptyState(calendarWrapper, ensureArray(state.bootstrapEvents).length > 0 || data.hasEvents);
    });
})(window, document);
