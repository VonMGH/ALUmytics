// address-dropdown.js

const LOCAL_API = "./api";

function normalizeCountryName(name) {
  return (name || '').toString().trim().toLowerCase();
}

function isPhilippinesCountry(value, code) {
  const normalized = normalizeCountryName(value);
  const countryCode = (code || '').toString().trim().toUpperCase();
  return normalized === 'philippines' || countryCode === 'PH';
}

function selectCountryOption(countrySelect, savedValue) {
  if (!countrySelect || !savedValue) return false;

  countrySelect.value = savedValue;
  if (countrySelect.value === savedValue) return true;

  const normalized = normalizeCountryName(savedValue);
  const upperSaved = savedValue.toString().trim().toUpperCase();
  const match = Array.from(countrySelect.options).find((option) => {
    if (normalizeCountryName(option.value) === normalized) return true;
    const code = (option.dataset.code || '').toUpperCase();
    return code === upperSaved || code === normalized.toUpperCase();
  });

  if (match) {
    countrySelect.value = match.value;
    return true;
  }

  return false;
}

function getSavedAddressValue(selectEl) {
  if (!selectEl) return '';
  return selectEl.getAttribute('data-selected-name') || selectEl.getAttribute('data-selected') || '';
}

function syncTextInputToSelect(textInput, selectEl) {
  if (!textInput || !selectEl) return;
  const value = textInput.value || 'N/A';
  const option = document.createElement('option');
  option.value = value;
  option.text = value;
  selectEl.innerHTML = '';
  selectEl.appendChild(option);
  selectEl.value = value;
}

function convertToTextInput(selectElement, placeholder, note = '') {
  if (!selectElement) return;
  
  const container = selectElement.parentNode;
  
  // Create and setup text input
  const textInput = document.createElement('input');
  textInput.type = 'text';
  textInput.className = 'form-control';
  textInput.placeholder = placeholder;
  textInput.name = selectElement.name;
  textInput.id = selectElement.id + '_text';
  
  // Create note if provided
  if (note) {
    const noteDiv = document.createElement('small');
    noteDiv.className = 'text-muted';
    noteDiv.style.fontSize = '0.8em';
    noteDiv.style.display = 'block';
    noteDiv.innerHTML = note;
    container.appendChild(noteDiv);
  }
  
  // Hide original select, remove required attribute, and add text input
  selectElement.style.display = 'none';
  selectElement.removeAttribute('required');
  container.insertBefore(textInput, selectElement);
  
  // Update hidden select when input changes
  textInput.addEventListener('input', function() {
    const option = selectElement.querySelector('option:not([disabled])') || 
                  document.createElement('option');
    option.value = this.value || 'N/A';
    option.text = this.value || 'N/A';
    selectElement.innerHTML = '';
    selectElement.appendChild(option);
    selectElement.value = this.value || 'N/A';
  });
  
  return textInput;
}

function initAddressDropdowns() {
  // Get form elements
  const provinceSelect = document.getElementById("province");
  const citySelect = document.getElementById("city");
  const barangaySelect = document.getElementById("barangay");
  const companyCountrySelect = document.getElementById("company_country");
  const companyProvinceSelect = document.getElementById("company_province");
  const companyCitySelect = document.getElementById("company_city");
  const companyBarangaySelect = document.getElementById("company_barangay");
  const personalCountrySelect = document.getElementById("country");

  // Get preselected values
  const selectedCompanyCountry = companyCountrySelect?.getAttribute("data-selected");
  const selectedPersonalCountry = personalCountrySelect?.getAttribute("data-selected");

  // Convert all address fields to text inputs with notes
  // We'll toggle between select-mode (PSGC) and free-text mode depending on country selection.
  // Keep track of created text inputs so we can restore selects later.
  const convertedMap = new Map(); // selectElement -> { inputEl, noteEl }

  function makeTextMode(selectEl, placeholder) {
    if (!selectEl) return;
    const savedValue = getSavedAddressValue(selectEl);
    // If already converted, update value if still empty
    const existing = document.getElementById(selectEl.id + '_text');
    if (existing) {
      if (savedValue && !existing.value) {
        existing.value = savedValue;
        syncTextInputToSelect(existing, selectEl);
      }
      return convertedMap.set(selectEl, { inputEl: existing });
    }
    // Convert
    const input = convertToTextInput(selectEl, placeholder);
    if (input && savedValue) {
      input.value = savedValue;
      syncTextInputToSelect(input, selectEl);
    }
    // Try to find the small note we appended (it was added as last child of container)
    let noteEl = null;
    if (input) {
      const container = selectEl.parentNode;
      const smalls = container.querySelectorAll('small.text-muted');
      noteEl = smalls[smalls.length - 1] || null;
    }
    convertedMap.set(selectEl, { inputEl: input, noteEl: noteEl });
  }

  function restoreSelectMode(selectEl) {
    if (!selectEl) return;
    const entry = convertedMap.get(selectEl);
    if (entry && entry.inputEl) {
      // Remove text input and note if present
      try {
        entry.inputEl.remove();
      } catch (e) {}
      if (entry.noteEl) {
        try { entry.noteEl.remove(); } catch (e) {}
      }
      convertedMap.delete(selectEl);
    }
    // Show the original select
    selectEl.style.display = '';
  }

  // Helper: small fetch wrapper
  async function fetchData(url) {
    try {
      const res = await fetch(url);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);
      return await res.json();
    } catch (err) {
      console.error('fetchData error', err, url);
      return null;
    }
  }

  // populate select with PSGC-style items (code,name)
  function populateSelect(selectEl, items, codeKey = 'code', nameKey = 'name') {
    if (!selectEl || !items) return;
    selectEl.innerHTML = `<option value="" disabled selected>Select ${selectEl.getAttribute('aria-label') || 'Option'}</option>`;
    const sorted = [...items].sort((a, b) => {
      const an = (a[nameKey] || a.name || a.city_name || '').toString().toLocaleLowerCase();
      const bn = (b[nameKey] || b.name || b.city_name || '').toString().toLocaleLowerCase();
      return an.localeCompare(bn);
    });
    sorted.forEach(it => {
      const opt = document.createElement('option');
      opt.value = it[codeKey] || it.code || it.psgc_code || it.name;
      opt.text = it[nameKey] || it.name || it.city_name;
      selectEl.appendChild(opt);
    });
  }

  // Try to set selected option from either code or name attributes
  function applySelectedFromAttributes(selectEl) {
    if (!selectEl) return;
    const selCode = selectEl.getAttribute('data-selected');
    const selName = selectEl.getAttribute('data-selected-name');
    if (selCode) {
      selectEl.value = selCode;
      if (selectEl.value === selCode) return; // success
    }
    if (selName) {
      const optByText = Array.from(selectEl.options).find(o => (o.text || '').toLowerCase() === selName.toLowerCase());
      if (optByText) {
        selectEl.value = optByText.value;
      }
    }
  }

  // Load PSGC provinces and attach cascading behavior for company selects
  async function enablePSGCForCompany() {
    if (!companyProvinceSelect || !companyCitySelect || !companyBarangaySelect) return;
    // restore selects if they were converted
    restoreSelectMode(companyProvinceSelect);
    restoreSelectMode(companyCitySelect);
    restoreSelectMode(companyBarangaySelect);

    const provinces = await fetchData('https://psgc.gitlab.io/api/provinces/');
    if (provinces) {
      populateSelect(companyProvinceSelect, provinces);
      applySelectedFromAttributes(companyProvinceSelect);
    }

    // set selected if present
    const selCompProvince = companyProvinceSelect.getAttribute('data-selected') || companyProvinceSelect.getAttribute('data-selected-name');
    if (selCompProvince) {
      // Ensure province is selected (code or name)
      applySelectedFromAttributes(companyProvinceSelect);
      const selectedProvCode = companyProvinceSelect.value || '';
      const cities = selectedProvCode ? await fetchData(`https://psgc.gitlab.io/api/provinces/${selectedProvCode}/cities-municipalities/`) : null;
      if (cities) populateSelect(companyCitySelect, cities);
      applySelectedFromAttributes(companyCitySelect);
      const selCompCityCode = companyCitySelect.value || '';
      if (selCompCityCode) {
        const barangays = await fetchData(`https://psgc.gitlab.io/api/cities-municipalities/${selCompCityCode}/barangays/`);
        if (barangays) populateSelect(companyBarangaySelect, barangays);
        applySelectedFromAttributes(companyBarangaySelect);
      }
    }

    // Attach cascading listeners (guard to avoid duplicate listeners)
    if (!companyProvinceSelect._psgcBound) {
      companyProvinceSelect.addEventListener('change', async function () {
        companyCitySelect.innerHTML = '<option value="" disabled selected>Select City/Municipality</option>';
        companyBarangaySelect.innerHTML = '<option value="" disabled selected>Select Barangay</option>';
        const selectedProvinceCode = this.value;
        if (selectedProvinceCode) {
          const cities = await fetchData(`https://psgc.gitlab.io/api/provinces/${selectedProvinceCode}/cities-municipalities/`);
          if (cities) {
            populateSelect(companyCitySelect, cities);
            applySelectedFromAttributes(companyCitySelect);
          }
        }
      });
      companyProvinceSelect._psgcBound = true;
    }

    if (!companyCitySelect._psgcBound) {
      companyCitySelect.addEventListener('change', async function () {
        companyBarangaySelect.innerHTML = '<option value="" disabled selected>Select Barangay</option>';
        const selectedCityCode = this.value;
        if (selectedCityCode) {
          const barangays = await fetchData(`https://psgc.gitlab.io/api/cities-municipalities/${selectedCityCode}/barangays/`);
          if (barangays) {
            populateSelect(companyBarangaySelect, barangays);
            applySelectedFromAttributes(companyBarangaySelect);
          }
        }
      });
      companyCitySelect._psgcBound = true;
    }
  }

  // Load PSGC provinces and attach cascading behavior for personal address selects
  async function enablePSGCForPersonal() {
    if (!provinceSelect || !citySelect || !barangaySelect) return;
    // restore selects if they were converted
    restoreSelectMode(provinceSelect);
    restoreSelectMode(citySelect);
    restoreSelectMode(barangaySelect);

    const provinces = await fetchData('https://psgc.gitlab.io/api/provinces/');
    if (provinces) {
      populateSelect(provinceSelect, provinces);
      applySelectedFromAttributes(provinceSelect);
    }

    // set selected if present
    const selProvince = provinceSelect.getAttribute('data-selected') || provinceSelect.getAttribute('data-selected-name');
    if (selProvince) {
      applySelectedFromAttributes(provinceSelect);
      const selectedProvCode = provinceSelect.value || '';
      const cities = selectedProvCode ? await fetchData(`https://psgc.gitlab.io/api/provinces/${selectedProvCode}/cities-municipalities/`) : null;
      if (cities) populateSelect(citySelect, cities);
      applySelectedFromAttributes(citySelect);
      const selCityCode = citySelect.value || '';
      if (selCityCode) {
        const barangays = await fetchData(`https://psgc.gitlab.io/api/cities-municipalities/${selCityCode}/barangays/`);
        if (barangays) populateSelect(barangaySelect, barangays);
        applySelectedFromAttributes(barangaySelect);
      }
    }

    if (!provinceSelect._psgcBound) {
      provinceSelect.addEventListener('change', async function () {
        citySelect.innerHTML = '<option value="" disabled selected>Select City/Municipality</option>';
        barangaySelect.innerHTML = '<option value="" disabled selected>Select Barangay</option>';
        const selectedProvinceCode = this.value;
        if (selectedProvinceCode) {
          const cities = await fetchData(`https://psgc.gitlab.io/api/provinces/${selectedProvinceCode}/cities-municipalities/`);
          if (cities) {
            populateSelect(citySelect, cities);
            applySelectedFromAttributes(citySelect);
          }
        }
      });
      provinceSelect._psgcBound = true;
    }

    if (!citySelect._psgcBound) {
      citySelect.addEventListener('change', async function () {
        barangaySelect.innerHTML = '<option value="" disabled selected>Select Barangay</option>';
        const selectedCityCode = this.value;
        if (selectedCityCode) {
          const barangays = await fetchData(`https://psgc.gitlab.io/api/cities-municipalities/${selectedCityCode}/barangays/`);
          if (barangays) {
            populateSelect(barangaySelect, barangays);
            applySelectedFromAttributes(barangaySelect);
          }
        }
      });
      citySelect._psgcBound = true;
    }
  }

  // Put fields into text-mode (free-form) for company address
  function enableTextModeForCompany() {
    if (companyProvinceSelect) makeTextMode(companyProvinceSelect, 'Enter Province/State');
    if (companyCitySelect) makeTextMode(companyCitySelect, 'Enter City/Municipality');
    if (companyBarangaySelect) makeTextMode(companyBarangaySelect, 'Enter District/Barangay');
  }

  // Put fields into text-mode (free-form) for personal address
  function enableTextModeForPersonal() {
    if (provinceSelect) makeTextMode(provinceSelect, 'Enter Province/State');
    if (citySelect) makeTextMode(citySelect, 'Enter City/Municipality');
    if (barangaySelect) makeTextMode(barangaySelect, 'Enter District/Barangay');
  }

  // Handle country selection and populate country list
  if (companyCountrySelect) {
    fetch(`${LOCAL_API}/location.php?action=countries`)
      .then(res => res.json())
      .then(async data => {
        if (data.success && data.data) {
          companyCountrySelect.innerHTML = '<option value="" disabled selected>Select Country</option>';
          data.data.forEach(country => {
            const option = document.createElement('option');
            option.value = country.name;
            option.text = country.name;
            option.dataset.code = country.code;
            companyCountrySelect.appendChild(option);
          });

          if (selectedCompanyCountry) {
            selectCountryOption(companyCountrySelect, selectedCompanyCountry);
          }

          // initial mode based on current country
          const current = companyCountrySelect.value || companyCountrySelect.getAttribute('data-selected');
          const selectedOption = companyCountrySelect.options[companyCountrySelect.selectedIndex];
          const code = selectedOption?.dataset?.code || '';
          if (isPhilippinesCountry(current, code)) {
            await enablePSGCForCompany();
          } else {
            enableTextModeForCompany();
          }

          // watch for changes
          companyCountrySelect.addEventListener('change', async function () {
            const val = this.value;
            const opt = this.options[this.selectedIndex];
            const countryCode = opt?.dataset?.code || '';
            if (isPhilippinesCountry(val, countryCode)) {
              await enablePSGCForCompany();
            } else {
              enableTextModeForCompany();
            }
          });
        }
      })
      .catch(err => {
        console.error('Error loading countries:', err);
        // fallback: make company address free-text
        enableTextModeForCompany();
      });
  }

  // Handle personal country selection and populate personal country list
  if (personalCountrySelect) {
    fetch(`${LOCAL_API}/location.php?action=countries`)
      .then(res => res.json())
      .then(async data => {
        if (data.success && data.data) {
          personalCountrySelect.innerHTML = '<option value="" disabled selected>Select Country</option>';
          data.data.forEach(country => {
            const option = document.createElement('option');
            option.value = country.name;
            option.text = country.name;
            option.dataset.code = country.code;
            personalCountrySelect.appendChild(option);
          });

          if (selectedPersonalCountry) {
            selectCountryOption(personalCountrySelect, selectedPersonalCountry);
          }

          // initial mode based on current country
          const current = personalCountrySelect.value || personalCountrySelect.getAttribute('data-selected');
          const selectedOption = personalCountrySelect.options[personalCountrySelect.selectedIndex];
          const code = selectedOption?.dataset?.code || '';
          if (isPhilippinesCountry(current, code)) {
            await enablePSGCForPersonal();
          } else {
            enableTextModeForPersonal();
          }

          // watch for changes
          personalCountrySelect.addEventListener('change', async function () {
            const val = this.value;
            const opt = this.options[this.selectedIndex];
            const countryCode = opt?.dataset?.code || '';
            if (isPhilippinesCountry(val, countryCode)) {
              await enablePSGCForPersonal();
            } else {
              enableTextModeForPersonal();
            }
          });
        }
      })
      .catch(err => {
        console.error('Error loading countries for personal address:', err);
        // fallback: make personal address free-text
        enableTextModeForPersonal();
      });
  }
}

// Initialize when document is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initAddressDropdowns);
} else {
  initAddressDropdowns();
}