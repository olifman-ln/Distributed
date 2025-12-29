// Search functionality
document.getElementById('searchInput')?.addEventListener('keyup', function () {
  const filter = this.value.toLowerCase();
  const rows = document.querySelectorAll('.data-table tbody tr');

  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(filter) ? '' : 'none';
  });
});

// Apply filters
function applyFilters() {
  const typeFilter = document.getElementById('typeFilter').value;
  const severityFilter = document.getElementById('severityFilter').value;
  const dateFrom = document.getElementById('dateFrom').value;
  const dateTo = document.getElementById('dateTo').value;

  // In a real application, this would make an AJAX request or reload the page with filters
  alert(
    'Filter functionality would be implemented here.\nFilters applied:\n' +
      'Type: ' +
      (typeFilter || 'All') +
      '\n' +
      'Severity: ' +
      (severityFilter || 'All') +
      '\n' +
      'Date Range: ' +
      (dateFrom || 'Any') +
      ' to ' +
      (dateTo || 'Any')
  );
}

// Form validation
document.querySelector('.form-card')?.addEventListener('submit', function (e) {
  const vehicleId = document.getElementById('vehicle_id').value;
  const cameraId = document.getElementById('camera_id').value;
  const type = document.getElementById('type').value;
  const severity = document.getElementById('severity').value;
  const description = document.getElementById('description').value.trim();

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

  if (!description) {
    e.preventDefault();
    alert('Please provide a description of the accident!');
    return false;
  }

  if (severity === 'high' && type !== 'pedestrian_hit') {
    if (!confirm('This is marked as High Severity. Are you sure this is not a pedestrian hit?')) {
      return true;
    }
  }

  return true;
});


document.getElementById('severity')?.addEventListener('change', function () {
  if (this.value === 'high') {
    document.getElementById('description').focus();
  }
});

// Focus on search input on page load
document.addEventListener('DOMContentLoaded', function () {
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.focus();
  }

  // Set default date filters to today
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('dateFrom').value = today;
  document.getElementById('dateTo').value = today;
});
