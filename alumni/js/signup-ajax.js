document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("signUpForm");
  const errorBox = form.querySelector(".form-error-box");
  const submitButton = form.querySelector(".sign-up-button");

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    errorBox.innerHTML = "";
    errorBox.style.display = "none";

    // Show simple loading state on the Sign Up button
    let originalText = "Sign Up";
    if (submitButton) {
      originalText = submitButton.textContent;
      submitButton.disabled = true;
      submitButton.textContent = "Processing...";
    }

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
      .catch(() => {
        errorBox.innerHTML = "An unexpected error occurred. Please try again.";
        errorBox.style.display = "block";
      })
      .finally(() => {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalText;
        }
      })
  });
});
