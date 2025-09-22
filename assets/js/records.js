// DNS record management helpers

document.addEventListener('DOMContentLoaded', () => {

    document.querySelectorAll('.js-inline-toggle').forEach((button) => {
        button.addEventListener('click', () => {
            const container = button.closest('td');
            if (!container) return;
            const form = container.querySelector('.rrset-inline-form');
            if (!form) return;

            if (!document.body.classList.contains('inline-editor-enabled')) {
                if (typeof showNotification === 'function') {
                    showNotification('Inline-Editor ist deaktiviert. Aktivieren Sie den Schalter im Footer.', 'is-info');
                }
                return;
            }

            form.classList.remove('is-hidden');
            button.classList.add('is-hidden');
        });
    });

    document.querySelectorAll('.js-inline-cancel').forEach((button) => {
        button.addEventListener('click', () => {
            const form = button.closest('.rrset-inline-form');
            if (!form) return;
            form.classList.add('is-hidden');
            const toggle = form.closest('td')?.querySelector('.js-inline-toggle');
            toggle?.classList.remove('is-hidden');
        });
    });

    document.querySelectorAll('.js-delete-form').forEach((form) => {
        form.addEventListener('submit', (event) => {
            const subname = form.closest('.rrset-row')?.getAttribute('data-subname') || '';
            const type = form.closest('.rrset-row')?.getAttribute('data-type') || '';
            const label = subname === '' ? '@' : subname;
            const message = `RRset ${label} ${type} wirklich löschen?`;
            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });
});
