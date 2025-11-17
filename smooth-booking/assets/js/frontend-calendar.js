(function () {
'use strict';

function parseDate(date, endOfDay) {
var time = endOfDay ? ' 23:59:59' : ' 00:00:00';
return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') + time;
}

function fetchEvents(range, employeeId) {
return window.wp.apiFetch({
path: '/smooth-booking/v1/calendar?start=' + encodeURIComponent(parseDate(range.startDate)) + '&end=' + encodeURIComponent(parseDate(range.endDate, true)) + '&employee=' + employeeId,
headers: {
'X-WP-Nonce': SmoothBookingFrontend.nonce,
},
});
}

document.addEventListener('DOMContentLoaded', function () {
var nodes = document.querySelectorAll('.smooth-booking-calendar');

if (!nodes.length) {
return;
}

nodes.forEach(function (node) {
var employee = parseInt(node.getAttribute('data-employee') || '0', 10);
var calendar = new Calendar(node, {
language: document.documentElement.lang || 'en',
displayWeekNumber: false,
dataSource: [],
disableDayClick: true,
});

function load() {
node.classList.add('is-loading');
var range = calendar.getVisibleRange();
fetchEvents(range, employee)
.then(function (response) {
calendar.setDataSource(response.events || []);
var emptyNotice = node.querySelector('.sb-calendar-empty');
if (!response.events || !response.events.length) {
node.setAttribute('aria-live', 'polite');
if (!emptyNotice) {
emptyNotice = document.createElement('p');
emptyNotice.className = 'sb-calendar-empty';
emptyNotice.textContent = SmoothBookingFrontend.i18n.empty;
node.appendChild(emptyNotice);
}
} else if (emptyNotice) {
emptyNotice.remove();
}
})
.catch(function (error) {
window.console.error(error);
})
.finally(function () {
node.classList.remove('is-loading');
});
}

calendar.options.renderEnd = load;
load();
});
});
})();
