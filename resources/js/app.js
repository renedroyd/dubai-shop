/**
 * Storefront progressive enhancements.
 *
 * Keep catalog navigation server-rendered so filtering remains usable without JavaScript.
 */
document.addEventListener('DOMContentLoaded', () => {
    const catalogForm = document.querySelector('[data-catalog-filters]');

    if (catalogForm) {
        const search = catalogForm.querySelector('input[type="search"]');
        let searchTimer;

        catalogForm.querySelectorAll('select[data-auto-submit]').forEach((select) => {
            select.addEventListener('change', () => catalogForm.requestSubmit());
        });

        if (search) {
            search.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(() => catalogForm.requestSubmit(), 350);
            });
        }
    }

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const message = element.dataset.confirm;

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});
