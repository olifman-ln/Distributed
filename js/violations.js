
initializeSearch();
initializeSpeedValidation();
initializeFormValidation();
initializeDateDefaults();
function initializeDateDefaults() {
    const dateFrom = document.getElementById('dateFrom');
    const dateTo = document.getElementById('dateTo');

    if (dateFrom && !dateFrom.value) {
        const today = new Date().toISOString().split('T')[0];
        dateFrom.value = today;
        dateTo.value = today;
    }
}

function initializeSearch() {
    // Search functionality
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.focus();
        searchInput.addEventListener('keyup', function () {
            filterTable();
        });
    }
}

function initializeSpeedValidation() {
    // Auto-calculate and validate speed
    const speedInput = document.getElementById('speed_actual');

    if (speedInput) {
        speedInput.addEventListener('blur', function () {
            const speedLimitInput = document.getElementById('speed_limit');
            const speedLimit = speedLimitInput ? speedLimitInput.value : 0;
            const speedActual = this.value;

            if (speedActual && speedLimit && parseInt(speedActual) > parseInt(speedLimit)) {
                this.style.borderColor = '#e74c3c';
                this.style.boxShadow = '0 0 0 3px rgba(231, 76, 60, 0.2)';
            } else {
                this.style.borderColor = '#dee2e6';
                this.style.boxShadow = 'none';
            }
        });
    }
}

function initializeFormValidation() {
    const form = document.querySelector('.form-card');
    if (form) {
        form.addEventListener('submit', function (e) {
            const vehicleId = document.getElementById('vehicle_id').value;
            const cameraId = document.getElementById('camera_id').value;
            const speedActual = document.getElementById('speed_actual').value;
            const speedLimit = document.getElementById('speed_limit').value;

            if (!vehicleId) {
                e.preventDefault();
                alert('Please select a vehicle!');
                return false;
            }

            if (!cameraId) {
                e.preventDefault();
                alert('Please select a camera!');
                return false;
            }

            if (speedActual && speedLimit && parseInt(speedActual) < parseInt(speedLimit)) {
                if (!confirm('Actual speed is below the speed limit. Are you sure this is a speeding violation?')) {
                    e.preventDefault();
                    return false;
                }
            }

            return true;
        });
    }
}
function applyFilters() {
    filterTable();
}

function filterTable() {
    const searchInput = document.getElementById('searchInput');
    const typeFilter = document.getElementById('violationFilter');
    const statusFilter = document.getElementById('statusFilter');
    const dateFromInput = document.getElementById('dateFrom');
    const dateToInput = document.getElementById('dateTo');

    const searchText = searchInput ? searchInput.value.toLowerCase() : '';
    const typeValue = typeFilter ? typeFilter.value.toLowerCase() : '';
    const statusValue = statusFilter ? statusFilter.value.toLowerCase() : '';
    let fromDate = null;
    if (dateFromInput && dateFromInput.value) {
        fromDate = new Date(dateFromInput.value);
        fromDate.setHours(0, 0, 0, 0);
    }

    let toDate = null;
    if (dateToInput && dateToInput.value) {
        toDate = new Date(dateToInput.value);
        toDate.setHours(23, 59, 59, 999);
    }

    const rows = document.querySelectorAll('.data-table tbody tr');
    let visibleCount = 0;

    rows.forEach(row => {

        if (row.querySelector('.empty-state')) return;

        let show = true;

        // 1. Search Text (Plate, Owner, Camera, Node)
        if (searchText) {
            const text = row.textContent.toLowerCase();
            if (!text.includes(searchText)) show = false;
        }

        // 2. Type Filter
        if (show && typeValue) {
            const typeBadge = row.querySelector('.violation-badge');
            if (typeBadge) {
                const badgeText = typeBadge.textContent.toLowerCase().replace(/\s+/g, '_');
                const normalizedText = typeBadge.textContent.trim().toLowerCase().replace(' ', '_');
                if (normalizedText !== typeValue) show = false;
            }
        }
        if (show && statusValue) {
            const statusBadge = row.querySelector('.status-badge');
            if (statusBadge) {
                const statusText = statusBadge.textContent.trim().toLowerCase();
                if (statusText !== statusValue) show = false;
            }
        }

        if (show && (fromDate || toDate)) {
            const timeDiv = row.querySelector('.time-display');
            if (timeDiv) {
                const dateText = timeDiv.childNodes[0].textContent.trim();
                const rowDate = new Date(dateText);

                if (!isNaN(rowDate.getTime())) {
                    if (fromDate && rowDate < fromDate) show = false;
                    if (toDate && rowDate > toDate) show = false;
                }
            }
        }

        row.style.display = show ? '' : 'none';
        if (show) visibleCount++;
    });
    const emptyStateRow = document.querySelector('.data-table tbody tr td .empty-state')?.closest('tr');

}

