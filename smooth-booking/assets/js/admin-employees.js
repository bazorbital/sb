(function ($) {
    'use strict';

    function closeMenus() {
        $('.smooth-booking-actions-list').attr('hidden', true);
        $('.smooth-booking-actions-toggle').attr('aria-expanded', 'false');
    }

    $(document).on('click', '.smooth-booking-actions-toggle', function (event) {
        event.preventDefault();
        const $toggle = $(this);
        const $menu = $toggle.closest('.smooth-booking-actions-menu').find('.smooth-booking-actions-list');
        const isOpen = !$menu.attr('hidden');

        closeMenus();

        if (!isOpen) {
            $menu.removeAttr('hidden');
            $toggle.attr('aria-expanded', 'true');
        }
    });

    $(document).on('click', function (event) {
        if ($(event.target).closest('.smooth-booking-actions-menu').length === 0) {
            closeMenus();
        }
    });

    $(document).on('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenus();
        }
    });

    $(document).on('submit', '.smooth-booking-delete-form', function (event) {
        const message = (window.SmoothBookingEmployees && window.SmoothBookingEmployees.confirmDelete) || '';
        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
})(jQuery);
