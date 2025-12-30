
var trafficVolumeOptions = {
  chart: {
    type: 'area',
    height: '100%',
    toolbar: { show: true },
    zoom: { enabled: true },
  },
  series: [
    {
      name: 'Violations',
      data: window.trafficVolumeData || [], // placeholder, set from PHP below
    },
  ],
  colors: ['#3498db'],
  stroke: { curve: 'smooth', width: 3 },
  fill: {
    type: 'gradient',
    gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.3, stops: [0, 90, 100] },
  },
  xaxis: {
    categories: [
      '00:00',
      '01:00',
      '02:00',
      '03:00',
      '04:00',
      '05:00',
      '06:00',
      '07:00',
      '08:00',
      '09:00',
      '10:00',
      '11:00',
      '12:00',
      '13:00',
      '14:00',
      '15:00',
      '16:00',
      '17:00',
      '18:00',
      '19:00',
      '20:00',
      '21:00',
      '22:00',
      '23:00',
    ],
  },
  yaxis: { title: { text: 'Number of Violations' } },
  tooltip: { x: { format: 'HH:mm' } },
};

var trafficVolumeChart = new ApexCharts(
  document.querySelector('#trafficVolumeChart'),
  trafficVolumeOptions
);
trafficVolumeChart.render();
var violationsByNodeOptions = {
  chart: { type: 'bar', height: '100%' },
  series: [
    {
      name: 'Violations',
      data: window.violationsByNodeData || [],
    },
  ],
  colors: ['#2ecc71'],
  plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
  dataLabels: { enabled: true },
  xaxis: {
    categories: window.violationsByNodeLabels || [],
  },
  yaxis: { title: { text: 'Number of Violations' } },
};

var violationsByNodeChart = new ApexCharts(
  document.querySelector('#violationsByNodeChart'),
  violationsByNodeOptions
);
violationsByNodeChart.render();
document.querySelectorAll('.time-range-btn').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('.time-range-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');

    console.log('Time range changed to:', this.textContent);

  });
})
document.querySelector('.btn-export').addEventListener('click', function () {
  alert('Export functionality would generate a traffic report (CSV/PDF).');
});
