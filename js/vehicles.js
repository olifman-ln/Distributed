
document.getElementById('searchInput')?.addEventListener('keyup', function () {
  const filter = this.value.toLowerCase();
  const rows = document.querySelectorAll('.vehicles-table tbody tr');

  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? '' : 'none';
  });
});

// Form validation
document
  .querySelector('.vehicle-form')
  ?.addEventListener('submit', function (e) {
    const plateNumber = document.getElementById('plate_number')?.value.trim();
    const typeId = document.getElementById('type_id')?.value;
    const ownerId = document.getElementById('owner_id')?.value;

    if (!plateNumber) {
      e.preventDefault();
      alert('Plate number is required!');
      return false;
    }

    if (!typeId) {
      e.preventDefault();
      alert('Vehicle type is required!');
      return false;
    }

    if (!ownerId) {
      e.preventDefault();
      alert('Owner selection is required!');
      return false;
    }

    return true;
  });

// Auto-format plate number (optional)
document.getElementById('plate_number')?.addEventListener('blur', function () {
  let value = this.value.toUpperCase().replace(/\s+/g, '');
  // Format as XXX-1234 pattern
  if (value.length >= 3 && /^[A-Z0-9]+$/.test(value)) {
    const letters = value.substring(0, 3);
    const numbers = value.substring(3);
    this.value = letters + (numbers ? '-' + numbers : '');
  }
});

// Focus on search input on page load
document.addEventListener('DOMContentLoaded', function () {
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.focus();
  }
});
