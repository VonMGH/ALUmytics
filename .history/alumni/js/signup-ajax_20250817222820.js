document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("signUpForm");
  const errorBox = form.querySelector(".form-error-box");

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    errorBox.innerHTML = "";
    errorBox.style.display = "none";

    const formData = new FormData(form);
    fetch("signup.php", {
      method: "POST",
      body: formData,
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          window.location.href = data.redirect;
        } else {
          errorBox.innerHTML = data.error ? data.error : "Registration failed.";
          errorBox.style.display = "block";
        }
      })
  });
});
