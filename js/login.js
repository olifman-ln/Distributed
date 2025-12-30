document.addEventListener("DOMContentLoaded", function () {
  const loginForm = document.getElementById("loginForm");
  const usernameInput = document.getElementById("username");
  const emailInput = document.getElementById("email");
  const passwordInput = document.getElementById("password");
  const toggleBtn = document.getElementById("togglePassword");
  const demoBtn = document.getElementById("demoBtn");

  // ------------------------
  // Toggle Password
  // ------------------------
  if (toggleBtn && passwordInput) {
    toggleBtn.addEventListener("click", function (e) {
      e.preventDefault();
      const icon = toggleBtn.querySelector("i");
      if (passwordInput.type === "password") {
        passwordInput.type = "text";
        icon.className = "fas fa-eye-slash";
      } else {
        passwordInput.type = "password";
        icon.className = "fas fa-eye";
      }
    });
  }

  // ------------------------
  // Demo Credentials Fill
  // ------------------------
  if (demoBtn) {
    demoBtn.addEventListener("click", function () {
      usernameInput.value = "admin";
      emailInput.value = "admin@trafficsense.local";
      passwordInput.value = "admin123";
      showMessage("Demo credentials loaded!", "success");
    });
  }

  if (loginForm) {
    loginForm.addEventListener("submit", function (e) {
      let error = "";

      if (!usernameInput.value.trim()) {
        error = "Please enter username";
        usernameInput.focus();
      } else if (!emailInput.value.trim()) {
        error = "Please enter email";
        emailInput.focus();
      } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailInput.value.trim())) {
        error = "Please enter a valid email";
        emailInput.focus();
      } else if (!passwordInput.value) {
        error = "Please enter password";
        passwordInput.focus();
      }

      if (error) {
        e.preventDefault();
        showMessage(error, "error");
      }
    });
  }

  function showMessage(text, type) {
    const existing = document.querySelector(".demo-message");
    if (existing) existing.remove();

    const message = document.createElement("div");
    message.className = `demo-message ${type}`;
    message.innerHTML = `<i class="fas fa-info-circle"></i> ${text}`;

    const loginBox = document.querySelector(".login-box");
    const form = document.querySelector("#loginForm");
    if (loginBox && form) loginBox.insertBefore(message, form);

    setTimeout(() => {
      message.style.opacity = "0";
      setTimeout(() => message.remove(), 300);
    }, 4000);
  }

  window.showMessage = showMessage;

  const serverError = document.querySelector(".error");
  if (serverError) {
    setTimeout(() => {
      serverError.style.opacity = "0";
      setTimeout(() => serverError.remove(), 300);
    }, 5000);
  }

  if (usernameInput) usernameInput.focus();
});
