document.addEventListener("DOMContentLoaded", function () {
  const filterForm = document.querySelector("form");
  const filterControls = document.querySelector(".filter-controls");
  if (!filterForm || !filterControls) return;

  // Listen for changes on any dropdown in the filter-controls
  filterControls.querySelectorAll("select").forEach(function (select) {
    select.addEventListener("change", function () {
      // Submit the form via AJAX
      const formData = new FormData(filterForm);
      const params = new URLSearchParams(formData).toString();
      console.log("Dropdown changed, sending AJAX with params:", params);
      fetch(window.location.pathname + "?" + params, {
        headers: { "X-Requested-With": "XMLHttpRequest" },
      })
        .then((response) => {
          console.log("AJAX response status:", response.status);
          return response.text();
        })
        .then((html) => {
          console.log("AJAX response HTML:", html.substring(0, 200));
          // Parse the returned HTML and update only the dashboard content
          const parser = new DOMParser();
          const doc = parser.parseFromString(html, "text/html");
          // Replace metrics
          const newMetrics = doc.querySelector(".metrics-container");
          const oldMetrics = document.querySelector(".metrics-container");
          if (newMetrics && oldMetrics) {
            oldMetrics.innerHTML = newMetrics.innerHTML;
            console.log("Metrics updated");
          } else {
            console.warn(
              "Metrics container not found in AJAX response or page"
            );
          }
          // Replace charts row
          const newChartsRow = doc.querySelector("div.row.g-4");
          const oldChartsRow = document.querySelector("div.row.g-4");
          if (newChartsRow && oldChartsRow) {
            oldChartsRow.innerHTML = newChartsRow.innerHTML;
            console.log("Charts row updated");
            if (typeof renderEmploymentByChart === "function")
              renderEmploymentByChart();
            if (typeof renderAlumniHeatmap === "function")
              renderAlumniHeatmap();
          } else {
            console.warn("Charts row not found in AJAX response or page");
          }
        })
        .catch((err) => {
          console.error("AJAX error:", err);
        });
    });
  });
});

function renderEmploymentByChart() {
  var dataScript = document.getElementById("employmentByChartData");
  var canvas = document.getElementById("employmentByChart");
  if (!dataScript || !canvas) return;
  if (window.employmentByChartInstance) {
    window.employmentByChartInstance.destroy();
    window.employmentByChartInstance = null;
  }
  var data = JSON.parse(dataScript.textContent);
  var chartType = data.chartType || "bar";
  var chartOptions = {
    responsive: true,
    plugins: {
      legend: { display: chartType === "pie" },
      title: { display: false },
    },
    scales:
      chartType === "bar" || chartType === "line"
        ? {
            x: {
              grid: { display: false },
              ticks: { color: "#333", font: { weight: "bold" } },
            },
            y: {
              beginAtZero: true,
              grid: { color: "#eee" },
              ticks: { color: "#333" },
            },
          }
        : {},
  };
  var chartData = {
    labels: data.labels,
    datasets: [
      {
        label: "Alumni Count",
        data: data.counts,
        backgroundColor: (function(){
            // Special mapping for Gender pie: Male = blue, Female = orange
            if (chartType === 'pie' && Array.isArray(data.labels)) {
              const lc = data.labels.map(l => String(l || '').toLowerCase().trim());
              const hasGender = lc.some(v => v === 'male' || v === 'female');
              if (hasGender) {
                const map = { male: 'rgba(31,119,180,0.85)', female: 'rgba(255,127,14,0.85)' };
                return lc.map(lbl => map[lbl] || 'rgba(124,124,124,0.85)');
              }
            }
            // For bar/pie charts, use per-label colors; for line charts, use a single color
            if (typeof getDistinctPalette === 'function' && typeof applyAlpha === 'function') {
              if (chartType === 'bar' || chartType === 'pie') {
                return applyAlpha(getDistinctPalette(data.labels.length), 0.85);
              }
              // line or others: use single color
              return applyAlpha([getDistinctPalette(1)[0]], 0.7);
            }
            // basic fallback: for bar/pie return array, for line return single rgba
            if (chartType === 'bar' || chartType === 'pie') return ["#1f77b4","#ff7f0e","#2ca02c","#d62728","#9467bd","#8c564b","#e377c2","#7f7f7f"].slice(0, data.labels.length);
            return "rgba(31,119,180,0.7)";
        })(),
        borderColor: chartType === "pie" ? "#fff" : (function(){
          if (chartType === 'pie') return '#fff';
          if (typeof getDistinctPalette === 'function') return getDistinctPalette(1)[0];
          return 'rgba(33, 150, 83, 1)';
        })(),
        borderWidth: 1,
        borderRadius: chartType === "bar" ? 8 : 0,
        maxBarThickness: chartType === "bar" ? 40 : undefined,
        fill: chartType === "line",
        tension: chartType === "line" ? 0.3 : undefined,
        pointBackgroundColor: (function(){
          if (chartType === 'line') {
            if (typeof getDistinctPalette === 'function') return getDistinctPalette(1)[0];
            return '#1f77b4';
          }
          return undefined;
        })(),
      },
    ],
  };
  window.employmentByChartInstance = new Chart(canvas.getContext("2d"), {
    type: chartType,
    data: chartData,
    options: chartOptions,
  });
}

function renderLocationHeatmapChart() {
  var dataScript = document.getElementById("locationHeatmapChartData");
  var canvas = document.getElementById("locationHeatmapChart");
  if (!dataScript || !canvas) return;
  var data = JSON.parse(dataScript.textContent);
  if (window.locationHeatmapChartInstance) {
    window.locationHeatmapChartInstance.destroy();
  }
  // Generate a color gradient for the bars
  var baseColor = [33, 150, 243]; // blue
  var max = Math.max.apply(null, data.counts);
  var min = Math.min.apply(null, data.counts);
  var colors = data.counts.map(function (count) {
    var intensity = max === min ? 1 : (count - min) / (max - min);
    // interpolate between baseColor and a lighter color
    var r = Math.round(baseColor[0] + (255 - baseColor[0]) * (1 - intensity));
    var g = Math.round(baseColor[1] + (255 - baseColor[1]) * (1 - intensity));
    var b = Math.round(baseColor[2] + (255 - baseColor[2]) * (1 - intensity));
    return "rgba(" + r + "," + g + "," + b + ",0.8)";
  });
  window.locationHeatmapChartInstance = new Chart(canvas.getContext("2d"), {
    type: "bar",
    data: {
      labels: data.labels,
      datasets: [
        {
          label: "Alumni Count",
          data: data.counts,
          backgroundColor: colors,
          borderColor: colors,
          borderWidth: 1,
          borderRadius: 8,
          maxBarThickness: 40,
        },
      ],
    },
    options: {
      indexAxis: "y",
      responsive: true,
      plugins: {
        legend: { display: false },
        title: { display: false },
      },
      scales: {
        x: {
          beginAtZero: true,
          grid: { color: "#eee" },
          ticks: { color: "#333" },
        },
        y: {
          grid: { display: false },
          ticks: { color: "#333", font: { weight: "bold" } },
        },
      },
    },
  });
}

// Call on page load
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", function () {
    renderEmploymentByChart();
    renderLocationHeatmapChart();
  });
} else {
  renderEmploymentByChart();
  renderLocationHeatmapChart();
}

// Initialize SortableJS for chart cards
if (typeof Sortable !== "undefined") {
  new Sortable(document.getElementById("sortable-charts"), {
    animation: 200,
    handle: ".card-header",
    draggable: ".sortable-chart-card",
    ghostClass: "sortable-ghost",
  });
} else if (window.Sortable) {
  new window.Sortable(document.getElementById("sortable-charts"), {
    animation: 200,
    handle: ".card-header",
    draggable: ".sortable-chart-card",
    ghostClass: "sortable-ghost",
  });
} else {
  console.warn("SortableJS is not loaded. Chart cards will not be draggable.");
}
