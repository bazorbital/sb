(function (window, document) {
    'use strict';

    function formatDate(date) {
        var month = String(date.getMonth() + 1).padStart(2, '0');
        var day = String(date.getDate()).padStart(2, '0');
        return date.getFullYear() + '-' + month + '-' + day;
    }

    function VanillaCalendar(selector, options) {
        this.container = typeof selector === 'string' ? document.querySelector(selector) : selector;
        this.options = options || {};
        this.current = new Date();
        if (this.options.initialDate) {
            this.current = new Date(this.options.initialDate);
        }
    }

    VanillaCalendar.prototype.init = function () {
        if (!this.container) {
            return;
        }

        this.container.classList.add('vanilla-calendar');
        this.render();
    };

    VanillaCalendar.prototype.render = function () {
        var self = this;
        var activeDate = this.options.selectedDate || this.current;
        var selected = new Date(activeDate.getTime());
        selected.setHours(0, 0, 0, 0);

        this.container.innerHTML = '';

        var header = document.createElement('div');
        header.className = 'vanilla-calendar__header';

        var title = document.createElement('span');
        title.className = 'vanilla-calendar__title';
        title.textContent = selected.toLocaleString(undefined, { month: 'long', year: 'numeric' });
        header.appendChild(title);

        this.container.appendChild(header);

        var grid = document.createElement('div');
        grid.className = 'vanilla-calendar__grid';

        var daysInMonth = new Date(selected.getFullYear(), selected.getMonth() + 1, 0).getDate();

        for (var day = 1; day <= daysInMonth; day++) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'vanilla-calendar__day';
            button.textContent = day;
            var dateValue = new Date(selected.getFullYear(), selected.getMonth(), day);
            button.dataset.date = formatDate(dateValue);

            if (this.options.selectedDate && formatDate(this.options.selectedDate) === button.dataset.date) {
                button.classList.add('is-selected');
            }

            button.addEventListener('click', function (event) {
                var chosen = event.currentTarget.dataset.date;
                self.handleSelection(chosen);
            });

            grid.appendChild(button);
        }

        this.container.appendChild(grid);
    };

    VanillaCalendar.prototype.handleSelection = function (dateString) {
        if (typeof this.options.onSelect === 'function') {
            this.options.onSelect(dateString);
        }
    };

    window.VanillaCalendar = VanillaCalendar;
})(window, document);
