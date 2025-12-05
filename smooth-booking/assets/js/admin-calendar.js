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
     * Format a Date into HH:MM 24h time.
     *
     * @param {Date} date Date instance.
     * @returns {string}
     */
    function formatTime(date) {
        var hours = String(date.getHours()).padStart(2, '0');
        var minutes = String(date.getMinutes()).padStart(2, '0');
        return hours + ':' + minutes;
    }

    /**
     * Format a Date to HH:MM using an optional IANA timezone.
     *
     * @param {Date} date Date instance.
     * @param {string} [timeZone] IANA timezone name or 'local'.
     * @returns {string}
     */
    function formatTimeWithZone(date, timeZone) {
        if (!date || !(date instanceof Date)) {
            return '';
        }

        if (!timeZone || timeZone === 'local') {
            return formatTime(date);
        }

        try {
            var formatter = new Intl.DateTimeFormat('en-GB', {
                timeZone: timeZone,
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            });

            var parts = formatter.formatToParts(date);
            var hours = '00';
            var minutes = '00';

            parts.forEach(function (part) {
                if (part.type === 'hour') {
                    hours = part.value.padStart(2, '0');
                } else if (part.type === 'minute') {
                    minutes = part.value.padStart(2, '0');
                }
            });

            return hours + ':' + minutes;
        } catch (error) {
            return formatTime(date);
        }
    }

    /**
     * Normalise time-like values to HH:MM.
     *
     * @param {*} value Time candidate.
     * @returns {string}
     */
    function normalizeTime(value, timeZone) {
        if (!value) {
            return '';
        }

        if (value instanceof Date) {
            return formatTimeWithZone(value, timeZone);
        }

        if (typeof value === 'string') {
            if (value.indexOf('T') !== -1 || value.indexOf('Z') !== -1) {
                var parsed = new Date(value);
                if (!Number.isNaN(parsed.getTime())) {
                    return formatTimeWithZone(parsed, timeZone);
                }
            }

            var parts = value.split(':');
            if (parts.length >= 2) {
                var hours = String(parseInt(parts[0], 10)).padStart(2, '0');
                var minutes = String(parseInt(parts[1], 10)).padStart(2, '0');
                return hours + ':' + minutes;
            }
        }

        return '';
    }

    /**
     * Add minutes to a HH:MM time string.
     *
     * @param {string} time Time string (HH:MM).
     * @param {number} minutes Minutes to add.
     * @returns {string}
     */
    function addMinutesToTime(time, minutes) {
        if (!time) {
            return '';
        }

        var parts = time.split(':');
        var hours = parseInt(parts[0], 10) || 0;
        var mins = parseInt(parts[1], 10) || 0;
        var date = new Date();
        date.setHours(hours);
        date.setMinutes(mins + (minutes || 0));
        return formatTime(date);
    }

    /**
     * Convert HH:MM[:SS] duration to minutes.
     *
     * @param {string} value Duration string.
     * @returns {number}
     */
    function durationToMinutes(value) {
        if (typeof value !== 'string' || !value) {
            return 30;
        }

        var parts = value.split(':');
        var hours = parseInt(parts[0], 10) || 0;
        var minutes = parseInt(parts[1], 10) || 0;
        return (hours * 60) + minutes;
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
     * Enhance a native select with Select2 when available.
     *
     * @param {HTMLSelectElement|null} select Target select element.
     * @returns {void}
     */
    function enhanceSelect(select) {
        if (!select || !window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.select2 !== 'function') {
            return;
        }

        var $select = window.jQuery(select);
        var dropdownParent = null;

        if (typeof select.closest === 'function') {
            dropdownParent = select.closest('.smooth-booking-calendar-dialog') || select.closest('dialog');
        }

        var dataset = select.dataset || {};
        var select2Options = {
            width: '100%',
            dropdownAutoWidth: false,
            placeholder: dataset.placeholder || '',
            closeOnSelect: dataset.closeOnSelect ? dataset.closeOnSelect === 'true' : !select.multiple,
            allowClear: dataset.allowClear === 'true' || !select.multiple,
            selectionCssClass: 'smooth-booking-calendar-select2',
            dropdownCssClass: 'smooth-booking-calendar-select2__dropdown',
        };

        if (dropdownParent) {
            select2Options.dropdownParent = window.jQuery(dropdownParent);
        }

        if ($select.hasClass('select2-hidden-accessible')) {
            $select.select2('destroy');
        }

        $select.select2(select2Options);

        if ($select.attr('aria-hidden')) {
            $select.attr('aria-hidden', 'false');
        }
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
        var bookingDialog = document.getElementById('smooth-booking-calendar-dialog');
        var bookingForm = document.getElementById('smooth-booking-calendar-booking-form');
        var bookingResource = document.getElementById('smooth-booking-calendar-booking-resource');
        var bookingService = document.getElementById('smooth-booking-calendar-booking-service');
        var bookingDateInput = document.getElementById('smooth-booking-calendar-booking-date-input');
        var bookingStart = document.getElementById('smooth-booking-calendar-booking-start');
        var bookingEnd = document.getElementById('smooth-booking-calendar-booking-end');
        var bookingCustomer = document.getElementById('smooth-booking-calendar-booking-customer');
        var bookingStatus = document.getElementById('smooth-booking-calendar-booking-status');
        var bookingPayment = document.getElementById('smooth-booking-calendar-booking-payment');
        var bookingCustomerEmail = document.getElementById('smooth-booking-calendar-booking-customer-email');
        var bookingCustomerPhone = document.getElementById('smooth-booking-calendar-booking-customer-phone');
        var bookingInternalNote = document.getElementById('smooth-booking-calendar-booking-internal-note');
        var bookingNote = document.getElementById('smooth-booking-calendar-booking-note');
        var bookingNotify = document.getElementById('smooth-booking-calendar-booking-notify');
        var bookingDateLabel = document.getElementById('smooth-booking-calendar-booking-date');
        var bookingResourceLabel = document.getElementById('smooth-booking-calendar-booking-resource-label');
        var bookingError = document.getElementById('smooth-booking-calendar-booking-error');
        var bookingCancel = document.getElementById('smooth-booking-calendar-booking-cancel');
        var bookingCancelAlt = document.getElementById('smooth-booking-calendar-booking-cancel-alt');

        var initialSlotDuration = data.slotDuration || '00:30:00';

        var state = {
            selectedDate: data.selectedDate || toDateString(new Date()),
            locationId: data.locationId || null,
            resources: ensureArray(data.resources),
            services: data.services || {},
            slotMinTime: data.slotMinTime || '06:00:00',
            slotMaxTime: data.slotMaxTime || '22:00:00',
            slotDuration: initialSlotDuration,
            defaultDurationMinutes: durationToMinutes(initialSlotDuration),
            resourceFilterIds: new Set(),
            serviceFilterIds: new Set(),
            bootstrapEvents: ensureArray(data.events),
            customers: ensureArray(data.customers),
        };

        var config = {
            endpoint: data.endpoint || '',
            nonce: data.nonce || '',
            locale: data.locale || 'en',
            timezone: data.timezone || 'local',
            appointmentsEndpoint: data.appointmentsEndpoint || '',
            customersEndpoint: data.customersEndpoint || '',
        };

        var viewOptions = {
            resourceTimelineDay: {
                slotMinTime: state.slotMinTime,
                slotMaxTime: state.slotMaxTime,
                slotDuration: state.slotDuration,
                resources: ensureArray(state.resources),
            },
        };

        var bookingDefaults = {
            mode: 'manual',
            date: state.selectedDate,
            resourceId: null,
            serviceId: null,
            startTime: '',
            endTime: '',
            customerId: null,
            status: 'pending',
            paymentStatus: '',
            customerEmail: '',
            customerPhone: '',
            notes: '',
            internalNote: '',
            sendNotifications: false,
        };

        var bookingContext = Object.assign({}, bookingDefaults);
        var scheduleAbortController = null;
        var customersAbortController = null;

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
        enhanceSelect(resourceFilter);

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
            enhanceSelect(serviceFilter);
        }

        refreshServiceFilter();

        /**
         * Retrieve a resource by id.
         *
         * @param {number|string|null} id Resource identifier.
         * @returns {Object|null}
         */
        function findResourceById(id) {
            if (!id) {
                return null;
            }

            var numericId = parseInt(id, 10);
            return ensureArray(state.resources).find(function (resource) {
                return parseInt(resource.id, 10) === numericId;
            }) || null;
        }

        /**
         * Derive duration from selected service.
         *
         * @param {string|number|null} serviceId Service identifier.
         * @returns {number}
         */
        function getServiceDurationMinutes(serviceId) {
            if (!serviceId || !state.services) {
                return state.defaultDurationMinutes;
            }

            var key = typeof serviceId === 'string' ? serviceId : String(serviceId);
            var template = state.services[key] || state.services[parseInt(serviceId, 10)];

            if (template && typeof template.durationMinutes !== 'undefined') {
                var minutes = parseInt(template.durationMinutes, 10);
                if (!Number.isNaN(minutes) && minutes > 0) {
                    return minutes;
                }
            }

            return state.defaultDurationMinutes;
        }

        /**
         * Populate booking resource selector.
         *
         * @param {number|null} selectedId Selected resource id.
         */
        function populateBookingResources(selectedId) {
            if (!bookingResource) {
                return;
            }

            var resources = getVisibleResources();
            var options = resources.map(function (resource) {
                return {
                    value: String(resource.id),
                    text: resource.title || '',
                    selected: selectedId ? parseInt(resource.id, 10) === parseInt(selectedId, 10) : false,
                };
            });

            populateSelect(bookingResource, options);
            enhanceSelect(bookingResource);
        }

        /**
         * Populate booking service selector.
         *
         * @param {number|null} selectedId Selected service id.
         */
        function populateBookingServices(selectedId) {
            if (!bookingService) {
                return;
            }

            var services = state.services || {};
            var options = Object.keys(services).map(function (key) {
                var service = services[key];
                return {
                    value: String(service.id || key),
                    text: service.name || key,
                    selected: selectedId ? parseInt(service.id || key, 10) === parseInt(selectedId, 10) : false,
                };
            });

            populateSelect(bookingService, options);
            enhanceSelect(bookingService);
        }

        /**
         * Populate booking customer selector.
         *
         * @param {number|null} selectedId Selected customer id.
         */
        function populateBookingCustomers(selectedId) {
            if (!bookingCustomer) {
                return;
            }

            var customers = ensureArray(state.customers).map(function (customer) {
                if (!customer) {
                    return null;
                }

                var labelParts = [];
                if (customer.name) {
                    labelParts.push(customer.name);
                } else if (customer.first_name || customer.last_name) {
                    labelParts.push([customer.first_name || '', customer.last_name || ''].join(' ').trim());
                }

                if (labelParts.length === 0 && customer.email) {
                    labelParts.push(customer.email);
                }

                if (labelParts.length === 0) {
                    labelParts.push('Customer #' + (customer.id || ''));
                }

                return {
                    value: String(customer.id || ''),
                    text: labelParts.join(' '),
                    selected: selectedId ? parseInt(customer.id, 10) === parseInt(selectedId, 10) : false,
                };
            }).filter(function (item) { return !!item; });

            customers.unshift({
                value: '',
                text: i18n.selectCustomer || 'Select customer',
                selected: !selectedId,
            });

            populateSelect(bookingCustomer, customers);
            enhanceSelect(bookingCustomer);
        }

        /**
         * Fetch customers from the REST API.
         *
         * @param {number|null} selectedId Selected customer id.
         */
        function fetchCustomers(selectedId) {
            if (!config.customersEndpoint || !bookingCustomer) {
                populateBookingCustomers(selectedId || null);
                return;
            }

            if (customersAbortController && typeof customersAbortController.abort === 'function') {
                customersAbortController.abort();
            }

            customersAbortController = typeof AbortController === 'function' ? new AbortController() : null;

            fetch(config.customersEndpoint + '?per_page=100', {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-WP-Nonce': config.nonce || '',
                },
                signal: customersAbortController && customersAbortController.signal ? customersAbortController.signal : undefined,
            })
                .then(function (response) { return response.json(); })
                .then(function (body) {
                    state.customers = ensureArray(body && (body.customers || body));
                    populateBookingCustomers(selectedId || null);
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    populateBookingCustomers(selectedId || null);
                });
        }

        /**
         * Display or clear booking error text.
         *
         * @param {string} message Error message.
         */
        function setBookingError(message) {
            if (!bookingError) {
                return;
            }

            bookingError.textContent = message || '';
            bookingError.hidden = !message;
        }

        /**
         * Update the end time when service or start changes.
         */
        function syncBookingEndTime() {
            if (!bookingStart || !bookingEnd) {
                return;
            }

            var startValue = normalizeTime(bookingStart.value || bookingContext.startTime, config.timezone);
            var duration = getServiceDurationMinutes(bookingService ? bookingService.value : null);

            if (!startValue || Number.isNaN(duration)) {
                return;
            }

            var nextEnd = addMinutesToTime(startValue, duration);
            bookingEnd.value = nextEnd;
            bookingContext.endTime = nextEnd;
        }

        /**
         * Update the start time when the end time changes to preserve duration.
         */
        function syncBookingStartTime() {
            if (!bookingStart || !bookingEnd) {
                return;
            }

            var endValue = normalizeTime(bookingEnd.value || bookingContext.endTime, config.timezone);
            var duration = getServiceDurationMinutes(bookingService ? bookingService.value : null);

            if (!endValue || Number.isNaN(duration)) {
                return;
            }

            var parts = endValue.split(':');
            var hours = parseInt(parts[0], 10) || 0;
            var minutes = parseInt(parts[1], 10) || 0;
            var date = new Date();
            date.setHours(hours);
            date.setMinutes(minutes - duration);

            var nextStart = formatTime(date);
            bookingStart.value = nextStart;
            bookingContext.startTime = nextStart;
        }

        /**
         * Open the booking dialog with the provided context.
         *
         * @param {Object} context Booking context.
         */
        function openBookingDialog(context) {
            if (!bookingDialog || !bookingForm) {
                return;
            }

            var baseDefaults = Object.assign({}, bookingDefaults, { date: state.selectedDate });

            bookingContext = Object.assign({}, baseDefaults, bookingContext, {
                mode: context && context.mode ? context.mode : 'manual',
                date: context && context.date ? toDateString(context.date) || state.selectedDate : state.selectedDate,
                resourceId: context && context.resourceId ? parseInt(context.resourceId, 10) : bookingContext.resourceId,
                serviceId: context && context.serviceId ? parseInt(context.serviceId, 10) : bookingContext.serviceId,
                startTime: normalizeTime((context && context.startTime) || bookingContext.startTime || state.slotMinTime, config.timezone),
                endTime: normalizeTime((context && context.endTime) || bookingContext.endTime || '', config.timezone),
                customerId: context && context.customerId ? parseInt(context.customerId, 10) : bookingContext.customerId,
                status: context && context.status ? context.status : bookingContext.status || 'pending',
                paymentStatus: context && context.paymentStatus ? context.paymentStatus : bookingContext.paymentStatus || '',
                customerEmail: context && context.customerEmail ? context.customerEmail : bookingContext.customerEmail,
                customerPhone: context && context.customerPhone ? context.customerPhone : bookingContext.customerPhone,
                notes: context && context.notes ? context.notes : bookingContext.notes,
                internalNote: context && context.internalNote ? context.internalNote : bookingContext.internalNote,
                sendNotifications: context && typeof context.sendNotifications !== 'undefined'
                    ? !!context.sendNotifications
                    : bookingContext.sendNotifications,
            });

            if (!bookingContext.resourceId && getVisibleResources().length > 0) {
                bookingContext.resourceId = parseInt(getVisibleResources()[0].id, 10);
            }

            bookingContext.date = bookingContext.date || state.selectedDate;

            fetchCustomers(bookingContext.customerId || null);

            populateBookingResources(bookingContext.resourceId);
            populateBookingServices(bookingContext.serviceId);

            if (bookingCustomer) {
                populateBookingCustomers(bookingContext.customerId);
                bookingCustomer.value = bookingContext.customerId ? String(bookingContext.customerId) : '';
            }

            if (bookingDateLabel) {
                bookingDateLabel.textContent = bookingContext.date;
            }

            if (bookingDateInput) {
                bookingDateInput.value = bookingContext.date;
            }

            var selectedResource = bookingContext.resourceId ? findResourceById(bookingContext.resourceId) : null;
            if (bookingResourceLabel) {
                bookingResourceLabel.textContent = selectedResource ? (selectedResource.title || '') : '';
            }

            if (bookingStart) {
                bookingStart.value = bookingContext.startTime || normalizeTime(state.slotMinTime);
                bookingContext.startTime = bookingStart.value;
            }

            if (bookingEnd) {
                var nextEnd = bookingContext.endTime;
                if (!nextEnd) {
                    var serviceDuration = getServiceDurationMinutes(bookingContext.serviceId || (bookingService ? bookingService.value : null));
                    nextEnd = addMinutesToTime(bookingContext.startTime || normalizeTime(state.slotMinTime), serviceDuration);
                }
                bookingEnd.value = nextEnd;
                bookingContext.endTime = nextEnd;
            }

            if (bookingStatus) {
                bookingStatus.value = bookingContext.status || 'pending';
            }

            if (bookingPayment) {
                bookingPayment.value = typeof bookingContext.paymentStatus === 'string' ? bookingContext.paymentStatus : '';
            }

            if (bookingCustomerEmail) {
                bookingCustomerEmail.value = bookingContext.customerEmail || '';
            }

            if (bookingCustomerPhone) {
                bookingCustomerPhone.value = bookingContext.customerPhone || '';
            }

            if (bookingInternalNote) {
                bookingInternalNote.value = bookingContext.internalNote || '';
            }

            if (bookingNote) {
                bookingNote.value = bookingContext.notes || '';
            }

            if (bookingNotify) {
                bookingNotify.checked = !!bookingContext.sendNotifications;
            }

            setBookingError('');

            bookingDialog.removeAttribute('hidden');
            bookingDialog.hidden = false;

            if (typeof bookingDialog.showModal === 'function') {
                bookingDialog.showModal();
            } else {
                bookingDialog.setAttribute('open', 'open');
            }
        }

        /**
         * Close the booking dialog.
         */
        function closeBookingDialog() {
            if (!bookingDialog) {
                return;
            }

            if (typeof bookingDialog.close === 'function') {
                bookingDialog.close();
            }

            bookingDialog.removeAttribute('open');
            bookingDialog.setAttribute('hidden', 'hidden');
            bookingDialog.hidden = true;

            if (bookingForm && typeof bookingForm.reset === 'function') {
                bookingForm.reset();
            }
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
                viewOptions.resourceTimelineDay.slotMinTime = payload.slotMinTime;
            }

            if (payload.slotMaxTime) {
                state.slotMaxTime = payload.slotMaxTime;
                calendarInstance.setOption('slotMaxTime', payload.slotMaxTime);
                viewOptions.resourceTimelineDay.slotMaxTime = payload.slotMaxTime;
            }

            if (payload.slotDuration) {
                state.slotDuration = payload.slotDuration;
                state.defaultDurationMinutes = durationToMinutes(payload.slotDuration);
                calendarInstance.setOption('slotDuration', payload.slotDuration);
                viewOptions.resourceTimelineDay.slotDuration = payload.slotDuration;
            }

            if (payload.scrollTime) {
                calendarInstance.setOption('scrollTime', payload.scrollTime);
            }

            calendarInstance.setOption('views', viewOptions);
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

            if (scheduleAbortController && typeof scheduleAbortController.abort === 'function') {
                scheduleAbortController.abort();
            }

            scheduleAbortController = typeof AbortController === 'function' ? new AbortController() : null;

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
                signal: scheduleAbortController && scheduleAbortController.signal ? scheduleAbortController.signal : undefined,
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
                    state.defaultDurationMinutes = durationToMinutes(state.slotDuration);

                    populateSelect(resourceFilter, state.resources.map(function (resource) {
                        return {
                            value: String(resource.id),
                            text: resource.title || '',
                            selected: state.resourceFilterIds.has(parseInt(resource.id, 10)),
                        };
                    }));
                    enhanceSelect(resourceFilter);

                    refreshServiceFilter();

                    if (calendarInstance) {
                        calendarInstance.setOption('resources', getVisibleResources());
                        viewOptions.resourceTimelineDay.resources = getVisibleResources();
                        calendarInstance.setOption('views', viewOptions);
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
                    if (error && error.name === 'AbortError') {
                        return;
                    }
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
            enhanceSelect(resourceFilter);
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
                viewOptions.resourceTimelineDay.resources = getVisibleResources();
                calendarInstance.setOption('resources', getVisibleResources());
                calendarInstance.setOption('views', viewOptions);
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

        /**
         * Handle booking form submit via REST API.
         */
        function onBookingSubmit(event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (!config.appointmentsEndpoint) {
                setBookingError(i18n.bookingEndpointMissing || 'Booking endpoint is unavailable.');
                return;
            }

            if (bookingStart) {
                bookingContext.startTime = normalizeTime(bookingStart.value || bookingContext.startTime, config.timezone);
                bookingStart.value = bookingContext.startTime;
            }

            if (bookingEnd) {
                bookingContext.endTime = normalizeTime(bookingEnd.value || bookingContext.endTime, config.timezone);
            }

            syncBookingEndTime();

            var providerId = bookingResource ? parseInt(bookingResource.value, 10) : 0;
            var serviceId = bookingService ? parseInt(bookingService.value, 10) : 0;
            var customerId = bookingCustomer ? parseInt(bookingCustomer.value, 10) : 0;
            var startValue = bookingStart ? bookingStart.value : '';
            var endValue = bookingEnd ? bookingEnd.value : '';
            var dateValue = bookingDateInput ? toDateString(bookingDateInput.value) : (bookingContext.date || state.selectedDate);

            bookingContext.date = dateValue || bookingContext.date;
            bookingContext.customerId = customerId || null;
            bookingContext.status = bookingStatus ? bookingStatus.value : bookingContext.status;
            bookingContext.paymentStatus = bookingPayment ? bookingPayment.value : bookingContext.paymentStatus;
            bookingContext.customerEmail = bookingCustomerEmail ? bookingCustomerEmail.value : bookingContext.customerEmail;
            bookingContext.customerPhone = bookingCustomerPhone ? bookingCustomerPhone.value : bookingContext.customerPhone;
            bookingContext.internalNote = bookingInternalNote ? bookingInternalNote.value : bookingContext.internalNote;
            bookingContext.notes = bookingNote ? bookingNote.value : bookingContext.notes;
            bookingContext.sendNotifications = bookingNotify ? !!bookingNotify.checked : bookingContext.sendNotifications;

            if (!providerId || !serviceId || !dateValue || !startValue || !endValue) {
                setBookingError(i18n.bookingValidation || 'Please complete all required fields.');
                return;
            }

            setBookingError('');

            var payload = {
                provider_id: providerId,
                service_id: serviceId,
                appointment_date: dateValue,
                appointment_start: startValue,
                appointment_end: endValue,
                notes: bookingContext.notes || '',
                internal_note: bookingContext.internalNote || '',
                customer_id: customerId,
                status: bookingContext.status || 'pending',
                payment_status: bookingContext.paymentStatus || '',
                send_notifications: bookingContext.sendNotifications,
                customer_email: bookingContext.customerEmail || '',
                customer_phone: bookingContext.customerPhone || '',
            };

            fetch(config.appointmentsEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-WP-Nonce': config.nonce || '',
                },
                body: JSON.stringify(payload),
            })
                .then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (body) {
                            var message = body && body.message ? body.message : response.statusText;
                            throw new Error(message || 'Request failed');
                        });
                    }

                    return response.json();
                })
                .then(function () {
                    closeBookingDialog();
                    if (calendarInstance) {
                        calendarInstance.refetchEvents();
                    }
                })
                .catch(function (error) {
                    setBookingError(error && error.message ? error.message : (i18n.bookingSaveError || 'Unable to save appointment.'));
                });
        }

        /**
         * Open modal from a calendar selection.
         */
        function onCalendarSelect(selectionInfo) {
            var resourceId = selectionInfo && selectionInfo.resource ? selectionInfo.resource.id : null;
            if (!resourceId && selectionInfo && selectionInfo.resourceId) {
                resourceId = selectionInfo.resourceId;
            }

            bookingContext.customerId = null;
            bookingContext.status = 'pending';
            bookingContext.paymentStatus = '';
            bookingContext.customerEmail = '';
            bookingContext.customerPhone = '';
            bookingContext.notes = '';
            bookingContext.internalNote = '';
            bookingContext.sendNotifications = false;

            bookingContext.startTime = normalizeTime(selectionInfo && selectionInfo.startStr, config.timezone);
            bookingContext.endTime = normalizeTime(selectionInfo && selectionInfo.endStr, config.timezone);
            bookingContext.date = toDateString(selectionInfo && selectionInfo.startStr) || state.selectedDate;
            bookingContext.resourceId = resourceId ? parseInt(resourceId, 10) : bookingContext.resourceId;

            openBookingDialog({
                mode: 'selection',
                resourceId: bookingContext.resourceId,
                startTime: bookingContext.startTime,
                endTime: bookingContext.endTime,
                date: bookingContext.date,
            });

            if (calendarInstance && typeof calendarInstance.unselect === 'function') {
                calendarInstance.unselect();
            }
        }

        var calendarInstance = EventCalendar.create(target, {
            view: 'resourceTimeGridDay',
            initialDate: state.selectedDate,
            date: state.selectedDate,
                    customButtons: {
                        addAppointment: {
                            text: i18n.addAppointment || 'Add appointment',
                            click: function () {
                                var baseStart = normalizeTime(state.slotMinTime) || '09:00';
                                var defaultResource = getVisibleResources().length ? getVisibleResources()[0].id : null;
                                openBookingDialog({
                                    mode: 'manual',
                                    date: state.selectedDate,
                                    resourceId: defaultResource,
                                    startTime: baseStart,
                                    endTime: addMinutesToTime(baseStart, state.defaultDurationMinutes),
                                    status: 'pending',
                                    paymentStatus: '',
                                    customerId: null,
                                    customerEmail: '',
                                    customerPhone: '',
                                    notes: '',
                                    internalNote: '',
                                    sendNotifications: false,
                                });
                            },
                        },
            },
            headerToolbar: {
                start: 'prev,next today',
                center: 'title',
                end: 'resourceTimeGridDay,resourceTimelineDay addAppointment',
            },
            timeZone: config.timezone,
            resources: getVisibleResources(),
            views: viewOptions,
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
            selectable: true,
            selectMirror: true,
            select: onCalendarSelect,
            buttonText: {
                resourceTimeGridDay: i18n.resourcesView || 'Resources',
                resourceTimelineDay: i18n.timelineView || 'Timeline',
            },
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

        if (bookingStart) {
            bookingStart.addEventListener('change', function () {
                bookingContext.startTime = bookingStart.value;
                syncBookingEndTime();
            });
        }

        if (bookingService) {
            bookingService.addEventListener('change', function () {
                bookingContext.serviceId = bookingService.value ? parseInt(bookingService.value, 10) : null;
                syncBookingEndTime();
            });
        }

        if (bookingEnd) {
            bookingEnd.addEventListener('change', function () {
                bookingContext.endTime = bookingEnd.value;
                syncBookingStartTime();
            });
        }

        if (bookingDateInput) {
            bookingDateInput.addEventListener('change', function () {
                bookingContext.date = toDateString(bookingDateInput.value) || bookingContext.date;
            });
        }

        if (bookingCustomer) {
            bookingCustomer.addEventListener('change', function () {
                bookingContext.customerId = bookingCustomer.value ? parseInt(bookingCustomer.value, 10) : null;
            });
        }

        if (bookingStatus) {
            bookingStatus.addEventListener('change', function () {
                bookingContext.status = bookingStatus.value || 'pending';
            });
        }

        if (bookingPayment) {
            bookingPayment.addEventListener('change', function () {
                bookingContext.paymentStatus = bookingPayment.value || '';
            });
        }

        if (bookingCustomerEmail) {
            bookingCustomerEmail.addEventListener('change', function () {
                bookingContext.customerEmail = bookingCustomerEmail.value || '';
            });
        }

        if (bookingCustomerPhone) {
            bookingCustomerPhone.addEventListener('change', function () {
                bookingContext.customerPhone = bookingCustomerPhone.value || '';
            });
        }

        if (bookingInternalNote) {
            bookingInternalNote.addEventListener('change', function () {
                bookingContext.internalNote = bookingInternalNote.value || '';
            });
        }

        if (bookingNote) {
            bookingNote.addEventListener('change', function () {
                bookingContext.notes = bookingNote.value || '';
            });
        }

        if (bookingNotify) {
            bookingNotify.addEventListener('change', function () {
                bookingContext.sendNotifications = !!bookingNotify.checked;
            });
        }

        if (bookingResource) {
            bookingResource.addEventListener('change', function () {
                bookingContext.resourceId = bookingResource.value ? parseInt(bookingResource.value, 10) : null;
                var resource = bookingContext.resourceId ? findResourceById(bookingContext.resourceId) : null;
                if (bookingResourceLabel) {
                    bookingResourceLabel.textContent = resource ? (resource.title || '') : '';
                }
            });
        }

        if (bookingCancel) {
            bookingCancel.addEventListener('click', function (event) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                closeBookingDialog();
            });
        }

        if (bookingCancelAlt) {
            bookingCancelAlt.addEventListener('click', function (event) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                closeBookingDialog();
            });
        }

        if (bookingDialog) {
            bookingDialog.addEventListener('cancel', function (event) {
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                closeBookingDialog();
            });

            bookingDialog.addEventListener('close', function () {
                bookingDialog.removeAttribute('open');
                bookingDialog.setAttribute('hidden', 'hidden');
                bookingDialog.hidden = true;
            });
        }

        if (bookingForm) {
            bookingForm.addEventListener('submit', onBookingSubmit);
        }

        renderEmptyState(calendarWrapper, ensureArray(state.bootstrapEvents).length > 0 || data.hasEvents);
    });
})(window, document);
