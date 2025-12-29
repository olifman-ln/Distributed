

document.addEventListener('DOMContentLoaded', function () {
    initializeSearch();
    initializeIPValidation();
    initializeStatusColors();
});

function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.focus();
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.data-table tbody tr');

            rows.forEach(row => {
                if (row.querySelector('.empty-state')) return;

                const text = row.textContent.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
}

function initializeIPValidation() {
    const form = document.querySelector('.form-card');
    if (form) {
        form.addEventListener('submit', function (e) {
            const ipInput = document.getElementById('ip_address');
            if (ipInput) {
                const ip = ipInput.value.trim();
                // Simple IPv4 regex validation
                const ipPattern = /^(\d{1,3}\.){3}\d{1,3}$/;

                if (ip && !ipPattern.test(ip)) {
                    e.preventDefault();
                alert('Please enter a valid IP address format (e.g., 192.168.1.10)');
                    return false;
                }

                // Validate segments
                if (ip) {
                    const parts = ip.split('.');
                    for (let part of parts) {
                        if (parseInt(part) > 255) {
                            e.preventDefault();
                            alert('IP address segments cannot be greater than 255.');
                            return false;
                        }
                    }
                }
            }
            return true;
        });
    }
}

function initializeStatusColors() {

}
