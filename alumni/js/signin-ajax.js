document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("signInForm");
  const errorBox = form.querySelector(".form-error-box");
  const passwordField = document.getElementById("password");
  const passwordToggle = document.getElementById("passwordToggle");
  const eyeIcon = document.getElementById("eyeIcon");

  // Password toggle functionality
  if (passwordToggle && passwordField && eyeIcon) {
    passwordToggle.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const isPassword = passwordField.type === "password";

      // Toggle password visibility
      passwordField.type = isPassword ? "text" : "password";

      // Toggle eye icon
      eyeIcon.className = isPassword ? "fas fa-eye-slash" : "fas fa-eye";

      // Update aria-label for accessibility
      passwordToggle.setAttribute(
        "aria-label",
        isPassword ? "Hide password" : "Show password"
      );

      // Keep focus on password field
      passwordField.focus();
    });

    // Ensure toggle is always visible and properly positioned
    passwordToggle.style.display = "flex";
    passwordToggle.style.opacity = "1";
    passwordToggle.style.visibility = "visible";

    // Add keyboard accessibility
    passwordToggle.addEventListener("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        passwordToggle.click();
      }
    });
  }

  // Form submission with AJAX
  if (form) {
    form.addEventListener("submit", function (e) {
      e.preventDefault();

      // Hide previous errors
      errorBox.innerHTML = "";
      errorBox.style.display = "none";

      // Create FormData object
      const formData = new FormData(form);

      // Add loading state to submit button
      const submitButton = form.querySelector(".sign-up-button");
      const originalText = submitButton.textContent;
      submitButton.textContent = "Signing In...";
      submitButton.disabled = true;

      // Send AJAX request
      fetch("signin.php", {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            // Success - redirect to specified page
            submitButton.textContent = "Success!";
            setTimeout(() => {
              window.location.href = data.redirect;
            }, 500);
          } else {
            // Error - show error message
            errorBox.innerHTML =
              data.error || "Sign in failed. Please try again.";
            errorBox.style.display = "block";

            // Reset submit button
            submitButton.textContent = originalText;
            submitButton.disabled = false;

            // Focus on first field for retry
            const emailField = form.querySelector('input[name="email"]');
            if (emailField) emailField.focus();
          }
        })
        .catch((error) => {
          console.error("Sign in error:", error);
          errorBox.innerHTML =
            "An unexpected error occurred. Please try again.";
          errorBox.style.display = "block";

          // Reset submit button
          submitButton.textContent = originalText;
          submitButton.disabled = false;
        });
    });
  }

  // Add smooth focus transitions for form fields
  const inputs = form.querySelectorAll("input");
  inputs.forEach((input) => {
    input.addEventListener("focus", function () {
      this.parentElement.classList.add("focused");
    });

    input.addEventListener("blur", function () {
      this.parentElement.classList.remove("focused");
    });
  });

  // Enhanced form validation feedback
  const emailField = form.querySelector('input[name="email"]');
  if (emailField) {
    emailField.addEventListener("blur", function () {
      const email = this.value.trim();
      if (email && !isValidEmail(email)) {
        this.style.borderColor = "#e74c3c";
        this.style.boxShadow = "0 0 0 3px rgba(231, 76, 60, 0.1)";
      } else {
        this.style.borderColor = "";
        this.style.boxShadow = "";
      }
    });
  }

  // Email validation helper function
  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }
});
