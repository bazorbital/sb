(function () {
    function initSettingsNavigation() {
        const nav = document.querySelector('.smooth-booking-settings-nav');
        const main = document.querySelector('.smooth-booking-settings-main');
        const buttons = nav ? nav.querySelectorAll('.smooth-booking-settings-nav__button') : [];
        const sections = main ? main.querySelectorAll('.smooth-booking-settings-section') : [];

        if ( ! nav || ! main || ! buttons.length || ! sections.length ) {
            return;
        }

        main.classList.add('has-tabs');

        function activateSection(target) {
            if (!target) {
                return;
            }

            buttons.forEach((button) => {
                const isActive = button.getAttribute('data-section') === target;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-current', isActive ? 'true' : 'false');
            });

            sections.forEach((section) => {
                const isActive = section.getAttribute('data-section') === target;
                section.classList.toggle('is-active', isActive);
            });
        }

        const defaultSection = main.getAttribute('data-default-section') || 'general';
        activateSection(defaultSection);

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                activateSection(button.getAttribute('data-section'));
            });
        });
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener('DOMContentLoaded', initSettingsNavigation);
    } else {
        initSettingsNavigation();
    }
})();
