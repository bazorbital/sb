(function () {
    function getWeekdayLabels() {
        const labels = [];
        const locale = document.documentElement.lang || undefined;
        const base = new Date(Date.UTC(2023, 0, 2)); // Monday baseline.

        for (let i = 0; i < 7; i += 1) {
            const date = new Date(base);
            date.setUTCDate(base.getUTCDate() + i);
            labels.push(date.toLocaleDateString(locale, { weekday: 'short' }));
        }

        return labels;
    }

    function createElement(tag, className) {
        const el = document.createElement(tag);

        if (className) {
            el.className = className;
        }

        return el;
    }

    function initHolidays() {
        const root = document.querySelector('[data-smooth-booking-holidays]');

        if (!root) {
            return;
        }

        const config = window.smoothBookingHolidays || {};
        const calendarEl = root.querySelector('[data-holiday-calendar]');
        const yearDisplay = root.querySelector('[data-year-display]');
        const startInput = document.getElementById('smooth-booking-holiday-start');
        const endInput = document.getElementById('smooth-booking-holiday-end');
        const noteInput = document.getElementById('smooth-booking-holiday-note');
        const clearButton = root.querySelector('[data-holiday-clear]');
        const prevButton = root.querySelector('[data-holiday-year-control="previous"]');
        const nextButton = root.querySelector('[data-holiday-year-control="next"]');
        const yearSyncInputs = root.querySelectorAll('[data-year-sync="true"]');

        if (!calendarEl) {
            return;
        }

        let currentYear = parseInt(config.currentYear, 10);

        if (Number.isNaN(currentYear)) {
            currentYear = new Date().getFullYear();
        }

        const localeStrings = config.l10n || {};
        const defaultNote = localeStrings.defaultNote || 'We are not working on this day';
        const recurringLabel = localeStrings.recurringLabel || 'Repeats annually';
        const singleLabel = localeStrings.singleLabel || 'One-time';
        const selectionCleared = localeStrings.selectionCleared || 'Selection cleared.';

        if (noteInput && !noteInput.placeholder) {
            noteInput.placeholder = defaultNote;
        }

        if (clearButton && selectionCleared) {
            clearButton.setAttribute('data-clear-message', selectionCleared);
        }

        const clearButtonOriginalLabel = clearButton ? (clearButton.getAttribute('aria-label') || clearButton.textContent || '') : '';

        const holidays = Array.isArray(config.holidays) ? config.holidays : [];
        const specificDates = new Set();
        const recurringDates = new Set();
        const notesMap = new Map();

        holidays.forEach((holiday) => {
            if (!holiday || typeof holiday.date !== 'string') {
                return;
            }

            const date = holiday.date;
            const note = typeof holiday.note === 'string' ? holiday.note : '';
            const recurring = Boolean(holiday.is_recurring);

            if (recurring) {
                const key = date.slice(5);
                recurringDates.add(key);

                if (note) {
                    notesMap.set(`R-${key}`, note);
                }
            } else {
                specificDates.add(date);

                if (note) {
                    notesMap.set(`S-${date}`, note);
                }
            }
        });

        const weekdayLabels = getWeekdayLabels();
        let dayButtons = [];

        function updateYearOutputs() {
            yearSyncInputs.forEach((input) => {
                input.value = String(currentYear);
            });

            if (yearDisplay) {
                yearDisplay.textContent = String(currentYear);
            }
        }

        function updateSelection() {
            if (!dayButtons.length) {
                dayButtons = Array.from(calendarEl.querySelectorAll('.smooth-booking-holidays__day'));
            }

            const startValue = startInput ? startInput.value : '';
            const endValue = endInput ? endInput.value : '';
            const hasRange = Boolean(startValue) && Boolean(endValue);

            dayButtons.forEach((button) => {
                const date = button.getAttribute('data-date');

                if (!date) {
                    return;
                }

                let isSelected = false;

                if (hasRange) {
                    if (startValue <= endValue) {
                        isSelected = date >= startValue && date <= endValue;
                    } else {
                        isSelected = date >= endValue && date <= startValue;
                    }
                } else if (startValue) {
                    isSelected = date === startValue;
                }

                button.classList.toggle('is-selected-range', isSelected);
                button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
            });
        }

        function renderCalendar() {
            calendarEl.innerHTML = '';
            const monthsWrapper = createElement('div', 'smooth-booking-holidays__months');

            for (let month = 0; month < 12; month += 1) {
                const monthSection = createElement('section', 'smooth-booking-holidays__month');
                const header = createElement('h4', 'smooth-booking-holidays__month-title');
                const monthDate = new Date(currentYear, month, 1);
                header.textContent = monthDate.toLocaleDateString(document.documentElement.lang || undefined, { month: 'long' });
                monthSection.appendChild(header);

                const grid = createElement('div', 'smooth-booking-holidays__grid');
                grid.setAttribute('role', 'grid');

                weekdayLabels.forEach((label) => {
                    const weekdayCell = createElement('span', 'smooth-booking-holidays__weekday');
                    weekdayCell.textContent = label;
                    grid.appendChild(weekdayCell);
                });

                const firstOfMonth = new Date(currentYear, month, 1);
                const firstIndex = (firstOfMonth.getDay() + 6) % 7; // Monday-first index.
                const daysInMonth = new Date(currentYear, month + 1, 0).getDate();

                for (let blank = 0; blank < firstIndex; blank += 1) {
                    grid.appendChild(createElement('span', 'smooth-booking-holidays__cell is-empty'));
                }

                for (let day = 1; day <= daysInMonth; day += 1) {
                    const dateStr = `${currentYear}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const monthDayKey = dateStr.slice(5);
                    const cell = createElement('button', 'smooth-booking-holidays__cell smooth-booking-holidays__day');
                    cell.type = 'button';
                    cell.textContent = String(day);
                    cell.dataset.date = dateStr;
                    cell.setAttribute('aria-pressed', 'false');
                    cell.setAttribute('aria-label', dateStr);

                    const titleParts = [];

                    if (specificDates.has(dateStr)) {
                        cell.classList.add('is-holiday-single');
                        titleParts.push(notesMap.get(`S-${dateStr}`) || singleLabel);
                    }

                    if (recurringDates.has(monthDayKey)) {
                        cell.classList.add('is-holiday-recurring');
                        titleParts.push(notesMap.get(`R-${monthDayKey}`) || recurringLabel);
                    }

                    if (titleParts.length > 0) {
                        cell.title = titleParts.join(' • ');
                    }

                    function handleSelection() {
                        if (!startInput || !endInput) {
                            return;
                        }

                        const startValue = startInput.value;
                        const endValue = endInput.value;

                        if (!startValue || (startValue && endValue)) {
                            startInput.value = dateStr;
                            endInput.value = dateStr;
                        } else if (dateStr < startValue) {
                            endInput.value = startValue;
                            startInput.value = dateStr;
                        } else {
                            endInput.value = dateStr;
                        }

                        updateSelection();
                    }

                    cell.addEventListener('click', () => {
                        handleSelection();
                    });

                    cell.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
                            event.preventDefault();
                            handleSelection();
                        }
                    });

                    grid.appendChild(cell);
                }

                monthSection.appendChild(grid);
                monthsWrapper.appendChild(monthSection);
            }

            calendarEl.appendChild(monthsWrapper);
            dayButtons = Array.from(calendarEl.querySelectorAll('.smooth-booking-holidays__day'));
            updateSelection();
        }

        function changeYear(delta) {
            const nextYear = Math.max(1970, Math.min(2100, currentYear + delta));

            if (nextYear === currentYear) {
                return;
            }

            currentYear = nextYear;
            updateYearOutputs();
            renderCalendar();
        }

        if (clearButton) {
            clearButton.addEventListener('click', () => {
                if (startInput) {
                    startInput.value = '';
                }

                if (endInput) {
                    endInput.value = '';
                }

                updateSelection();

                if (selectionCleared) {
                    clearButton.setAttribute('aria-label', selectionCleared);
                    setTimeout(() => {
                        if (clearButtonOriginalLabel) {
                            clearButton.setAttribute('aria-label', clearButtonOriginalLabel);
                        } else {
                            clearButton.removeAttribute('aria-label');
                        }
                    }, 1000);
                }
            });
        }

        if (prevButton) {
            prevButton.addEventListener('click', () => changeYear(-1));
        }

        if (nextButton) {
            nextButton.addEventListener('click', () => changeYear(1));
        }

        updateYearOutputs();
        renderCalendar();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initHolidays);
    } else {
        initHolidays();
    }
})();
