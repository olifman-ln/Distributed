const ownerForm = document.querySelector('.owner-form form');
const ownersTable = document.querySelector('.table-container table tbody');

document.addEventListener('DOMContentLoaded', function () {
  const searchInput = document.querySelector('input[name="search"]');
  if (searchInput && !searchInput.value) {
    searchInput.focus();
  }

  if (window.editId) {
    const editRow = document.querySelector(`a[href*="edit=${window.editId}"]`)?.closest('tr');
    if (editRow) {
      editRow.style.backgroundColor = '#fffde7';
      editRow.scrollIntoView({
        behavior: 'smooth',
        block: 'center'
      });
    }
  }
});

let formMessage = document.getElementById('formMessage');
if (!formMessage) {
  formMessage = document.createElement('div');
  formMessage.id = 'formMessage';
  formMessage.style.margin = '10px 0';
  formMessage.style.color = 'red';
  ownerForm.prepend(formMessage);
}

ownerForm.addEventListener('submit', function (e) {
  e.preventDefault();

  const formData = new FormData(ownerForm);

  formMessage.textContent = '⏳ Saving owner...';
  formMessage.style.color = '#555';

  fetch('owners.php', {
    method: 'POST',
    body: formData,
  })
    .then(res => res.text())
    .then(data => {
      fetch('owners.php')
        .then(res => res.text())
        .then(html => {
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, 'text/html');
          const newTbody = doc.querySelector('.table-container table tbody');

          if (newTbody) {
            ownersTable.innerHTML = newTbody.innerHTML;
            formMessage.style.color = 'green';
            formMessage.textContent = '✅ Owner added successfully!';
            ownerForm.reset();
          }
        });
    })
    .catch(err => {
      console.error(err);
      formMessage.style.color = 'red';
      formMessage.textContent = '❌ Error saving owner';
    });
});
