document.addEventListener('DOMContentLoaded', function () {
    initializeSearch();
    initializeViewToggle();
});

function initializeSearch() {
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.focus();
        searchInput.addEventListener('keyup', function () {
            const filter = this.value.toLowerCase();
            const cards = document.querySelectorAll('.node-card');

            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(filter)) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });

            // Also filter table rows if in list view (if implementation exists)
            const rows = document.querySelectorAll('.data-table tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
}

function initializeViewToggle() {
    const gridBtn = document.getElementById('btn-grid-view');
    const listBtn = document.getElementById('btn-list-view');
    const gridContainer = document.querySelector('.nodes-grid');
    const listContainer = document.querySelector('.nodes-list');
    if (gridBtn && listBtn) {
        gridBtn.addEventListener('click', function () {
            gridBtn.classList.add('active');
            listBtn.classList.remove('active');

            if (gridContainer) gridContainer.classList.remove('list-view');
            // Logic to show grid / hide list
        });

        listBtn.addEventListener('click', function () {
            listBtn.classList.add('active');
            gridBtn.classList.remove('active');

            if (gridContainer) gridContainer.classList.add('list-view');

        });
    }
}
