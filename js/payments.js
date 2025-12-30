document.addEventListener('DOMContentLoaded', function () {
    initializeSearch();
    initializeConfirmation();
});

function initializeSearch() {
    const searchInput = document.getElementById('searchInput');

    if (searchInput) {
        searchInput.focus();

        searchInput.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('.payments-table tbody tr');

            rows.forEach(row => {
            const text = row.textContent.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
 checkEmptyState(rows);
        });
    }
}

function initializeConfirmation() {
const payLinks = document.querySelectorAll('a[href*="mark_paid"]');

    payLinks.forEach(link => {
        link.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to mark this payment as PAID? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });
}

function checkEmptyState(rows) {
    let visibleCount = 0;
    rows.forEach(row => {
        if (row.style.display !== 'none') visibleCount++;
    });

    const tableBody = document.querySelector('.payments-table tbody');
    const existingEmpty = document.getElementById('no-results-row');

    if (visibleCount === 0) {
        if (!existingEmpty && tableBody) {
            const emptyRow = document.createElement('tr');
            emptyRow.id = 'no-results-row';
            emptyRow.innerHTML = `
                <td colspan="10" style="text-align: center; padding: 20px; color: #666;">
                    <i class="fas fa-search" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                    No payments found matching your search.
                </td>
            `;
            tableBody.appendChild(emptyRow);
        }
    } else {
        if (existingEmpty) {
            existingEmpty.remove();
        }
    }
}
