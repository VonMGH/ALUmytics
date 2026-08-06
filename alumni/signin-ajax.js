document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("signInForm");
  if (!form) return;
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => {
      data[key] = value;
    });
    fetch("", {
      method: "POST",
      headers: { "X-Requested-With": "XMLHttpRequest" },
      body: formData,
    })
      .then((response) => response.json())
      .then((res) => {
        if (res.success) {
          window.location.href = res.redirect;
        } else {
          const errorBox = document.querySelector(".form-error-box");
          if (errorBox) {
            errorBox.textContent =
              res.error || "Login failed. Please try again.";
            errorBox.style.display = "block";
          }
        }
      })
      .catch(() => {
        const errorBox = document.querySelector(".form-error-box");
        if (errorBox) {
          errorBox.textContent = "An error occurred. Please try again.";
          errorBox.style.display = "block";
        }
      });
  });
});
