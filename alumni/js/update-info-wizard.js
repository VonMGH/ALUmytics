document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("updateWizardForm");
  const steps = Array.from(document.querySelectorAll(".wizard-step"));
  const nextBtn = document.getElementById("nextBtn");
  const backBtn = document.getElementById("backBtn");
  const submitBtn = document.getElementById("submitBtn");
  const progressBar = document.getElementById("wizardProgressBar");
  let currentStep = 0;

  function showStep(idx) {
    steps.forEach((step, i) => {
      step.classList.toggle("active", i === idx);
    });
    backBtn.style.display = idx === 0 ? "none" : "inline-block";
    nextBtn.style.display = idx === steps.length - 1 ? "none" : "inline-block";
    submitBtn.style.display =
      idx === steps.length - 1 ? "inline-block" : "none";
    // Progress bar
    progressBar.style.width = ((idx + 1) / steps.length) * 100 + "%";
    // On review step, show summary (last step)
    if (idx === steps.length - 1) {
      populateReview();
    }
  }

  function validateStep(idx) {
    const step = steps[idx];
    const requiredFields = Array.from(step.querySelectorAll("[required]"));
    let valid = true;
    let firstInvalid = null;
    requiredFields.forEach((field) => {
      if (!field.value || (field.tagName === "SELECT" && !field.value)) {
        valid = false;
        if (!firstInvalid) firstInvalid = field;
      }
    });
    const errorBox = step.querySelector(".form-error-box");
    if (!valid) {
      errorBox.innerHTML = "Please fill in all required fields.";
      errorBox.style.display = "block";
      if (firstInvalid) firstInvalid.focus();
    } else {
      errorBox.innerHTML = "";
      errorBox.style.display = "none";
    }
    return valid;
  }

  function populateReview() {
    // This function is no longer used; review step mirrors inputs directly.
  }

  nextBtn.addEventListener("click", function () {
    if (validateStep(currentStep)) {
      currentStep++;
      showStep(currentStep);
    }
  });

  backBtn.addEventListener("click", function () {
    currentStep--;
    showStep(currentStep);
  });

  // Prevent form submit on Enter except on last step
  form.addEventListener("keydown", function (e) {
    if (e.key === "Enter" && currentStep !== steps.length - 1) {
      e.preventDefault();
      nextBtn.click();
    }
  });

  // AJAX submit on final step
  form.addEventListener("submit", function (e) {
    if (currentStep !== steps.length - 1) {
      e.preventDefault();
      nextBtn.click();
      return;
    }
    e.preventDefault();
    const errorBox = steps[currentStep].querySelector(".form-error-box");
    errorBox.innerHTML = "";
    errorBox.style.display = "none";
    const formData = new FormData(form);
    formData.append("action", "save");
    fetch("update-info-wizard.php", {
      method: "POST",
      body: formData,
      headers: { "X-Requested-With": "XMLHttpRequest" },
    })
      .then((res) => res.json())
      .then((data) => {
        if (data.success) {
          window.location.href = data.redirect;
        } else {
          errorBox.innerHTML = data.error || "An error occurred.";
          errorBox.style.display = "block";
        }
      })
      .catch(() => {
        errorBox.innerHTML = "An unexpected error occurred.";
        errorBox.style.display = "block";
      });
  });

  // Initial state
  showStep(currentStep);

  // Populate industry select(s)
  const industries = [
    "Agriculture",
    "Banking and Finance",
    "Construction",
    "Education",
    "Energy and Utilities",
    "Entertainment",
    "Government",
    "Healthcare",
    "Hospitality",
    "Information Technology",
    "Manufacturing",
    "Marketing and Advertising",
    "Mining",
    "Non-Profit",
    "Pharmaceuticals",
    "Real Estate",
    "Retail",
    "Telecommunications",
    "Transportation and Logistics",
    "Other",
  ];
  function populateIndustrySelect(selectEl) {
    if (!selectEl) return;
    industries.forEach((industry) => {
      const opt = document.createElement("option");
      opt.value = industry;
      opt.textContent = industry;
      selectEl.appendChild(opt);
    });
  }
  // Main industry select in Employment step
  populateIndustrySelect(document.getElementById("industry"));
  // Step for certifications/awards removed
});
