// academic-dropdowns.js
// Client-side helpers for academic dropdowns (cascading filters).

document.addEventListener("DOMContentLoaded", () => {
  const schoolSelect = document.getElementById("school_university");
  const campusSelect = document.getElementById("campus_branch");
  const collegeSelect = document.getElementById("college_department");
  const programSelect = document.getElementById("program");
  const majorSelect = document.getElementById("major_specialization");

  if (!schoolSelect || !campusSelect || !collegeSelect || !programSelect) return;

  // Cache original department options (excluding placeholder)
  const originalDeptOptions = Array.from(collegeSelect.options).filter(
    (opt) => !opt.disabled && opt.value
  );

  // Cache original campus and program options (excluding placeholders)
  const originalCampusOptions = Array.from(campusSelect.options).filter(
    (opt) => !opt.disabled && opt.value
  );

  const originalProgramOptions = Array.from(programSelect.options).filter(
    (opt) => !opt.disabled && opt.value
  );

  const originalMajorOptions =
    majorSelect && majorSelect.tagName === "SELECT"
      ? Array.from(majorSelect.options).filter((opt) => !opt.disabled && opt.value)
      : [];

  function getSelectedUniversityId() {
    const opt = schoolSelect.options[schoolSelect.selectedIndex];
    return opt ? opt.getAttribute("data-university-id") : null;
  }

  function getSelectedCampusIds() {
    const opt = campusSelect.options[campusSelect.selectedIndex];
    return {
      campusId: opt ? opt.getAttribute("data-campus-id") : null,
      universityId: opt ? opt.getAttribute("data-university-id") : null,
    };
  }

  function getSelectedDepartmentId() {
    const opt = collegeSelect.options[collegeSelect.selectedIndex];
    return opt ? opt.getAttribute("data-department-id") : null;
  }

  function getSelectedProgramId() {
    const opt = programSelect.options[programSelect.selectedIndex];
    return opt ? opt.getAttribute("data-program-id") : null;
  }

  function resetSelectKeepingPlaceholder(select) {
    const placeholder = select.querySelector("option[disabled]");
    select.innerHTML = "";
    if (placeholder) {
      select.appendChild(placeholder.cloneNode(true));
    }
  }

  function filterCampuses() {
    const selectedUniId = getSelectedUniversityId();

    resetSelectKeepingPlaceholder(campusSelect);
    resetSelectKeepingPlaceholder(collegeSelect);
    resetSelectKeepingPlaceholder(programSelect);
    if (majorSelect && majorSelect.tagName === "SELECT") {
      resetSelectKeepingPlaceholder(majorSelect);
    }

    if (!selectedUniId) {
      return;
    }

    originalCampusOptions.forEach((opt) => {
      const campusUniId = opt.getAttribute("data-university-id");
      if (campusUniId === selectedUniId) {
        campusSelect.appendChild(opt.cloneNode(true));
      }
    });
  }

  function filterDepartments() {
    const selectedUniId = getSelectedUniversityId();
    const { campusId } = getSelectedCampusIds();

    // Require both School and Campus to be chosen before listing departments
    resetSelectKeepingPlaceholder(collegeSelect);
    resetSelectKeepingPlaceholder(programSelect);
    resetSelectKeepingPlaceholder(majorSelect);

    if (!selectedUniId || !campusId) {
      // No valid selection yet; keep only the placeholder
      return;
    }

    originalDeptOptions.forEach((opt) => {
      const deptUniId = opt.getAttribute("data-university-id");
      const deptCampusId = opt.getAttribute("data-campus-id");

      // Only include departments that exactly match both university and campus
      if (deptUniId === selectedUniId && deptCampusId === campusId) {
        collegeSelect.appendChild(opt.cloneNode(true));
      }
    });
  }

  function filterPrograms() {
    const selectedUniId = getSelectedUniversityId();
    const { campusId } = getSelectedCampusIds();
    const departmentId = getSelectedDepartmentId();

    resetSelectKeepingPlaceholder(programSelect);
    resetSelectKeepingPlaceholder(majorSelect);

    if (!selectedUniId || !campusId || !departmentId) {
      return;
    }

    originalProgramOptions.forEach((opt) => {
      const progUniId = opt.getAttribute("data-university-id");
      const progCampusId = opt.getAttribute("data-campus-id");
      const progDeptId = opt.getAttribute("data-department-id");

      if (
        progUniId === selectedUniId &&
        progCampusId === campusId &&
        progDeptId === departmentId
      ) {
        programSelect.appendChild(opt.cloneNode(true));
      }
    });
  }

  function filterSpecializations() {
    const selectedUniId = getSelectedUniversityId();
    const { campusId } = getSelectedCampusIds();
    const departmentId = getSelectedDepartmentId();
    const programId = getSelectedProgramId();

    resetSelectKeepingPlaceholder(majorSelect);

    if (!selectedUniId || !campusId || !departmentId || !programId) {
      return;
    }

    originalMajorOptions.forEach((opt) => {
      const specUniId = opt.getAttribute("data-university-id");
      const specCampusId = opt.getAttribute("data-campus-id");
      const specDeptId = opt.getAttribute("data-department-id");
      const specProgId = opt.getAttribute("data-program-id");

      if (
        specUniId === selectedUniId &&
        specCampusId === campusId &&
        specDeptId === departmentId &&
        specProgId === programId
      ) {
        majorSelect.appendChild(opt.cloneNode(true));
      }
    });
  }

  schoolSelect.addEventListener("change", () => {
    filterCampuses();
    filterDepartments();
    filterPrograms();
    filterSpecializations();
  });

  campusSelect.addEventListener("change", () => {
    filterDepartments();
    filterPrograms();
    filterSpecializations();
  });

  collegeSelect.addEventListener("change", () => {
    filterPrograms();
    filterSpecializations();
  });

  if (majorSelect && majorSelect.tagName === "SELECT") {
    programSelect.addEventListener("change", filterSpecializations);
  }

  // Initial filter on load (in case values are preselected)
  filterCampuses();
  filterDepartments();
  filterPrograms();
  if (majorSelect && majorSelect.tagName === "SELECT") {
    filterSpecializations();
  }
});
