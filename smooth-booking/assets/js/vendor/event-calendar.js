/*!
 * EventCalendar integration layer tailored for Smooth Booking.
 * Inspired by https://github.com/vkurko/calendar (MIT licensed).
 */
(function (window, document) {
    'use strict';

    if (!window) {
        return;
    }

    function createElement(tag, className) {
        var element = document.createElement(tag);
        if (className) {
            element.className = className;
        }
        return element;
    }

    function ensureArray(value) {
        return Array.isArray(value) ? value : [];
    }

    function replacePlaceholders(template, slot, resourceTitle) {
        if (!template || typeof template !== 'string') {
            return '';
        }

        return template.replace('%1$s', slot).replace('%2$s', resourceTitle);
    }

    function EventCalendar(target, options) {
        this.element = typeof target === 'string' ? document.querySelector(target) : target;
        if (!this.element) {
            return;
        }

        this.options = Object.assign(
            {
                slots: [],
                resources: [],
                events: [],
                labels: {},
                onTimeSlotClick: null,
                onEventClick: null
            },
            options || {}
        );

        this.render();
    }

    EventCalendar.prototype.render = function () {
        var slots = ensureArray(this.options.slots);
        var resources = ensureArray(this.options.resources);
        var events = ensureArray(this.options.events);
        var slotCount = slots.length || 1;
        var self = this;

        this.element.innerHTML = '';
        this.element.classList.add('smooth-booking-calendar-grid');
        this.element.style.gridTemplateColumns = 'minmax(120px, 160px) repeat(' + resources.length + ', minmax(220px, 1fr))';

        var timesColumn = createElement('div', 'smooth-booking-calendar-column smooth-booking-calendar-column--times');
        timesColumn.style.gridTemplateRows = 'repeat(' + slotCount + ', var(--sbc-slot-height))';
        slots.forEach(function (slot) {
            var timeCell = createElement('div', 'smooth-booking-calendar-time');
            timeCell.setAttribute('aria-hidden', 'true');
            timeCell.textContent = slot;
            timesColumn.appendChild(timeCell);
        });
        this.element.appendChild(timesColumn);

        resources.forEach(function (resource) {
            var column = createElement('div', 'smooth-booking-calendar-column');
            column.setAttribute('data-employee-id', String(resource.id));

            var header = createElement('div', 'smooth-booking-calendar-column__header');
            var title = createElement('span', 'smooth-booking-calendar-column__title');
            title.textContent = resource.title || '';
            header.appendChild(title);
            column.appendChild(header);

            var body = createElement('div', 'smooth-booking-calendar-column__body');
            body.style.gridTemplateRows = 'repeat(' + slotCount + ', var(--sbc-slot-height))';

            slots.forEach(function (slot) {
                var button = createElement('button', 'smooth-booking-calendar-slot');
                button.type = 'button';
                button.dataset.slot = String(slot);
                button.dataset.employee = String(resource.id);
                var ariaLabel = replacePlaceholders(
                    self.options.labels && self.options.labels.slotAria,
                    slot,
                    resource.title || ''
                );
                if (ariaLabel) {
                    button.setAttribute('aria-label', ariaLabel);
                }

                button.addEventListener('click', function (event) {
                    if (typeof self.options.onTimeSlotClick === 'function') {
                        self.options.onTimeSlotClick({
                            slot: slot,
                            resourceId: resource.id,
                            resource: resource,
                            originalEvent: event
                        });
                    }
                });

                body.appendChild(button);
            });

            column.appendChild(body);
            self.element.appendChild(column);
        });

        events.forEach(function (eventData) {
            if (!eventData || typeof eventData.resourceId === 'undefined') {
                return;
            }

            var selector = '[data-employee-id="' + String(eventData.resourceId) + '"] .smooth-booking-calendar-column__body';
            var columnBody = self.element.querySelector(selector);
            if (!columnBody) {
                return;
            }

            var appointment = self.renderEvent(eventData);
            columnBody.appendChild(appointment);
        });
    };

    EventCalendar.prototype.renderEvent = function (eventData) {
        var appointment = createElement('div', 'smooth-booking-calendar-appointment');
        appointment.dataset.employee = String(eventData.resourceId || '');
        appointment.dataset.appointment = String(eventData.id || '');

        var borderColor = eventData.color || '#2271b1';
        appointment.style.borderColor = borderColor;

        if (typeof eventData.startIndex === 'number' && typeof eventData.span === 'number') {
            appointment.style.gridRow = String(eventData.startIndex + 1) + ' / span ' + String(eventData.span);
        }

        var service = createElement('span', 'smooth-booking-calendar-appointment__service');
        service.style.backgroundColor = borderColor;
        service.textContent = eventData.service || '';
        appointment.appendChild(service);

        var time = createElement('span', 'smooth-booking-calendar-appointment__time');
        time.textContent = eventData.timeLabel || '';
        appointment.appendChild(time);

        var customerName = eventData.customer && eventData.customer.name ? eventData.customer.name : '';
        var customer = createElement('span', 'smooth-booking-calendar-appointment__customer');
        customer.textContent = customerName;
        appointment.appendChild(customer);

        var contactWrapper = createElement('span', 'smooth-booking-calendar-appointment__contact');
        if (eventData.customer && eventData.customer.phone) {
            var phone = document.createElement('span');
            phone.textContent = eventData.customer.phone;
            contactWrapper.appendChild(phone);
        }
        if (eventData.customer && eventData.customer.email) {
            var email = document.createElement('span');
            email.textContent = eventData.customer.email;
            contactWrapper.appendChild(email);
        }
        appointment.appendChild(contactWrapper);

        if (eventData.status) {
            var status = createElement('span', 'smooth-booking-calendar-appointment__status');
            status.textContent = eventData.status;
            appointment.appendChild(status);
        }

        var actions = createElement('div', 'smooth-booking-calendar-appointment__actions');

        if (eventData.editUrl) {
            var editLink = document.createElement('a');
            editLink.className = 'button button-small';
            editLink.href = eventData.editUrl;
            editLink.textContent = eventData.editLabel || 'Edit';
            actions.appendChild(editLink);
        }

        if (eventData.delete && eventData.delete.endpoint) {
            var form = document.createElement('form');
            form.className = 'smooth-booking-calendar-appointment__delete';
            form.method = 'post';
            form.action = eventData.delete.endpoint;

            var actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = eventData.delete.action || '';
            form.appendChild(actionInput);

            var nonceInput = document.createElement('input');
            nonceInput.type = 'hidden';
            nonceInput.name = '_wpnonce';
            nonceInput.value = eventData.delete.nonce || '';
            form.appendChild(nonceInput);

            var idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'appointment_id';
            idInput.value = String(eventData.delete.appointmentId || '');
            form.appendChild(idInput);

            var refererInput = document.createElement('input');
            refererInput.type = 'hidden';
            refererInput.name = '_wp_http_referer';
            refererInput.value = eventData.delete.referer || '';
            form.appendChild(refererInput);

            var deleteButton = document.createElement('button');
            deleteButton.type = 'submit';
            deleteButton.className = 'button button-small button-link-delete';
            deleteButton.textContent = eventData.delete.label || 'Delete';
            form.appendChild(deleteButton);

            actions.appendChild(form);
        }

        appointment.appendChild(actions);

        var self = this;
        appointment.addEventListener('click', function (event) {
            if (typeof self.options.onEventClick === 'function') {
                self.options.onEventClick({
                    event: eventData,
                    originalEvent: event
                });
            }
        });

        return appointment;
    };

    window.EventCalendar = EventCalendar;
})(window, document);
