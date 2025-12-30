(function (window, document) {
    'use strict';

    /**
     * Safely log to the console.
     *
     * @param {string} level Console method name.
     * @param {string} message Message to log.
     * @param {*} [context] Optional context payload.
     * @returns {void}
     */
    function logConsole(level, message, context) {
        if (!window.console || typeof window.console[level] !== 'function') {
            return;
        }

        if (typeof context !== 'undefined') {
            window.console[level](message, context);
            return;
        }

        window.console[level](message);
    }

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
     * Convert supported values to a Date instance.
     *
     * @param {*} value Raw date value.
     * @returns {Date|null}
     */
    function toSafeDate(value) {
        if (!value) {
            return null;
        }

        if (value instanceof Date) {
            return new Date(value.getTime());
        }

        if (typeof value === 'string') {
            var parsed = new Date(value);

            if (!Number.isNaN(parsed.getTime())) {
                return parsed;
            }
        }

        return null;
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
     * Convert a minute value to a HH:MM:SS duration string.
     *
     * @param {number|string} minutes Duration in minutes.
     * @returns {string}
     */
    function minutesToDuration(minutes, fallbackMinutes) {
        var totalMinutes = parseInt(minutes, 10);
        var configuredMinutes = parseInt(fallbackMinutes, 10);

        if (!totalMinutes || totalMinutes < 1) {
            totalMinutes = configuredMinutes && configuredMinutes > 0 ? configuredMinutes : 30;
        }

        var hours = Math.floor(totalMinutes / 60);
        var remainder = totalMinutes % 60;

        return String(hours).padStart(2, '0') + ':' + String(remainder).padStart(2, '0') + ':00';
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
            if (item.dataset && typeof item.dataset === 'object') {
                Object.keys(item.dataset).forEach(function (key) {
                    option.dataset[key] = item.dataset[key];
                });
            }
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

    /**
     * Render a notice within the calendar container.
     *
     * @param {HTMLElement|null} wrapper Calendar wrapper.
     * @param {string} message Notice message.
     * @param {'success'|'error'} [type='success'] Notice type.
     * @returns {void}
     */
    function renderCalendarNotice(wrapper, message, type) {
        if (!wrapper || !message) {
            return;
        }

        var existing = wrapper.querySelector('.smooth-booking-calendar-notice');

        if (existing && existing.parentNode) {
            existing.parentNode.removeChild(existing);
        }

        var notice = document.createElement('div');
        notice.className = 'notice smooth-booking-calendar-notice ' + (type === 'error' ? 'notice-error' : 'notice-success');

        var messageNode = document.createElement('p');
        messageNode.textContent = message;
        notice.appendChild(messageNode);

        if (wrapper.firstChild) {
            wrapper.insertBefore(notice, wrapper.firstChild);
        } else {
            wrapper.appendChild(notice);
        }

        if (window.wp && window.wp.a11y && typeof window.wp.a11y.speak === 'function') {
            window.wp.a11y.speak(message);
        }

        window.setTimeout(function () {
            if (notice && notice.parentNode) {
                notice.parentNode.removeChild(notice);
            }
        }, 8000);
    }

    ready(function initCalendar() {
        try {
            var settings = window.SmoothBookingCalendar || {};
            var data = window.SmoothBookingCalendarData || settings.data || {};
            var i18n = settings.i18n || {};
            var target = document.getElementById('smooth-booking-calendar');
            var hasCalendarLibrary = typeof EventCalendar !== 'undefined' && typeof EventCalendar.create === 'function';
            var calendarWrapper = target ? target.closest('.smooth-booking-calendar-board') : document.querySelector('.smooth-booking-calendar-board');
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
            var addCustomerButton = document.getElementById('smooth-booking-calendar-add-customer');
            var customerAccordion = document.getElementById('smooth-booking-calendar-customer-accordion');
            var customerAccordionBody = customerAccordion
                ? customerAccordion.querySelector('.smooth-booking-calendar-customer-accordion__body')
                : null;
            var customerError = document.getElementById('smooth-booking-calendar-customer-error');
            var customerUserAction = document.getElementById('smooth-booking-customer-user-action');
            var customerExistingUser = document.getElementById('smooth-booking-customer-existing-user');
            var customerTags = document.getElementById('smooth-booking-customer-tags');
            var customerNewTags = document.getElementById('smooth-booking-customer-new-tags');
            var customerName = document.getElementById('smooth-booking-customer-name');
            var customerFirstName = document.getElementById('smooth-booking-customer-first-name');
            var customerLastName = document.getElementById('smooth-booking-customer-last-name');
            var customerPhone = document.getElementById('smooth-booking-customer-phone');
            var customerEmail = document.getElementById('smooth-booking-customer-email');
            var customerDateOfBirth = document.getElementById('smooth-booking-customer-date-of-birth');
            var customerCountry = document.getElementById('smooth-booking-customer-country');
            var customerStateRegion = document.getElementById('smooth-booking-customer-state-region');
            var customerPostalCode = document.getElementById('smooth-booking-customer-postal-code');
            var customerCity = document.getElementById('smooth-booking-customer-city');
            var customerStreetAddress = document.getElementById('smooth-booking-customer-street-address');
            var customerAdditionalAddress = document.getElementById('smooth-booking-customer-additional-address');
            var customerStreetNumber = document.getElementById('smooth-booking-customer-street-number');
            var customerNotes = document.getElementById('smooth-booking-customer-notes');
            var customerProfileImage = document.querySelector('#smooth-booking-calendar-customer-accordion input[name="customer_profile_image_id"]');
            var customerSubmit = document.getElementById('smooth-booking-calendar-customer-submit');

            var initialSlotDuration = data.slotDuration || minutesToDuration(data.slotLengthMinutes, data.slotLengthMinutes);

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
                slotLabelInterval: state.slotDuration,
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
        var customerDetailRequests = {};
        var customerTriggerTraceEnabled = false;
        var customerTriggerLastTimestamp = 0;

        /**
         * Refresh booking field references and bind time synchronisation listeners.
         */
        function bindBookingFields() {
            bookingStart = bookingStart || document.getElementById('smooth-booking-calendar-booking-start');
            bookingEnd = bookingEnd || document.getElementById('smooth-booking-calendar-booking-end');
            bookingService = bookingService || document.getElementById('smooth-booking-calendar-booking-service');

            var bindTimeInput = function (element, callback) {
                if (!element || element.dataset.smoothBookingBound === '1') {
                    return;
                }

                ['change', 'input'].forEach(function (eventName) {
                    element.addEventListener(eventName, callback);
                });

                element.dataset.smoothBookingBound = '1';
            };

            bindTimeInput(bookingStart, function () {
                bookingContext.startTime = bookingStart.value;
                syncBookingEndTime();
            });

            bindTimeInput(bookingEnd, function () {
                bookingContext.endTime = bookingEnd.value;
                syncBookingStartTime();
            });

            if (bookingService && bookingService.dataset.smoothBookingBound !== '1') {
                bookingService.addEventListener('change', function () {
                    bookingContext.serviceId = bookingService.value ? parseInt(bookingService.value, 10) : null;
                    syncBookingEndTime();
                });

                if (window.jQuery && typeof window.jQuery.fn.select2 === 'function') {
                    window.jQuery(bookingService).on('select2:select select2:clear', function () {
                        bookingContext.serviceId = bookingService.value ? parseInt(bookingService.value, 10) : null;
                        syncBookingEndTime();
                    });
                }

                bookingService.dataset.smoothBookingBound = '1';
            }
        }

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
            var numericId = parseInt(serviceId, 10);
            var template = state.services[key] || state.services[numericId];

            if (!template && Array.isArray(state.services)) {
                template = state.services.find(function (item) {
                    return item && parseInt(item.id, 10) === numericId;
                }) || null;
            }

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
                    dataset: {
                        email: customer.email || '',
                        phone: customer.phone || '',
                    },
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
            bindCustomerChangeHandlers();
        }

        /**
         * Find a customer object by id.
         *
         * @param {number|string|null} customerId Customer identifier.
         * @returns {Object|null}
         */
        function findCustomerById(customerId) {
            var id = parseInt(customerId, 10);

            if (Number.isNaN(id)) {
                return null;
            }

            var customers = ensureArray(state.customers);

            for (var index = 0; index < customers.length; index++) {
                var customer = customers[index];

                if (!customer) {
                    continue;
                }

                var currentId = typeof customer.id !== 'undefined' ? parseInt(customer.id, 10) : null;

                if (!Number.isNaN(currentId) && currentId === id) {
                    return customer;
                }
            }

            return null;
        }

        /**
         * Handle customer selection changes across native and Select2 events.
         *
         * @param {Event} event Selection event.
         * @returns {void}
         */
        function handleCustomerChange(event) {
            if (!bookingCustomer) {
                return;
            }

            var selectedId = bookingCustomer.value ? parseInt(bookingCustomer.value, 10) : null;
            var contact = null;

            if (event && event.params && event.params.data && typeof event.params.data.id !== 'undefined') {
                selectedId = event.params.data.id === '' ? null : parseInt(event.params.data.id, 10);

                var dataset = event.params.data.element && event.params.data.element.dataset ? event.params.data.element.dataset : null;
                contact = {
                    email: event.params.data.email || (dataset && dataset.email ? dataset.email : ''),
                    phone: event.params.data.phone || (dataset && dataset.phone ? dataset.phone : ''),
                };
            }

            bookingContext.customerId = Number.isNaN(selectedId) ? null : selectedId;
            syncCustomerContactFields(bookingContext.customerId, contact);
        }

        /**
         * Ensure customer selection events remain bound after Select2 reinitialisation.
         *
         * @returns {void}
         */
        function bindCustomerChangeHandlers() {
            if (!bookingCustomer) {
                return;
            }

            if (bookingCustomer.dataset.smoothBookingCustomerBound !== '1') {
                bookingCustomer.addEventListener('change', handleCustomerChange);
                bookingCustomer.dataset.smoothBookingCustomerBound = '1';
            }

            if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.select2 === 'function') {
                window.jQuery(document)
                    .off('select2:select.smoothBookingCustomer select2:clear.smoothBookingCustomer')
                    .on('select2:select.smoothBookingCustomer select2:clear.smoothBookingCustomer', '#smooth-booking-calendar-booking-customer', handleCustomerChange);
            }
        }

        /**
         * Sync customer contact inputs with selected customer details.
         *
         * @param {number|string|null} customerId Customer identifier.
         * @param {{email?: string, phone?: string}|null} [contactDetails] Optional contact details from selection event.
         * @returns {void}
         */
        function syncCustomerContactFields(customerId, contactDetails) {
            var customer = findCustomerById(customerId);
            var option = null;
            var fallbackContact = contactDetails && typeof contactDetails === 'object' ? contactDetails : {};
            var email = fallbackContact.email || (customer && customer.email ? customer.email : '');
            var phone = fallbackContact.phone || (customer && customer.phone ? customer.phone : '');

            if ((!email || !phone) && bookingCustomer) {
                var targetValue = typeof customerId === 'undefined' || customerId === null ? '' : String(customerId);

                if (typeof bookingCustomer.querySelector === 'function' && targetValue) {
                    try {
                        var escapedValue = targetValue;
                        if (window.CSS && typeof window.CSS.escape === 'function') {
                            escapedValue = window.CSS.escape(targetValue);
                        }

                        option = bookingCustomer.querySelector('option[value="' + escapedValue + '"]');
                    } catch (error) {
                        option = null;
                    }
                }

                if (!option && bookingCustomer.options && bookingCustomer.selectedIndex >= 0) {
                    option = bookingCustomer.options[bookingCustomer.selectedIndex];
                }

                if (option && option.dataset) {
                    email = email || option.dataset.email || '';
                    phone = phone || option.dataset.phone || '';
                }
            }

            if (bookingCustomerEmail) {
                bookingCustomerEmail.value = email;
            }

            if (bookingCustomerPhone) {
                bookingCustomerPhone.value = phone;
            }

            bookingContext.customerEmail = email;
            bookingContext.customerPhone = phone;

            if (typeof console !== 'undefined' && typeof console.log === 'function') {
                console.log('[Smooth Booking] Selected customer contact', {
                    id: typeof customerId === 'undefined' ? null : customerId,
                    email: email,
                    phone: phone,
                });
            }

            if (customerId && config.customersEndpoint && (!customer || !email || !phone)) {
                fetchCustomerDetails(customerId);
            }
        }

        /**
         * Fetch a customer's details when not present in local state.
         *
         * @param {number|string|null} customerId Customer identifier.
         * @returns {void}
         */
        function fetchCustomerDetails(customerId) {
            var id = parseInt(customerId, 10);

            if (Number.isNaN(id) || customerDetailRequests[id]) {
                return;
            }

            if (!config.customersEndpoint) {
                return;
            }

            customerDetailRequests[id] = fetch(config.customersEndpoint + '/' + id, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-WP-Nonce': config.nonce || '',
                },
            })
                .then(function (response) { return response.ok ? response.json() : null; })
                .then(function (body) {
                    if (!body || typeof body !== 'object' || Number.isNaN(parseInt(body.id, 10))) {
                        return;
                    }

                    var customers = ensureArray(state.customers);
                    var existingIndex = customers.findIndex(function (customer) {
                        return customer && parseInt(customer.id, 10) === id;
                    });

                    if (existingIndex >= 0) {
                        customers[existingIndex] = body;
                    } else {
                        customers.push(body);
                    }

                    state.customers = customers;
                    syncCustomerContactFields(id);
                })
                .catch(function () {
                    // Intentionally swallow errors; manual input remains available.
                })
                .finally(function () {
                    delete customerDetailRequests[id];
                });
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
                    syncCustomerContactFields(selectedId || bookingContext.customerId);
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    populateBookingCustomers(selectedId || null);
                    syncCustomerContactFields(selectedId || bookingContext.customerId);
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
            bindBookingFields();

            if (!bookingStart || !bookingEnd) {
                return;
            }

            var startValue = normalizeTime(bookingStart.value || bookingContext.startTime, config.timezone);
            var duration = getServiceDurationMinutes((bookingService && bookingService.value) || bookingContext.serviceId);
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
            bindBookingFields();

            if (!bookingStart || !bookingEnd) {
                return;
            }

            var endValue = normalizeTime(bookingEnd.value || bookingContext.endTime, config.timezone);
            var duration = getServiceDurationMinutes((bookingService && bookingService.value) || bookingContext.serviceId);
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
         * Capture the current booking form values into the booking context.
         *
         * @returns {void}
         */
        function captureBookingFormState() {
            bookingContext.date = bookingDateInput && bookingDateInput.value
                ? toDateString(bookingDateInput.value)
                : bookingContext.date;
            bookingContext.resourceId = bookingResource && bookingResource.value
                ? parseInt(bookingResource.value, 10)
                : bookingContext.resourceId;
            bookingContext.serviceId = bookingService && bookingService.value
                ? parseInt(bookingService.value, 10)
                : bookingContext.serviceId;
            bookingContext.startTime = bookingStart && bookingStart.value
                ? normalizeTime(bookingStart.value, config.timezone)
                : bookingContext.startTime;
            bookingContext.endTime = bookingEnd && bookingEnd.value
                ? normalizeTime(bookingEnd.value, config.timezone)
                : bookingContext.endTime;
            bookingContext.customerId = bookingCustomer && bookingCustomer.value
                ? parseInt(bookingCustomer.value, 10)
                : bookingContext.customerId;
            bookingContext.status = bookingStatus && bookingStatus.value
                ? bookingStatus.value
                : bookingContext.status;
            bookingContext.paymentStatus = bookingPayment && bookingPayment.value
                ? bookingPayment.value
                : bookingContext.paymentStatus;
            bookingContext.customerEmail = bookingCustomerEmail && bookingCustomerEmail.value
                ? bookingCustomerEmail.value
                : bookingContext.customerEmail;
            bookingContext.customerPhone = bookingCustomerPhone && bookingCustomerPhone.value
                ? bookingCustomerPhone.value
                : bookingContext.customerPhone;
            bookingContext.internalNote = bookingInternalNote && bookingInternalNote.value
                ? bookingInternalNote.value
                : bookingContext.internalNote;
            bookingContext.notes = bookingNote && bookingNote.value
                ? bookingNote.value
                : bookingContext.notes;
            bookingContext.sendNotifications = bookingNotify ? !!bookingNotify.checked : bookingContext.sendNotifications;
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
                syncCustomerContactFields(bookingContext.customerId);
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

            bindBookingFields();

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

            bookingDialog.open = false;
            bookingDialog.removeAttribute('open');
            bookingDialog.setAttribute('hidden', 'hidden');
            bookingDialog.hidden = true;

            if (bookingForm && typeof bookingForm.reset === 'function') {
                bookingForm.reset();
            }

            setBookingError('');
        }

        /**
         * Hide the booking dialog without resetting the form values.
         *
         * @returns {void}
         */
        function pauseBookingDialog() {
            if (!bookingDialog) {
                return;
            }

            if (typeof bookingDialog.close === 'function') {
                bookingDialog.close();
            }

            bookingDialog.open = false;
            bookingDialog.removeAttribute('open');
            bookingDialog.setAttribute('hidden', 'hidden');
            bookingDialog.hidden = true;
        }

        /**
         * Bind dialog dismiss handlers.
         *
         * @param {HTMLElement|null} element Target element.
         * @returns {void}
         */
        function bindDialogDismiss(element) {
            if (!element) {
                return;
            }

            element.addEventListener('click', function (event) {
                /* eslint-disable no-console */
                console.log('[SmoothBooking] Booking dialog dismiss clicked:', element.id || 'unknown');
                /* eslint-enable no-console */
                if (event && typeof event.preventDefault === 'function') {
                    event.preventDefault();
                }
                closeBookingDialog();
            });
        }

        bindDialogDismiss(bookingCancel);
        bindDialogDismiss(bookingCancelAlt);

        document.addEventListener('click', function (event) {
            var targetElement = event.target;
            if (!targetElement || !(targetElement instanceof HTMLElement)) {
                return;
            }

            var dismissTarget = targetElement.closest(
                '[data-smooth-booking-dismiss], #smooth-booking-calendar-booking-cancel, #smooth-booking-calendar-booking-cancel-alt'
            );

            if (!dismissTarget) {
                return;
            }

            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            closeBookingDialog();
        });

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

            var resolvedSlotDuration = payload.slotDuration || minutesToDuration(payload.slotLengthMinutes, state.defaultDurationMinutes);

            if (resolvedSlotDuration) {
                state.slotDuration = resolvedSlotDuration;
                state.defaultDurationMinutes = durationToMinutes(resolvedSlotDuration);
                calendarInstance.setOption('slotDuration', resolvedSlotDuration);
                viewOptions.resourceTimelineDay.slotDuration = resolvedSlotDuration;
                viewOptions.resourceTimelineDay.slotLabelInterval = resolvedSlotDuration;
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
                    var payloadDuration = payload.slotDuration || minutesToDuration(payload.slotLengthMinutes, state.defaultDurationMinutes);

                    state.slotDuration = payloadDuration || state.slotDuration;
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

            syncBookingEndTime();
            captureBookingFormState();

            var providerId = bookingContext.resourceId || (bookingResource ? parseInt(bookingResource.value, 10) : 0);
            var serviceId = bookingContext.serviceId || (bookingService ? parseInt(bookingService.value, 10) : 0);
            var customerId = bookingContext.customerId || (bookingCustomer ? parseInt(bookingCustomer.value, 10) : 0);
            var startValue = bookingContext.startTime || (bookingStart ? bookingStart.value : '');
            var endValue = bookingContext.endTime || (bookingEnd ? bookingEnd.value : '');
            var dateValue = bookingContext.date || state.selectedDate;

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

            var endpointPath = config.appointmentsEndpoint;

            try {
                var parsed = new URL(config.appointmentsEndpoint, window.location.origin);
                endpointPath = parsed.pathname + parsed.search;
            } catch (error) {
                endpointPath = config.appointmentsEndpoint;
            }

            var requestPromise;
            if (window.wp && window.wp.apiFetch) {
                var apiFetchArgs = {
                    method: 'POST',
                    data: payload,
                };

                if (/^https?:\/\//i.test(config.appointmentsEndpoint)) {
                    apiFetchArgs.url = config.appointmentsEndpoint;
                } else if (/^\/wp-json\//.test(endpointPath)) {
                    apiFetchArgs.path = endpointPath.replace(/^\/wp-json/, '');
                } else {
                    apiFetchArgs.path = endpointPath;
                }

                requestPromise = window.wp.apiFetch(apiFetchArgs);
            } else {
                requestPromise = fetch(config.appointmentsEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-WP-Nonce': config.nonce || '',
                    },
                    credentials: 'include',
                    body: JSON.stringify(payload),
                }).then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (body) {
                            var message = body && body.message ? body.message : response.statusText;
                            throw new Error(message || 'Request failed');
                        });
                    }

                    return response.json();
                });
            }

            requestPromise
                .then(function () {
                    closeBookingDialog();
                    if (calendarInstance) {
                        calendarInstance.refetchEvents();
                    }
                    renderCalendarNotice(
                        calendarWrapper,
                        i18n.bookingSaved || 'Appointment saved successfully.',
                        'success'
                    );
                })
                .catch(function (error) {
                    setBookingError(error && error.message ? error.message : (i18n.bookingSaveError || 'Unable to save appointment.'));
                    renderCalendarNotice(
                        calendarWrapper,
                        error && error.message ? error.message : (i18n.bookingSaveError || 'Unable to save appointment.'),
                        'error'
                    );
                });
        }

        /**
         * Toggle the existing user selector visibility.
         *
         * @param {string} action Selected action key.
         * @returns {void}
         */
        function toggleCustomerExistingUserField(action) {
            var field = customerAccordion ? customerAccordion.querySelector('.smooth-booking-existing-user-field') : null;

            if (!field) {
                return;
            }

            if (action === 'assign') {
                field.style.display = '';
            } else {
                field.style.display = 'none';
                if (customerExistingUser) {
                    customerExistingUser.value = '0';
                }
            }
        }

        /**
         * Display or clear customer dialog errors.
         *
         * @param {string} message Error message.
         * @returns {void}
         */
        function setCustomerDialogError(message) {
            if (!customerError) {
                return;
            }

            customerError.textContent = message || '';
            customerError.hidden = !message;
        }

        /**
         * Retrieve the avatar field wrapper.
         *
         * @returns {HTMLElement|null} Avatar field element.
         */
        function getCustomerAvatarField() {
            if (!customerAccordion) {
                return null;
            }

            return customerAccordion.querySelector('.smooth-booking-avatar-field');
        }

        /**
         * Reset the avatar preview to placeholder.
         *
         * @returns {void}
         */
        function resetCustomerAvatar() {
            var field = getCustomerAvatarField();
            if (!field) {
                return;
            }

            var preview = field.querySelector('.smooth-booking-avatar-preview');
            var removeButton = field.querySelector('.smooth-booking-avatar-remove');
            var placeholder = field.getAttribute('data-placeholder') || '';

            if (preview) {
                preview.innerHTML = placeholder;
            }

            if (removeButton) {
                removeButton.style.display = 'none';
            }

            if (customerProfileImage) {
                customerProfileImage.value = '0';
            }
        }

        /**
         * Update avatar preview from media frame selection.
         *
         * @param {Object|null} attachment Media attachment object.
         * @returns {void}
         */
        function updateCustomerAvatar(attachment) {
            var field = getCustomerAvatarField();
            if (!field) {
                return;
            }

            var preview = field.querySelector('.smooth-booking-avatar-preview');
            var removeButton = field.querySelector('.smooth-booking-avatar-remove');

            if (attachment && attachment.id) {
                var imageUrl = attachment.sizes && attachment.sizes.thumbnail ? attachment.sizes.thumbnail.url : attachment.url;
                var altText = attachment.alt || attachment.title || '';
                var wrapper = document.createElement('span');
                wrapper.className = 'smooth-booking-avatar-wrapper';
                var img = document.createElement('img');
                img.src = imageUrl;
                img.alt = altText;
                img.className = 'smooth-booking-avatar-image';
                wrapper.appendChild(img);

                if (preview) {
                    preview.innerHTML = '';
                    preview.appendChild(wrapper);
                }

                if (customerProfileImage) {
                    customerProfileImage.value = attachment.id;
                }

                if (removeButton) {
                    removeButton.style.display = '';
                }
            } else {
                resetCustomerAvatar();
            }
        }

        /**
         * Reset customer form fields for the accordion.
         *
         * @returns {void}
         */
        function resetCustomerFormFields() {
            if (!customerAccordion) {
                return;
            }

            var fields = customerAccordion.querySelectorAll('input, select, textarea');
            Array.prototype.slice.call(fields).forEach(function (field) {
                if (!(field instanceof HTMLElement)) {
                    return;
                }

                if (field.matches('input[type="hidden"][name="customer_profile_image_id"]')) {
                    field.value = '0';
                    return;
                }

                if (field.matches('input[type="checkbox"], input[type="radio"]')) {
                    field.checked = false;
                    return;
                }

                if (field.tagName === 'SELECT') {
                    if (field.multiple) {
                        Array.prototype.slice.call(field.options).forEach(function (option) {
                            option.selected = false;
                        });
                    } else if (field.options.length) {
                        field.selectedIndex = 0;
                    } else {
                        field.value = '';
                    }
                    return;
                }

                field.value = '';
            });

            if (customerUserAction) {
                customerUserAction.value = 'none';
            }

            if (customerExistingUser) {
                customerExistingUser.value = '0';
            }
        }

        /**
         * Retrieve the customer accordion body element.
         *
         * @returns {HTMLElement|null} Accordion body element.
         */
        function getCustomerAccordionBody() {
            if (customerAccordionBody && customerAccordionBody.isConnected) {
                return customerAccordionBody;
            }

            customerAccordion = customerAccordion || document.getElementById('smooth-booking-calendar-customer-accordion');

            if (!customerAccordion) {
                return null;
            }

            customerAccordionBody = customerAccordion.querySelector('.smooth-booking-calendar-customer-accordion__body');

            if (!customerAccordionBody) {
                customerAccordionBody = document.getElementById('smooth-booking-calendar-customer-accordion-body');
            }

            return customerAccordionBody || null;
        }

        /**
         * Open the customer creation accordion.
         *
         * @returns {void}
         */
        function openCustomerAccordion() {
            customerAccordion = customerAccordion || document.getElementById('smooth-booking-calendar-customer-accordion');

            if (!customerAccordion) {
                return;
            }

            setCustomerDialogError('');
            resetCustomerFormFields();
            resetCustomerAvatar();
            toggleCustomerExistingUserField(customerUserAction ? customerUserAction.value : 'none');

            customerAccordionBody = getCustomerAccordionBody();
            if (customerAccordionBody) {
                customerAccordionBody.removeAttribute('hidden');
                customerAccordionBody.hidden = false;
            }
            customerAccordion.classList.add('is-open');

            if (addCustomerButton) {
                addCustomerButton.setAttribute('aria-expanded', 'true');
            }

            if (customerName && typeof customerName.focus === 'function') {
                window.setTimeout(function () { customerName.focus(); }, 50);
            }
        }

        /**
         * Close the customer accordion.
         *
         * @returns {void}
         */
        function closeCustomerAccordion() {
            customerAccordion = customerAccordion || document.getElementById('smooth-booking-calendar-customer-accordion');

            if (!customerAccordion) {
                return;
            }

            customerAccordion.classList.remove('is-open');
            customerAccordionBody = getCustomerAccordionBody();
            if (customerAccordionBody) {
                customerAccordionBody.setAttribute('hidden', 'hidden');
                customerAccordionBody.hidden = true;
            }

            resetCustomerAvatar();
            setCustomerDialogError('');
            toggleCustomerExistingUserField('none');
            resetCustomerFormFields();

            if (addCustomerButton) {
                addCustomerButton.setAttribute('aria-expanded', 'false');
            }
        }

        /**
         * Enable or disable the customer form submit button.
         *
         * @param {boolean} disabled Whether the form is submitting.
         * @returns {void}
         */
        function disableCustomerForm(disabled) {
            if (customerSubmit) {
                customerSubmit.disabled = !!disabled;
            }

            if (customerAccordion) {
                customerAccordion.classList.toggle('is-submitting', !!disabled);
            }
        }

        /**
         * Build the payload for creating a customer.
         *
         * @returns {Object} Payload object.
         */
        function gatherCustomerPayload() {
            var tagIds = [];

            if (customerTags && customerTags.selectedOptions) {
                tagIds = Array.prototype.slice.call(customerTags.selectedOptions)
                    .map(function (option) { return parseInt(option.value, 10); })
                    .filter(function (value) { return !Number.isNaN(value); });
            }

            return {
                name: customerName ? customerName.value : '',
                first_name: customerFirstName ? customerFirstName.value : '',
                last_name: customerLastName ? customerLastName.value : '',
                phone: customerPhone ? customerPhone.value : '',
                email: customerEmail ? customerEmail.value : '',
                date_of_birth: customerDateOfBirth ? customerDateOfBirth.value : '',
                country: customerCountry ? customerCountry.value : '',
                state_region: customerStateRegion ? customerStateRegion.value : '',
                postal_code: customerPostalCode ? customerPostalCode.value : '',
                city: customerCity ? customerCity.value : '',
                street_address: customerStreetAddress ? customerStreetAddress.value : '',
                additional_address: customerAdditionalAddress ? customerAdditionalAddress.value : '',
                street_number: customerStreetNumber ? customerStreetNumber.value : '',
                notes: customerNotes ? customerNotes.value : '',
                profile_image_id: customerProfileImage && customerProfileImage.value ? customerProfileImage.value : 0,
                user_action: customerUserAction ? customerUserAction.value : 'none',
                existing_user_id: customerExistingUser ? customerExistingUser.value : 0,
                tag_ids: tagIds,
                new_tags: customerNewTags ? customerNewTags.value : '',
            };
        }

        /**
         * Submit the customer form via REST and return to booking dialog.
         *
         * @param {Event} event Submit event.
         * @returns {void}
         */
        function handleCustomerSubmit(event) {
            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (!config.customersEndpoint || typeof fetch !== 'function') {
                setCustomerDialogError(i18n.customerCreateError || 'Unable to create customer.');
                return;
            }

            setCustomerDialogError('');
            disableCustomerForm(true);

            var payload = gatherCustomerPayload();

            fetch(config.customersEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-WP-Nonce': config.nonce || '',
                },
                credentials: 'include',
                body: JSON.stringify(payload),
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (!result.ok) {
                        var message = result.body && result.body.message ? result.body.message : '';
                        throw new Error(message || (i18n.customerCreateError || 'Unable to create customer.'));
                    }

                    return result.body;
                })
                .then(function (customer) {
                    if (!customer || Number.isNaN(parseInt(customer.id, 10))) {
                        throw new Error(i18n.customerCreateError || 'Unable to create customer.');
                    }

                    var id = parseInt(customer.id, 10);
                    var customers = ensureArray(state.customers);
                    var existingIndex = customers.findIndex(function (item) {
                        return item && parseInt(item.id, 10) === id;
                    });

                    if (existingIndex >= 0) {
                        customers[existingIndex] = customer;
                    } else {
                        customers.unshift(customer);
                    }

                    state.customers = customers;
                    bookingContext.customerId = id;
                    bookingContext.customerEmail = customer.email || bookingContext.customerEmail;
                    bookingContext.customerPhone = customer.phone || bookingContext.customerPhone;

                    populateBookingCustomers(id);
                    if (bookingCustomer) {
                        bookingCustomer.value = String(id);
                    }

                    syncCustomerContactFields(id, {
                        email: bookingContext.customerEmail,
                        phone: bookingContext.customerPhone,
                    });

                    closeCustomerAccordion();
                })
                .catch(function (error) {
                    setCustomerDialogError(error && error.message ? error.message : (i18n.customerCreateError || 'Unable to create customer.'));
                })
                .finally(function () {
                    disableCustomerForm(false);
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

        /**
         * Resolve the resource identifier for an EventCalendar event.
         *
         * @param {Object} event Calendar event.
         * @returns {number|null}
         */
        function getEventResourceId(event) {
            if (!event) {
                return null;
            }

            if (event.getResources && typeof event.getResources === 'function') {
                var resources = event.getResources();
                if (Array.isArray(resources) && resources.length > 0 && resources[0].id) {
                    return parseInt(resources[0].id, 10) || null;
                }
            }

            if (event.resource && event.resource.id) {
                return parseInt(event.resource.id, 10) || null;
            }

            if (event.resourceIds && event.resourceIds.length > 0) {
                return parseInt(event.resourceIds[0], 10) || null;
            }

            if (event.extendedProps && event.extendedProps.resourceId) {
                return parseInt(event.extendedProps.resourceId, 10) || null;
            }

            return null;
        }

        /**
         * Format a human readable time range label.
         *
         * @param {string} startTime Start time (HH:MM).
         * @param {string} endTime End time (HH:MM).
         * @returns {string}
         */
        function formatTimeRangeLabel(startTime, endTime) {
            var template = (i18n && i18n.timeRangeTemplate) ? i18n.timeRangeTemplate : '%1$s – %2$s';
            return template.replace('%1$s', startTime).replace('%2$s', endTime);
        }

        /**
         * Update an EventCalendar event instance with new timings and labels.
         *
         * @param {Object} event EventCalendar event.
         * @param {Date|null} startDate New start date.
         * @param {Date|null} endDate New end date.
         * @param {string} startValue Normalised start time string.
         * @param {string} endValue Normalised end time string.
         * @param {string} timeRangeLabel Human readable time range label.
         *
         * @returns {void}
         */
        function applyEventTimeUpdates(event, startDate, endDate, startValue, endValue, timeRangeLabel) {
            if (!event) {
                return;
            }

            if (startDate && typeof event.setStart === 'function') {
                event.setStart(startDate, { maintainDuration: false });
            } else if (startDate) {
                event.start = startDate;
            }

            if (endDate && typeof event.setEnd === 'function') {
                event.setEnd(endDate);
            } else if (endDate) {
                event.end = endDate;
            }

            if (event.setExtendedProp) {
                if (timeRangeLabel) {
                    event.setExtendedProp('timeRange', timeRangeLabel);
                }
                if (startValue) {
                    event.setExtendedProp('appointment_start', startValue);
                }
                if (endValue) {
                    event.setExtendedProp('appointment_end', endValue);
                }
            } else if (event.extendedProps) {
                if (timeRangeLabel) {
                    event.extendedProps.timeRange = timeRangeLabel;
                }
                if (startValue) {
                    event.extendedProps.appointment_start = startValue;
                }
                if (endValue) {
                    event.extendedProps.appointment_end = endValue;
                }
            }

            if (event.el) {
                var timeNode = event.el.querySelector('.smooth-booking-calendar-event__time');

                if (timeNode && timeRangeLabel) {
                    timeNode.textContent = timeRangeLabel;
                }
            }
        }

        /**
         * Persist a moved or resized appointment to the REST API.
         *
         * @param {Object} changeInfo Event change payload from EventCalendar.
         * @returns {void}
         */
        function onEventChange(changeInfo) {
            if (!changeInfo || !changeInfo.event) {
                return;
            }

            if (!config.appointmentsEndpoint) {
                renderCalendarNotice(calendarWrapper, i18n.bookingEndpointMissing || 'Booking endpoint is unavailable. Please reload the page.', 'error');
                return;
            }

            var event = changeInfo.event;
            var appointmentId = event.id ? parseInt(event.id, 10) : null;

            if (!appointmentId && event.extendedProps && event.extendedProps.appointmentId) {
                appointmentId = parseInt(event.extendedProps.appointmentId, 10);
            }

            var resourceId = getEventResourceId(event);
            var serviceId = event.extendedProps && event.extendedProps.serviceId ? parseInt(event.extendedProps.serviceId, 10) : null;
            var startDate = toSafeDate(event.start) || toSafeDate(event.startStr);
            var endDate = toSafeDate(event.end) || toSafeDate(event.endStr);

            if (!endDate && startDate && changeInfo && changeInfo.oldEvent) {
                var oldStart = toSafeDate(changeInfo.oldEvent.start);
                var oldEnd = toSafeDate(changeInfo.oldEvent.end);

                if (oldStart && oldEnd) {
                    var durationMs = oldEnd.getTime() - oldStart.getTime();
                    if (durationMs > 0) {
                        endDate = new Date(startDate.getTime() + durationMs);
                    }
                }
            }

            var appointmentDate = toDateString(startDate);
            var startValue = normalizeTime(startDate, config.timezone);
            var endValue = normalizeTime(endDate, config.timezone);

            if (!appointmentId || !resourceId || !serviceId || !appointmentDate || !startValue || !endValue) {
                if (changeInfo && typeof changeInfo.revert === 'function') {
                    changeInfo.revert();
                }
                renderCalendarNotice(calendarWrapper, i18n.bookingMoveError || 'Unable to move appointment. Please try again.', 'error');
                return;
            }

            var targetEndpoint = (config.appointmentsEndpoint || '').replace(/\/$/, '') + '/' + appointmentId;
            var endpointPath = targetEndpoint;

            try {
                var parsedEndpoint = new URL(targetEndpoint, window.location.origin);
                endpointPath = parsedEndpoint.pathname + parsedEndpoint.search;
            } catch (error) {
                endpointPath = targetEndpoint;
            }

            var payload = {
                provider_id: resourceId,
                service_id: serviceId,
                location_id: state.locationId ? parseInt(state.locationId, 10) : null,
                appointment_date: appointmentDate,
                appointment_start: startValue,
                appointment_end: endValue,
            };

            var timeRangeLabel = formatTimeRangeLabel(startValue, endValue);

            applyEventTimeUpdates(event, startDate, endDate, startValue, endValue, timeRangeLabel);

            if (calendarInstance && typeof calendarInstance.rerenderEvents === 'function') {
                calendarInstance.rerenderEvents();
            }

            var requestPromise;
            if (window.wp && window.wp.apiFetch) {
                var apiFetchArgs = {
                    method: 'PATCH',
                    data: payload,
                };

                if (/^https?:\/\//i.test(targetEndpoint)) {
                    apiFetchArgs.url = targetEndpoint;
                } else if (/^\/wp-json\//.test(endpointPath)) {
                    apiFetchArgs.path = endpointPath.replace(/^\/wp-json/, '');
                } else {
                    apiFetchArgs.path = endpointPath;
                }

                requestPromise = window.wp.apiFetch(apiFetchArgs);
            } else {
                requestPromise = fetch(targetEndpoint, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-WP-Nonce': config.nonce || '',
                    },
                    credentials: 'include',
                    body: JSON.stringify(payload),
                }).then(function (response) {
                    if (!response.ok) {
                        return response.json().then(function (body) {
                            var message = body && body.message ? body.message : response.statusText;
                            throw new Error(message || 'Request failed');
                        });
                    }

                    return response.json();
                });
            }

            requestPromise
                .then(function () {
                    renderCalendarNotice(
                        calendarWrapper,
                        i18n.bookingMoved || 'Appointment rescheduled.',
                        'success'
                    );

                    if (calendarInstance && typeof calendarInstance.refetchEvents === 'function') {
                        calendarInstance.refetchEvents();
                    }
                })
                .catch(function (error) {
                    if (changeInfo && typeof changeInfo.revert === 'function') {
                        changeInfo.revert();
                    }

                    var previousStart = changeInfo && changeInfo.oldEvent ? toSafeDate(changeInfo.oldEvent.start) : startDate;
                    var previousEnd = changeInfo && changeInfo.oldEvent ? toSafeDate(changeInfo.oldEvent.end) : endDate;
                    var previousStartValue = normalizeTime(previousStart, config.timezone);
                    var previousEndValue = normalizeTime(previousEnd, config.timezone);
                    var previousLabel = formatTimeRangeLabel(previousStartValue, previousEndValue);

                    applyEventTimeUpdates(event, previousStart, previousEnd, previousStartValue, previousEndValue, previousLabel);

                    renderCalendarNotice(
                        calendarWrapper,
                        error && error.message ? error.message : (i18n.bookingMoveError || 'Unable to move appointment. Please try again.'),
                        'error'
                    );
                });
        }

        var calendarInstance = null;

        if (target && hasCalendarLibrary) {
            calendarInstance = EventCalendar.create(target, {
                view: 'resourceTimeGridDay',
                initialDate: state.selectedDate,
                date: state.selectedDate,
                editable: true,
                eventStartEditable: true,
                eventDurationEditable: true,
                eventResizableFromStart: true,
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
                eventChange: onEventChange,
                eventDrop: onEventChange,
                eventResize: onEventChange,
                buttonText: {
                    resourceTimeGridDay: i18n.resourcesView || 'Resources',
                    resourceTimelineDay: i18n.timelineView || 'Timeline',
                },
                eventContent: function (arg) {
                    return buildEventContent(arg, i18n);
                },
            });
        } else if (calendarWrapper) {
            renderEmptyState(calendarWrapper, false);
        }

        if (!calendarInstance || typeof calendarInstance.on !== 'function') {
            renderEmptyState(calendarWrapper, false);
            calendarInstance = null;

            return;
        }

        calendarInstance.on('datesSet', onDatesSet);
        calendarInstance.on('eventChange', onEventChange);
        calendarInstance.on('eventDrop', onEventChange);
        calendarInstance.on('eventResize', onEventChange);

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

        bindBookingFields();

        if (bookingDateInput) {
            bookingDateInput.addEventListener('change', function () {
                bookingContext.date = toDateString(bookingDateInput.value) || bookingContext.date;
            });
        }

        bindCustomerChangeHandlers();

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

        function handleCustomerDialogOpen(event) {
            // Support both delegated and direct bindings even when another handler stops bubbling.
            var target = event && event.target instanceof HTMLElement ? event.target : null;

            if (!target && event && event.currentTarget === addCustomerButton) {
                target = addCustomerButton;
            }

            var trigger = target && typeof target.closest === 'function'
                ? target.closest('#smooth-booking-calendar-add-customer')
                : null;

            if (!trigger) {
                return;
            }

            var now = Date.now();
            var context = {
                eventType: event && event.type ? event.type : '',
                defaultPrevented: !!(event && event.defaultPrevented),
                bookingDialogOpen: !!(bookingDialog && bookingDialog.open),
                customerDialogOpen: !!(customerAccordionBody && !customerAccordionBody.hidden),
            };

            if (customerTriggerTraceEnabled) {
                logConsole('log', 'Smooth Booking debug: customer trigger captured', Object.assign({}, context, {
                    targetId: target ? target.id : '',
                }));
            }

            if (now - customerTriggerLastTimestamp < 300) {
                if (customerTriggerTraceEnabled) {
                    logConsole('log', 'Smooth Booking debug: skipping duplicate customer trigger', Object.assign({}, context, {
                        skipReason: 'duplicate_event',
                    }));
                }
                return;
            }

            customerTriggerLastTimestamp = now;

            // Lazily refresh the dialog reference in case the markup is injected later.
            customerAccordion = customerAccordion || document.getElementById('smooth-booking-calendar-customer-accordion');
            customerAccordionBody = getCustomerAccordionBody();

            if (!customerAccordion || !customerAccordionBody) {
                logConsole('warn', 'Smooth Booking: customer accordion trigger ignored because required elements are missing', {
                    hasTrigger: !!trigger,
                    hasDialog: !!customerAccordion,
                    hasBody: !!customerAccordionBody,
                });
                return;
            }

            if (event && event.defaultPrevented && customerTriggerTraceEnabled) {
                logConsole('warn', 'Smooth Booking: add customer trigger click was defaultPrevented; forcing dialog open', context);
            }

            if (event && typeof event.preventDefault === 'function') {
                event.preventDefault();
            }

            if (event && typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }

            openCustomerAccordion();
        }

        var customerTriggerEvents = ['click', 'pointerdown'];

        if (addCustomerButton) {
            customerTriggerEvents.forEach(function (eventName) {
                addCustomerButton.addEventListener(eventName, handleCustomerDialogOpen, true);
            });
            logConsole('log', 'Smooth Booking: add customer trigger bound directly to button');
        } else {
            logConsole('warn', 'Smooth Booking: add customer button not found; relying on delegated listener only');
        }

        if (!customerAccordion) {
            logConsole('warn', 'Smooth Booking: customer accordion markup was not found in the DOM');
        } else if (!customerAccordionBody) {
            logConsole('warn', 'Smooth Booking: customer accordion body was not found in the DOM');
        }

        customerTriggerEvents.forEach(function (eventName) {
            document.addEventListener(eventName, handleCustomerDialogOpen, { capture: true, passive: false });
        });

        if (customerUserAction) {
            customerUserAction.addEventListener('change', function () {
                toggleCustomerExistingUserField(customerUserAction.value || 'none');
            });

            toggleCustomerExistingUserField(customerUserAction.value || 'none');
        }

        if (customerAccordion) {
            customerAccordion.addEventListener('click', function (event) {
                var target = event.target;
                if (!target || !(target instanceof HTMLElement)) {
                    return;
                }

                if (target.closest('[data-smooth-booking-customer-dismiss]')) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }
                    closeCustomerAccordion();
                    return;
                }

                if (target.classList.contains('smooth-booking-avatar-select')) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }

                    if (!window.wp || !window.wp.media) {
                        return;
                    }

                    var frame = window.wp.media({
                        title: (i18n && i18n.chooseImage) || 'Choose image',
                        button: {
                            text: (i18n && i18n.useImage) || 'Use image',
                        },
                        library: { type: 'image' },
                        multiple: false,
                    });

                    frame.on('select', function () {
                        var attachment = frame.state().get('selection').first().toJSON();
                        updateCustomerAvatar(attachment);
                    });

                    frame.open();
                    return;
                }

                if (target.classList.contains('smooth-booking-avatar-remove')) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault();
                    }
                    resetCustomerAvatar();
                }
            });
        }

        if (customerSubmit) {
            customerSubmit.addEventListener('click', handleCustomerSubmit);
        }

        window.SmoothBookingCalendarDebug = Object.assign({}, window.SmoothBookingCalendarDebug || {}, {
            logCustomerTriggerState: function () {
                var snapshot = {
                    hasAddCustomerButton: !!addCustomerButton,
                    hasCustomerDialog: !!customerAccordion,
                    bookingDialogOpen: !!(bookingDialog && bookingDialog.open),
                    bookingContext: Object.assign({}, bookingContext),
                };

                logConsole('log', 'Smooth Booking debug: customer trigger snapshot', snapshot);

                return snapshot;
            },
            openCustomerDialog: function () {
                return openCustomerAccordion();
            },
            toggleCustomerTriggerTracing: function (enabled) {
                if (typeof enabled === 'undefined') {
                    customerTriggerTraceEnabled = !customerTriggerTraceEnabled;
                } else {
                    customerTriggerTraceEnabled = !!enabled;
                }

                logConsole('log', 'Smooth Booking debug: customer trigger tracing ' + (customerTriggerTraceEnabled ? 'enabled' : 'disabled'));

                return customerTriggerTraceEnabled;
            },
        });

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

        syncBookingEndTime();
        syncBookingStartTime();

        renderEmptyState(calendarWrapper, ensureArray(state.bootstrapEvents).length > 0 || data.hasEvents);
        } catch (error) {
            logConsole('error', 'Smooth Booking: admin calendar failed to initialize', error);
        }
    });
})(window, document);
