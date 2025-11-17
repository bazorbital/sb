(function ($) {
'use strict';

function formatDate(date) {
return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') + ' 00:00:00';
}

$(function () {
var container = $('#smooth-booking-calendar');

if (!container.length) {
return;
}

var employeeField = $('.smooth-booking-employee');
var calendar = new Calendar(container[0], {
language: document.documentElement.lang || 'en',
dataSource: [],
displayWeekNumber: false,
disableDayClick: true,
});

function loadEvents() {
container.addClass('is-loading');
var range = calendar.getVisibleRange();
var data = {
action: 'smooth_booking_calendar',
nonce: SmoothBookingCalendar.nonce,
start: formatDate(range.startDate),
end: formatDate(range.endDate),
employee: parseInt(employeeField.val(), 10) || 0,
};

$.get(SmoothBookingCalendar.ajaxUrl, data)
.done(function (response) {
if (response.success) {
calendar.setDataSource(response.data.events || []);
}
})
.fail(function () {
window.console.error('Unable to load calendar data');
})
.always(function () {
container.removeClass('is-loading');
});
}

calendar.options.renderEnd = loadEvents;

employeeField.on('change', loadEvents);

loadEvents();
});
})(jQuery);
