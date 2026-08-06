// --- Address Dropdowns Section ---
const provinceSelect = document.getElementById("province");
const citySelect = document.getElementById("city");
const barangaySelect = document.getElementById("barangay");

const companyProvinceSelect = document.getElementById("company_province");
const companyCitySelect = document.getElementById("company_city");
const companyBarangaySelect = document.getElementById("company_barangay");

// Function to populate a select dropdown with data
function populateDropdown(
  selectElement,
  data,
  valueKey = "code",
  textKey = "name"
) {
  if (!selectElement) return; // Skip if element doesn't exist
  const sorted = [...data].sort((a, b) => {
    const an = (a[textKey] || a.name || a.city_name || "").toString().toLocaleLowerCase();
    const bn = (b[textKey] || b.name || b.city_name || "").toString().toLocaleLowerCase();
    return an.localeCompare(bn);
  });

  sorted.forEach((item) => {
    const option = document.createElement("option");
    option.value = item[valueKey];
    option.textContent = item[textKey];
    selectElement.appendChild(option);
  });
}

// Function to fetch data from an API endpoint and handle errors
async function fetchData(url) {
  try {
    const response = await fetch(url);
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    return await response.json();
  } catch (error) {
    console.error("Error fetching data:", error);
    return null; // Or handle the error as needed
  }
}

// Fetch Provinces (for both home and company addresses)
async function loadProvinces() {
  const provinces = await fetchData("https://psgc.gitlab.io/api/provinces/");
  if (provinces) {
    populateDropdown(provinceSelect, provinces);
    populateDropdown(companyProvinceSelect, provinces);
  }
}

// Fetch Cities/Municipalities based on the selected Province (Home Address)
if (provinceSelect) provinceSelect.addEventListener("change", async function () {
  citySelect.innerHTML =
    '<option value="" disabled selected>Select City/Municipality</option>';
  barangaySelect.innerHTML =
    '<option value="" disabled selected>Select Barangay</option>';
  const selectedProvinceCode = this.value;
  if (selectedProvinceCode) {
    const cities = await fetchData(
      `https://psgc.gitlab.io/api/provinces/${selectedProvinceCode}/cities-municipalities/`
    );
    if (cities) {
      populateDropdown(citySelect, cities);
    }
  }
});

// Fetch Barangays based on the selected City/Municipality (Home Address)
if (citySelect) citySelect.addEventListener("change", async function () {
  barangaySelect.innerHTML =
    '<option value="" disabled selected>Select Barangay</option>';
  const selectedCityCode = this.value;
  if (selectedCityCode) {
    const barangays = await fetchData(
      `https://psgc.gitlab.io/api/cities-municipalities/${selectedCityCode}/barangays/`
    );
    if (barangays) {
      populateDropdown(barangaySelect, barangays);
    }
  }
});

// Fetch Cities/Municipalities based on the selected Province (Company Address)
if (companyProvinceSelect) companyProvinceSelect.addEventListener("change", async function () {
  companyCitySelect.innerHTML =
    '<option value="" disabled selected>Select City/Municipality</option>';
  companyBarangaySelect.innerHTML =
    '<option value="" disabled selected>Select Barangay</option>';
  const selectedProvinceCode = this.value;
  if (selectedProvinceCode) {
    const cities = await fetchData(
      `https://psgc.gitlab.io/api/provinces/${selectedProvinceCode}/cities-municipalities/`
    );
    if (cities) {
      populateDropdown(companyCitySelect, cities);
    }
  }
});

// Fetch Barangays based on the selected City/Municipality (Company Address)
if (companyCitySelect) companyCitySelect.addEventListener("change", async function () {
  companyBarangaySelect.innerHTML =
    '<option value="" disabled selected>Select Barangay</option>';
  const selectedCityCode = this.value;
  if (selectedCityCode) {
    const barangays = await fetchData(
      `https://psgc.gitlab.io/api/cities-municipalities/${selectedCityCode}/barangays/`
    );
    if (barangays) {
      populateDropdown(companyBarangaySelect, barangays);
    }
  }
});

// Initialize by loading the provinces and set selected values after load
async function initAddresses() {
  await loadProvinces();
  if (!provinceSelect && !companyProvinceSelect) return;
  // Home address selections
  if (provinceSelect) {
  const selProvince = provinceSelect.getAttribute("data-selected");
  if (selProvince) {
    provinceSelect.value = selProvince;
    const cities = await fetchData(
      `https://psgc.gitlab.io/api/provinces/${selProvince}/cities-municipalities/`
    );
    if (cities) {
      populateDropdown(citySelect, cities);
      const selCity = citySelect.getAttribute("data-selected");
      if (selCity) {
        citySelect.value = selCity;
        const barangays = await fetchData(
          `https://psgc.gitlab.io/api/cities-municipalities/${selCity}/barangays/`
        );
        if (barangays) {
          populateDropdown(barangaySelect, barangays);
          const selBarangay = barangaySelect.getAttribute("data-selected");
          if (selBarangay) barangaySelect.value = selBarangay;
        }
      }
    }
  }
  }
  // Company address selections
  if (companyProvinceSelect) {
  const selCompProvince = companyProvinceSelect.getAttribute("data-selected");
  if (selCompProvince) {
    companyProvinceSelect.value = selCompProvince;
    const cities = await fetchData(
      `https://psgc.gitlab.io/api/provinces/${selCompProvince}/cities-municipalities/`
    );
    if (cities) {
      populateDropdown(companyCitySelect, cities);
      const selCompCity = companyCitySelect.getAttribute("data-selected");
      if (selCompCity) {
        companyCitySelect.value = selCompCity;
        const barangays = await fetchData(
          `https://psgc.gitlab.io/api/cities-municipalities/${selCompCity}/barangays/`
        );
        if (barangays) {
          populateDropdown(companyBarangaySelect, barangays);
          const selCompBarangay =
            companyBarangaySelect.getAttribute("data-selected");
          if (selCompBarangay) companyBarangaySelect.value = selCompBarangay;
        }
      }
    }
  }
  }
}

document.addEventListener("DOMContentLoaded", function () {
  initAddresses();
});
