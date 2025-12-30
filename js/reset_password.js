
function toggleInput(id, btn) {
    const input = document.getElementById(id);
    if (!input) return;

    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);

    const icon = btn.querySelector('i');
    if (icon) {
        icon.classList.toggle('fa-eye');
        icon.classList.toggle('fa-eye-slash');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const toggles = document.querySelectorAll('.toggle-password');

    toggles.forEach(btn => {
        btn.removeAttribute('onclick');
        btn.addEventListener('click', function () {
            const container = this.closest('.password-container');
            const input = container.querySelector('input');
            if (input) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'passwor'; input.setAttribute('type', type);

                const icon = this.querySelector('i');
                if (icon) {
                    icon.classList.toggle('fa-eye');
                    icon.classList.toggle('fa-eye-slash');
                }
            }
        });
    });
});
