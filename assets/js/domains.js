// Domain helper scripts

document.addEventListener('DOMContentLoaded', () => {
    const deleteForms = document.querySelectorAll('form input[name="action"][value="delete"]');

    deleteForms.forEach((input) => {
        const form = input.closest('form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', (event) => {
            const domainField = form.querySelector('input[name="domain"]');
            const domainName = domainField ? domainField.value : '';
            const question = domainName
                ? `Domain "${domainName}" wirklich löschen?`
                : 'Domain wirklich löschen?';

            if (!window.confirm(question)) {
                event.preventDefault();
            }
        });
    });
});
