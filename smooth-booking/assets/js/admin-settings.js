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

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                const target = button.getAttribute('data-section');

                buttons.forEach((btn) => {
                    const isActive = btn === button;
                    btn.classList.toggle('is-active', isActive);
                    btn.setAttribute('aria-current', isActive ? 'true' : 'false');
                });

                sections.forEach((section) => {
                    const isActive = section.getAttribute('data-section') === target;
                    section.classList.toggle('is-active', isActive);
                });
            });
        });
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener('DOMContentLoaded', initSettingsNavigation);
    } else {
        initSettingsNavigation();
    }
})();
