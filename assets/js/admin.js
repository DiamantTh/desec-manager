// Admin helper scripts

document.addEventListener('DOMContentLoaded', () => {
    const passwordField = document.querySelector('#password');
    if (passwordField) {
        passwordField.addEventListener('focus', () => {
            passwordField.select();
        });
    }
});
