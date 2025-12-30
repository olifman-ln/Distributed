document.addEventListener('DOMContentLoaded', function () {
    const typeSelect = document.getElementById('typeSelect');
    if (typeSelect) {
        toggleReference();
        typeSelect.addEventListener('change', toggleReference);
    }
});

function toggleReference() {
    const typeSelect = document.getElementById('typeSelect');
    const referenceSelect = document.getElementById('referenceSelect');

    if (!typeSelect || !referenceSelect) return;

    const type = typeSelect.value;


    referenceSelect.innerHTML = '<option value="">Select</option>';
    let data = [];
    if (type === 'violation' && typeof window.violationsData !== 'undefined') {
        data = window.violationsData;
    } else if (type === 'accident' && typeof window.accidentsData !== 'undefined') {
        data = window.accidentsData;
    }
    data.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.id;

        if (type === 'violation') {
            opt.textContent = (item.plate_number || 'Unknown') + " - " + (item.violation_type || 'Unknown');
        } else {
            opt.textContent = (item.plate_number || 'Unknown') + " - " + (item.severity || 'Unknown');
        }

        referenceSelect.appendChild(opt);
    });
}
