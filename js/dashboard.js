document.addEventListener('DOMContentLoaded', function () {
  initCharts();
  loadNodeStatus();
  loadTrafficChart('24h');
  startRealTimeUpdates();
  initThemeToggle();
  initVehicleSearchModal();
});
function initThemeToggle() {
  const btn = document.getElementById('themeToggle');
  btn.addEventListener('click', () => {
    document.body.classList.toggle('dark-theme');
    btn.querySelector('i').classList.toggle('fa-moon');
    btn.querySelector('i').classList.toggle('fa-sun');
  });
}
function loadNodeStatus() {
  fetch('api/node_status.php')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        const nodeHealth =
          data.onlineNodes && data.totalNodes
            ? Math.round((data.onlineNodes / data.totalNodes) * 100)
            : 0;
        document.querySelector('.system-status .status-dot').className =
          'status-dot ' +
          (nodeHealth > 80
            ? 'online'
            : nodeHealth > 50
              ? 'warning'
              : 'offline');
        document.querySelector(
          '.system-status span:last-child'
        ).textContent = `Nodes Health: ${nodeHealth}%`;
      }
    })
    .catch(err => console.error('Node status error:', err));
}
let trafficChart;
function initCharts() {
  const chartEl = document.querySelector('#trafficChart');
  if (!chartEl) return;

  trafficChart = new ApexCharts(chartEl, {
    chart: {
      type: 'line',
      height: 300,
      toolbar: { show: false },
    },
    series: [
      {
        name: 'Vehicles Detected',
        data: [],
      },
    ],
    xaxis: { categories: [] },
    stroke: { curve: 'smooth' },
    colors: ['#1E90FF'],
    tooltip: { theme: 'dark' },
  });
  trafficChart.render();
}

function loadTrafficChart(range = '24h') {
  fetch(`api/traffic_chart.php?range=${range}`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        trafficChart.updateOptions({
          xaxis: { categories: data.categories },
          series: [{ name: 'Vehicles Detected', data: data.values }],
        });
      }
    })
    .catch(err => console.error('Traffic chart error:', err));
}

function updateTrafficChart(range) {
  loadTrafficChart(range);
}

// -------------------------
// REAL-TIME UPDATES
// -------------------------
function startRealTimeUpdates() {
  setInterval(() => {
    loadNodeStatus();
    loadTrafficChart(document.querySelector('.chart-filter')?.value || '24h');

  }, 30000);
}
function initVehicleSearchModal() {
  const searchModal = document.getElementById('vehicleSearchModal');
  const searchForm = document.getElementById('searchVehicleForm');
  if (!searchForm) return;

  searchForm.addEventListener('submit', function (e) {
    e.preventDefault();
    const plate = document.getElementById('searchPlate').value.trim();
    const owner = document.getElementById('searchOwner').value.trim();
    searchVehicle(plate, owner);
  });

  const closeBtn = searchModal.querySelector('.modal-close');
  closeBtn.addEventListener(
    'click',
    () => (searchModal.style.display = 'none')
  );
  window.addEventListener('click', e => {
    if (e.target === searchModal) searchModal.style.display = 'none';
  });
}

function searchVehicle(plate, owner) {
  const resultsDiv = document.getElementById('searchResults');
  resultsDiv.innerHTML = '<div class="loading">Searching...</div>';
  fetch(
    `api/search_vehicle.php?plate=${encodeURIComponent(
      plate
    )}&owner=${encodeURIComponent(owner)}`
  )
    .then(res => res.json())
    .then(data => {
      if (data.success && data.vehicles.length) {
        let html = '<div class="results-list">';
        data.vehicles.forEach(vehicle => {
          html += `
<div class="result-item">
<h4>${vehicle.plate_number}</h4>
<p>${vehicle.type_name} • ${vehicle.model || 'Unknown'} • ${vehicle.color || 'Unknown'
            }</p>
<p><i class="fas fa-user"></i> ${vehicle.full_name || 'Unknown Owner'}</p>
<a href="vehicles.php?id=${vehicle.id}" class="btn-view">View Details</a>
</div>`;
        });
        html += '</div>';
        resultsDiv.innerHTML = html;
      } else {
        resultsDiv.innerHTML =
          '<div class="no-results">No vehicles found</div>';
      }
    })
    .catch(err => {
      console.error('Vehicle search error:', err);
      resultsDiv.innerHTML = '<div class="error">Search error</div>';
    });
}

function openVehicleSearch() {
  document.getElementById('vehicleSearchModal').style.display = 'block';
}
