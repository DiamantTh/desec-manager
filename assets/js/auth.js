// Ensure the username field is focused on the login form.

document.addEventListener('DOMContentLoaded', () => {
    const usernameField = document.querySelector('#username');
    if (usernameField && typeof usernameField.focus === 'function') {
        usernameField.focus();
    }
});
