(function ($) {
    function initNotifications() {
        const scopeSelect = document.getElementById('smooth-booking-notification-service-scope');
        const servicesWrapper = document.querySelector('.smooth-booking-notification-services-select');
        const recipients = document.querySelectorAll('input[name="notification_recipients[]"]');
        const customContainer = document.querySelector('.smooth-booking-notification-custom-emails');
        const codesToggle = document.querySelector('.smooth-booking-toggle-codes');
        const codesPanel = document.getElementById('smooth-booking-notification-codes');

        if (typeof $.fn.select2 === 'function') {
            $('.smooth-booking-select2').select2({ width: '100%' });
        }

        function toggleServices(value) {
            if (!servicesWrapper) {
                return;
            }

            if ('selected' === value) {
                servicesWrapper.classList.add('is-visible');
            } else {
                servicesWrapper.classList.remove('is-visible');
            }
        }

        if (scopeSelect) {
            toggleServices(scopeSelect.value);
            scopeSelect.addEventListener('change', function () {
                toggleServices(this.value);
            });
        }

        function toggleCustomEmails() {
            if (!customContainer) {
                return;
            }

            let hasCustom = false;
            recipients.forEach(function (input) {
                if ('custom' === input.value && input.checked) {
                    hasCustom = true;
                }
            });

            if (hasCustom) {
                customContainer.classList.add('is-visible');
            } else {
                customContainer.classList.remove('is-visible');
            }
        }

        if (recipients.length) {
            toggleCustomEmails();
            recipients.forEach(function (input) {
                input.addEventListener('change', toggleCustomEmails);
            });
        }

        if (codesToggle && codesPanel) {
            codesToggle.addEventListener('click', function (event) {
                event.preventDefault();

                const isHidden = codesPanel.hasAttribute('hidden');

                if (isHidden) {
                    codesPanel.removeAttribute('hidden');
                    codesToggle.textContent = (window.SmoothBookingNotifications && window.SmoothBookingNotifications.hideCodes) || codesToggle.textContent;
                } else {
                    codesPanel.setAttribute('hidden', 'hidden');
                    codesToggle.textContent = (window.SmoothBookingNotifications && window.SmoothBookingNotifications.showCodes) || codesToggle.textContent;
                }
            });
        }
    }

    if ('loading' === document.readyState) {
        document.addEventListener('DOMContentLoaded', initNotifications);
    } else {
        initNotifications();
    }
})(jQuery);
