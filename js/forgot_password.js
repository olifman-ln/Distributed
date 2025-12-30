

document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('form');
    const emailInput = document.getElementById('email');
    const submitBtn = document.querySelector('.login-btn');

    if (form && emailInput && submitBtn) {
        form.addEventListener('submit', function (e) {
            const email = emailInput.value.trim();


            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (!email) {
                e.preventDefault();
                showError('Please enter your email address.');
                return false;
            }

            if (!emailRegex.test(email)) {
                e.preventDefault();
                showError('Please enter a valid email address.');
                return false;
            }


            setLoadingState(submitBtn, true);
        });
        emailInput.addEventListener('input', function () {
            clearError();
        });
    }
});

function showError(message) {
    clearError();

    // Create error message
    const errorDiv = document.createElement('div');
    errorDiv.className = 'demo-message error js-error';
    errorDiv.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;

    // Insert before the form
    const form = document.querySelector('form');
    const loginBox = document.querySelector('.login-box');
    const logoDiv = document.querySelector('.logo');

    if (loginBox && logoDiv) {
        logoDiv.insertAdjacentElement('afterend', errorDiv);
    } else {
        form.insertAdjacentElement('beforebegin', errorDiv);
    }
}

function clearError() {
    const existingError = document.querySelector('.js-error');
    if (existingError) {
        existingError.remove();
    }
}

function setLoadingState(btn, isLoading) {
    if (isLoading) {
        const icon = btn.querySelector('i');
        const iconClass = icon ? icon.className : 'fas fa-paper-plane';


        btn.dataset.originalHtml = btn.innerHTML;

        // Set loading HTML
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
        btn.disabled = true;
        btn.style.opacity = '0.7';
        btn.style.cursor = 'not-allowed';
    } else {
        // Restore
        if (btn.dataset.originalHtml) {
            btn.innerHTML = btn.dataset.originalHtml;
        }
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
    }
}
