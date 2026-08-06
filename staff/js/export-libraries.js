// js/export-libraries.js
// Enhanced reusable export component for PDF, Excel, and CSV
// Dependencies: jsPDF (https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js), SheetJS (https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js)

(function (global) {
  const ExportLibraries = {
    // Default configuration for PDF generation
    defaultPDFConfig: {
      orientation: "portrait", // "portrait" or "landscape"
      unit: "mm",
      format: "a4",
      margins: { top: 20, left: 20, right: 20, bottom: 20 },
      theme: {
        primaryColor: [33, 150, 83],
        textColor: [0, 0, 0],
        headerColor: [255, 255, 255],
        lightGray: [150, 150, 150],
      },
      fonts: {
        title: { size: 20, style: "bold" },
        sectionHeader: { size: 15, style: "bold" },
        normal: { size: 12, style: "normal" },
        footer: { size: 10, style: "normal" },
      },
      spacing: {
        afterTitle: 10,
        afterHeader: 8,
        betweenSections: 10,
        betweenRows: 7,
        lineHeight: 1.2,
        chartPadding: 2,
      },
      chart: {
        defaultWidth: 150,
        defaultHeight: 60,
        maxWidth: 170,
        quality: 1.0,
        positioning: "center", // "left", "center", "right"
      },
    },

    exportToPDF: async function (
      data,
      filename = "export.pdf",
      options = {},
      title = ""
    ) {
      if (!window.jspdf || !window.jspdf.jsPDF) {
        alert("jsPDF library not loaded.");
        return;
      }

      // Merge user configuration with defaults
      const config = this._mergeConfig(
        this.defaultPDFConfig,
        options.config || {}
      );

      const doc = new window.jspdf.jsPDF({
        orientation: config.orientation.charAt(0), // "p" or "l"
        unit: config.unit,
        format: config.format,
      });

      let y = config.margins.top;
      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      const contentWidth =
        pageWidth - config.margins.left - config.margins.right;

      // Render title
      if (title) {
        y = this._renderTitle(doc, title, y, config, pageWidth);
      }

      // Process data using the new section-based renderer
      y = await this._renderSections(
        doc,
        data,
        options,
        y,
        config,
        pageWidth,
        pageHeight,
        contentWidth
      );

      // Add footer
      this._addFooter(doc, config, pageHeight);

      doc.save(filename);
      // Return a resolved promise so callers can await this
      return Promise.resolve();
    },

    // Merge configuration objects recursively
    _mergeConfig: function (defaultConfig, userConfig) {
      const result = { ...defaultConfig };

      for (const key in userConfig) {
        if (userConfig.hasOwnProperty(key)) {
          if (
            typeof userConfig[key] === "object" &&
            userConfig[key] !== null &&
            !Array.isArray(userConfig[key])
          ) {
            result[key] = this._mergeConfig(
              defaultConfig[key] || {},
              userConfig[key]
            );
          } else {
            result[key] = userConfig[key];
          }
        }
      }

      return result;
    },

    // Render document title
    _renderTitle: function (doc, title, y, config, pageWidth) {
      doc.setFont("helvetica", config.fonts.title.style);
      doc.setFontSize(config.fonts.title.size);
      doc.setTextColor(...config.theme.primaryColor);
      doc.text(title, pageWidth / 2, y, { align: "center" });

      y += config.spacing.afterTitle;

      // Add decorative line
      doc.setDrawColor(...config.theme.primaryColor);
      doc.setLineWidth(1);
      doc.line(config.margins.left, y, pageWidth - config.margins.right, y);

      return y + config.spacing.afterHeader;
    },

    // Main section rendering logic
    _renderSections: async function (
      doc,
      data,
      options,
      y,
      config,
      pageWidth,
      pageHeight,
      contentWidth
    ) {
      for (let i = 0; i < data.length; i++) {
        const row = data[i];

        // Check if we need a new page
        if (y > pageHeight - config.margins.bottom - 30) {
          doc.addPage();
          y = config.margins.top;
        }

        y = await this._processRow(
          doc,
          row,
          options,
          y,
          config,
          pageWidth,
          pageHeight,
          contentWidth
        );
      }

      return y;
    },

    // Process individual row/section
    _processRow: async function (
      doc,
      row,
      options,
      y,
      config,
      pageWidth,
      pageHeight,
      contentWidth
    ) {
      // Reset text styling
      doc.setFont("helvetica", config.fonts.normal.style);
      doc.setFontSize(config.fonts.normal.size);
      doc.setTextColor(...config.theme.textColor);

      // Simple multi-column table mode (used for Alumni PDF list)
      if (options && options.simpleTable && Array.isArray(row) && row.length > 2) {
        // First multi-column row becomes the header
        if (!options._simpleTableInitialized) {
          options._simpleTableInitialized = true;
          return this._renderSimpleTableRow(doc, row, y, config, contentWidth, true);
        }
        // Subsequent rows are normal table rows
        return this._renderSimpleTableRow(doc, row, y, config, contentWidth, false);
      }

      // Section headers (general pattern matching)
      if (this._isSectionHeader(row)) {
        return this._renderSectionHeader(doc, row[0], y, config);
      }

      // Chart image markers
      else if (this._isChartMarker(row)) {
        return await this._renderChart(
          doc,
          row,
          options,
          y,
          config,
          contentWidth
        );
      }

      // Table headers
      else if (this._isTableHeader(row)) {
        return this._renderTableHeader(doc, row, y, config, contentWidth);
      }

      // Table rows
      else if (this._isTableRow(row)) {
        return this._renderTableRow(doc, row, y, config);
      }

      // Description rows (single-column paragraph, e.g. "Title — Description")
      else if (this._isDescriptionRow(row)) {
        return this._renderDescriptionRow(doc, row, y, config, contentWidth);
      }

      // Blank rows
      else if (this._isBlankRow(row)) {
        return y + config.spacing.betweenRows * 0.6;
      }

      // Default row handling
      else {
        return this._renderDefaultRow(doc, row, y, config);
      }
    },

    // Helper methods for row identification
    _isSectionHeader: function (row) {
      if (row.length !== 1) return false;
      const text = row[0].toUpperCase();
      const sectionKeywords = [
        "METRICS",
        "DISTRIBUTION",
        "BREAKDOWN",
        "ANALYSIS",
        "SUMMARY",
        "OVERVIEW",
        "STATISTICS",
        "DEMOGRAPHICS",
        "EMPLOYMENT",
        "GEOGRAPHY",
        "HEATMAP",
        "TABLE",
        // Allow description sections
        "DESCRIPTION",
        "DESCRIPTIONS",
      ];
      return sectionKeywords.some((keyword) => text.includes(keyword));
    },

    _isChartMarker: function (row) {
      return row.length === 2 && row[0] === "__CHART_IMAGE__";
    },

    _isTableHeader: function (row) {
      if (row.length !== 2) return false;
      const headerKeywords = [
        "Metric",
        "Industry",
        "Province",
        "Location",
        "Gender",
        "Age Group",
        "Program",
        "Category",
        "Type",
        "Status",
        "Department",
        "Year",
        "Count",
        "Value",
        "Percentage",
        // Allow Description table header
        "Title"
      ];
      return headerKeywords.includes(row[0]);
    },

    _isTableRow: function (row) {
      return row.length === 2 && row[0] && row[1] && !this._isTableHeader(row);
    },

    _isBlankRow: function (row) {
      return row.length === 1 && row[0] === "";
    },

    _isDescriptionRow: function(row) {
      // Single-column description text (no special prefix needed)
      // Heuristic: a single-cell row that is a sentence (longer than 10 chars) and
      // contains at least one lowercase letter (to avoid matching ALL-CAPS headers).
      if (!Array.isArray(row) || row.length !== 1) return false;
      if (typeof row[0] !== 'string') return false;
      const s = row[0].trim();
      if (s.length < 10) return false;
      // avoid matching section headers which are typically all uppercase or short
      if (s === s.toUpperCase()) return false;
      // must contain a space (multiple words) to be a descriptive sentence
      if (!s.includes(' ')) return false;
      return /[a-z]/.test(s);
    },

    // Rendering methods
    _renderSectionHeader: function (doc, text, y, config) {
      y += config.spacing.betweenSections;
      doc.setFont("helvetica", config.fonts.sectionHeader.style);
      doc.setFontSize(config.fonts.sectionHeader.size);
      doc.setTextColor(...config.theme.primaryColor);
      doc.text(text, config.margins.left, y);

      y += config.spacing.afterHeader;
      doc.setFont("helvetica", config.fonts.normal.style);
      doc.setFontSize(config.fonts.normal.size);
      doc.setTextColor(0, 0, 0); // Always use black text

      return y;
    },

    _renderDescription: function(doc, text, y, config, contentWidth) {
      // Normalize and trim text
      text = String(text || '').trim();

      if (!text) return y;

      // Do not override global font or color; use current document settings

      // Split text to fit width with proper wrapping
      const lines = doc.splitTextToSize(text, contentWidth - config.margins.left - config.margins.right - 10);

      // Render text left-aligned with bold green styling for descriptions
      // Save previous settings
      const prevFontSize = doc.getFontSize ? doc.getFontSize() : config.fonts.normal.size;
      try {
        if (doc.setFont) doc.setFont('helvetica', 'bold');
        if (doc.setTextColor) doc.setTextColor(33, 150, 83); // #219653
      } catch (e) {}

      doc.text(lines, config.margins.left + 5, y);

      // Restore font/color
      try {
        if (doc.setFont) doc.setFont('helvetica', config.fonts.normal.style || 'normal');
        if (doc.setTextColor) doc.setTextColor(...config.theme.textColor);
        if (doc.setFontSize && prevFontSize) doc.setFontSize(prevFontSize);
      } catch (e) {}

      // Estimate height: font size in pt; approximate line height multiplier
      const currentFontSize = doc.getFontSize ? doc.getFontSize() : config.fonts.normal.size;
      const lineHeightPx = currentFontSize * 0.35 * config.spacing.lineHeight;
      return y + lines.length * lineHeightPx;
    },

    _renderChart: async function (doc, row, options, y, config, contentWidth) {
      const chartType = row[1];
      let chartImage = null;

      // Handle different chart image option patterns for backward compatibility
      if (options) {
        // New pattern: options.chartImages[chartType]
        if (options.chartImages && options.chartImages[chartType]) {
          chartImage = options.chartImages[chartType];
        }
        // Legacy pattern: options.chartImgDataUrl (for single chart)
        else if (options.chartImgDataUrl && chartType) {
          chartImage = options.chartImgDataUrl;
        }
        // Alternative legacy pattern: direct chart image reference
        else if (options[chartType]) {
          chartImage = options[chartType];
        }
      }

      if (chartImage) {
        y += config.spacing.chartPadding;

        const chart = config.chart;
        let chartWidth = chart.defaultWidth;
        let chartHeight = chart.defaultHeight;

        // Ensure chart fits within content area
        if (chartWidth > contentWidth) {
          const ratio = contentWidth / chartWidth;
          chartWidth = contentWidth - 10; // Small margin
          chartHeight = chartHeight * ratio;
        }

        // Calculate x position based on positioning setting
        let xPos = config.margins.left;
        if (chart.positioning === "center") {
          xPos = config.margins.left + (contentWidth - chartWidth) / 2;
        } else if (chart.positioning === "right") {
          xPos = config.margins.left + contentWidth - chartWidth;
        }

        try {
          doc.addImage(
            chartImage,
            "PNG",
            xPos,
            y,
            chartWidth,
            chartHeight,
            undefined,
            "FAST"
          );

          return y + chartHeight + config.spacing.chartPadding;
        } catch (error) {
          console.warn(
            `Failed to add chart image for type: ${chartType}`,
            error
          );
          // Return current y position if image fails to load
          return y;
        }
      }
      return y;
    },

    _renderTableHeader: function (doc, row, y, config, contentWidth) {
      doc.setFillColor(...config.theme.primaryColor);
      doc.setTextColor(...config.theme.headerColor);
      doc.setFont("helvetica", "bold");

      const headerHeight = 8;
      doc.rect(config.margins.left, y - 4, contentWidth, headerHeight, "F");

      // Split the width for two columns
      const col1Width = contentWidth * 0.6;
      const col2Width = contentWidth * 0.4;

      doc.text(row[0], config.margins.left + 5, y);
      doc.text(row[1], config.margins.left + col1Width, y);

      y += headerHeight;
      doc.setFont("helvetica", config.fonts.normal.style);
      doc.setTextColor(...config.theme.textColor);

      return y;
    },

    _renderTableRow: function (doc, row, y, config) {
      const col1Width = (config.chart.maxWidth || 170) * 0.6;

      doc.text(row[0], config.margins.left + 5, y);
      doc.text(String(row[1]), config.margins.left + col1Width, y);

      return y + config.spacing.betweenRows;
    },

    // Simple grid-style table renderer for multi-column data (e.g., Alumni list)
    _renderSimpleTableRow: function (doc, row, y, config, contentWidth, isHeader) {
      const cols = row.length;
      if (cols === 0) return y;

      const xStart = config.margins.left;

      // Column width ratios tuned for Alumni table:
      // [Alumni ID, Name, Email, Contact Number, Department, Year Graduated]
      const defaultRatios = [0.12, 0.18, 0.25, 0.15, 0.22, 0.08];
      const ratios = [];
      let ratioSum = 0;
      for (let i = 0; i < cols; i++) {
        const r = defaultRatios[i] != null ? defaultRatios[i] : 1 / cols;
        ratios.push(r);
        ratioSum += r;
      }

      const colWidths = ratios.map(r => (r / ratioSum) * contentWidth);

      // Prepare fonts/colors
      if (isHeader) {
        doc.setFillColor(...config.theme.primaryColor);
        doc.setTextColor(...config.theme.headerColor);
        doc.setFont("helvetica", "bold");
      } else {
        doc.setFont("helvetica", config.fonts.normal.style);
        doc.setTextColor(...config.theme.textColor);
      }

      // Always use black for table borders
      if (doc.setDrawColor) {
        doc.setDrawColor(0, 0, 0);
      }

      const fontSize = doc.getFontSize ? doc.getFontSize() : config.fonts.normal.size;
      const lineHeight = fontSize * 0.35 * (config.spacing.lineHeight || 1.2);

      // First pass: measure wrapped text for each cell to determine row height
      const cellLines = [];
      let maxLines = 1;
      for (let i = 0; i < cols; i++) {
        const text = row[i] != null ? String(row[i]) : "";
        const availableWidth = Math.max(10, colWidths[i] - 4); // padding inside cell
        const lines = doc.splitTextToSize(text, availableWidth);
        cellLines.push(lines);
        if (lines.length > maxLines) maxLines = lines.length;
      }

      const cellHeight = Math.max(8, lineHeight * maxLines + 4); // 4 for top/bottom padding

      // Draw header background bar once across the row (for header only)
      if (isHeader) {
        doc.rect(xStart, y - 4, contentWidth, cellHeight, "F");
      }

      // Second pass: draw borders and text
      let xCursor = xStart;
      for (let i = 0; i < cols; i++) {
        const w = colWidths[i];
        const lines = cellLines[i];

        // Border (stroke only, color already set to black above)
        doc.rect(xCursor, y - 4, w, cellHeight, "S");

        // Text: start a bit below the top inside the cell
        let textY = y;
        const textX = xCursor + 2;
        lines.forEach(line => {
          doc.text(String(line), textX, textY);
          textY += lineHeight;
        });

        xCursor += w;
      }

      return y + cellHeight;
    },

    _renderDefaultRow: function (doc, row, y, config) {
      if (row.length > 0 && row[0]) {
        doc.text(String(row[0]), config.margins.left, y);
        return y + config.spacing.betweenRows;
      }
      return y;
    },

    _renderDescriptionRow: function(doc, row, y, config, contentWidth) {
      let text = String(row[0] || '').trim();
      if (!text) return y;

      // Add spacing before description
      y += config.spacing.betweenRows * 0.5;

  // Use existing document font/color settings (do not override styling)

      // Calculate available width for centered text
      // contentWidth already accounts for left/right margins
      const maxWidth = Math.max(20, contentWidth - 40); // leave 40 units padding total

  // Split text to fit width
      let lines = doc.splitTextToSize(text, maxWidth);

      // If the text wraps to many lines, try reducing font size slightly to fit better
      const maxLinesBeforeReduce = 6;
      if (lines.length > maxLinesBeforeReduce) {
        const reducedSize = Math.max(8, Math.floor(config.fonts.normal.size * 0.9));
        // Temporarily reduce font size for measurement/rendering
        const prevSize = doc.getFontSize ? doc.getFontSize() : config.fonts.normal.size;
        if (doc.setFontSize) doc.setFontSize(reducedSize);
        lines = doc.splitTextToSize(text, maxWidth);
        // restore previous size for subsequent content
        if (doc.setFontSize) doc.setFontSize(prevSize);
      }

      // Calculate center position (relative to page, so add left margin)
      const centerX = config.margins.left + contentWidth / 2;

      // Center align and render each line with bold green styling for description rows
      // Save previous font and color
      const prevFont = doc.getFont ? doc.getFont() : null;
      const prevFontSize = doc.getFontSize ? doc.getFontSize() : config.fonts.normal.size;
      // Use bold font and green color
      try {
        if (doc.setFont) doc.setFont('helvetica', 'bold');
        if (doc.setTextColor) doc.setTextColor(33, 150, 83); // #219653
      } catch (e) {
        // ignore if setting font/color fails
      }

      lines.forEach(line => {
        doc.text(line, centerX, y, { align: 'center' });
        // use current font size for line spacing (in the same units as unit config)
        const currentFontSize = doc.getFontSize ? doc.getFontSize() : config.fonts.normal.size;
        y += currentFontSize * 0.35 * config.spacing.lineHeight;
      });

      // Restore previous font and color
      try {
        if (doc.setFont && prevFont) {
          // prevFont may not contain style; restore to normal style from config
          doc.setFont('helvetica', config.fonts.normal.style || 'normal');
        }
        if (doc.setTextColor) doc.setTextColor(...config.theme.textColor);
        if (doc.setFontSize && prevFontSize) doc.setFontSize(prevFontSize);
      } catch (e) {
        // ignore
      }

      y += config.spacing.betweenRows * 0.5;
      return y;
    },

    _addFooter: function (doc, config, pageHeight) {
      doc.setFontSize(config.fonts.footer.size);
      doc.setTextColor(...config.theme.lightGray);
      const footerY = pageHeight - config.margins.bottom + 5;
      doc.text(
        "Generated by ALUMytics",
        doc.internal.pageSize.getWidth() / 2,
        footerY,
        { align: "center" }
      );
    },
    // Enhanced Excel export with better formatting
    exportToExcel: function (data, filename = "export.xlsx", options = {}) {
      if (!window.XLSX) {
        alert("SheetJS (XLSX) library not loaded.");
        return Promise.resolve();
      }

      // Process data to remove chart markers for Excel
      const processedData = this._processDataForExcel(data);

      const ws = window.XLSX.utils.aoa_to_sheet(processedData);

      // Apply basic formatting if available
      if (options.formatting !== false) {
        this._applyExcelFormatting(ws, processedData);
      }

      const wb = window.XLSX.utils.book_new();
      window.XLSX.utils.book_append_sheet(
        wb,
        ws,
        options.sheetName || "Sheet1"
      );
      window.XLSX.writeFile(wb, filename);
      return Promise.resolve();
    },

    // Enhanced CSV export
  exportToCSV: function (data, filename = "export.csv", options = {}) {
      // Process data to remove chart markers for CSV
      const processedData = this._processDataForCsv(data);

      const csv = processedData
        .map((row) =>
          row
            .map((cell) => '"' + String(cell).replace(/"/g, '""') + '"')
            .join(",")
        )
        .join("\r\n");

      const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
      const link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      return Promise.resolve();
    },

    // Utility method to process data for Excel (remove descriptions and chart markers)
    _processDataForExcel: function (data) {
      return data.filter((row) => {
        // Skip chart image markers and description rows
        if (row.length === 2 && row[0] === "__CHART_IMAGE__") return false;
        if (this._isDescriptionRow(row)) return false;
        return true;
      });
    },

    // Utility method to process data for CSV (remove descriptions and chart markers)
    _processDataForCsv: function (data) {
      // Remove chart markers and description rows
      return data.filter((row) => {
        if (row.length === 2 && row[0] === "__CHART_IMAGE__") return false;
        if (this._isDescriptionRow(row)) return false;
        return true;
      });
    },

    // Apply basic Excel formatting
    _applyExcelFormatting: function (ws, data) {
      const range = window.XLSX.utils.decode_range(ws["!ref"]);

      // Style headers
      for (let R = range.s.r; R <= range.e.r; ++R) {
        for (let C = range.s.c; C <= range.e.c; ++C) {
          const cell_address = window.XLSX.utils.encode_cell({ c: C, r: R });
          if (!ws[cell_address]) continue;

          const cell_value = ws[cell_address].v;

          // Check if this is a header row (contains common header keywords)
          if (typeof cell_value === "string") {
            // If this is a description row, enable wrapText and style as bold green
            if (this._isDescriptionRow([cell_value])) {
              ws[cell_address].s = ws[cell_address].s || {};
              ws[cell_address].s.alignment = Object.assign({}, ws[cell_address].s.alignment || {}, { wrapText: true });
              ws[cell_address].s.font = Object.assign({}, ws[cell_address].s.font || {}, { bold: true, color: { rgb: '219653' } });
              continue;
            }
            const headerKeywords = [
              "Metric",
              "Industry",
              "Province",
              "Location",
              "Gender",
              "Age Group",
              "Program",
            ];
            if (headerKeywords.includes(cell_value)) {
              ws[cell_address].s = {
                font: { bold: true, color: { rgb: "FFFFFF" } },
                fill: { fgColor: { rgb: "219653" } },
              };
            }
          }
        }
      }
    },

    // Create pre-configured export options for common use cases
    createConfig: function (type = "standard") {
      const configs = {
        standard: {
          orientation: "portrait",
          margins: { top: 20, left: 20, right: 20, bottom: 20 },
        },
        landscape: {
          orientation: "landscape",
          margins: { top: 15, left: 15, right: 15, bottom: 15 },
        },
        compact: {
          orientation: "portrait",
          margins: { top: 15, left: 15, right: 15, bottom: 15 },
          fonts: {
            title: { size: 18, style: "bold" },
            sectionHeader: { size: 14, style: "bold" },
            normal: { size: 10, style: "normal" },
          },
          spacing: {
            afterTitle: 8,
            afterHeader: 6,
            betweenSections: 8,
            betweenRows: 5,
          },
        },
        presentation: {
          orientation: "landscape",
          fonts: {
            title: { size: 24, style: "bold" },
            sectionHeader: { size: 18, style: "bold" },
            normal: { size: 14, style: "normal" },
          },
          chart: {
            defaultWidth: 200,
            defaultHeight: 80,
            positioning: "center",
          },
          spacing: {
            afterTitle: 15,
            afterHeader: 12,
            betweenSections: 15,
            betweenRows: 10,
          },
        },
      };

      return configs[type] || configs.standard;
    },

    // Validate chart images and provide fallbacks
    validateChartImages: function (chartImages) {
      const validImages = {};

      if (!chartImages) {
        return validImages;
      }

      // Handle single chart image (legacy pattern)
      if (typeof chartImages === "string") {
        if (chartImages.startsWith("data:image/")) {
          validImages.default = chartImages;
        }
        return validImages;
      }

      // Handle multiple chart images (new pattern)
      for (const [key, value] of Object.entries(chartImages)) {
        if (
          value &&
          typeof value === "string" &&
          value.startsWith("data:image/")
        ) {
          validImages[key] = value;
        } else {
          console.warn(`Invalid chart image for key: ${key}`);
        }
      }

      return validImages;
    },

    // Debug method to help troubleshoot issues
    debugLibraries: function () {
      console.log("=== Export Libraries Debug Info ===");
      console.log("jsPDF loaded:", !!window.jspdf);
      console.log("SheetJS (XLSX) loaded:", !!window.XLSX);

      if (window.jspdf) {
        console.log("jsPDF version:", window.jspdf.version || "Unknown");
      }

      if (window.XLSX) {
        console.log("XLSX version:", window.XLSX.version || "Unknown");
      }

      console.log("Default PDF config:", this.defaultPDFConfig);
      console.log(
        "Available config presets:",
        Object.keys({
          standard: 1,
          landscape: 1,
          compact: 1,
          presentation: 1,
        })
      );
    },

    // Method to analyze data structure and suggest optimal configuration
    analyzeData: function (data) {
      const analysis = {
        totalRows: data.length,
        sectionHeaders: 0,
        chartMarkers: 0,
        tableRows: 0,
        blankRows: 0,
        chartTypes: [],
        suggestedConfig: "standard",
      };

      data.forEach((row) => {
        if (this._isSectionHeader(row)) {
          analysis.sectionHeaders++;
        } else if (this._isChartMarker(row)) {
          analysis.chartMarkers++;
          if (row[1] && !analysis.chartTypes.includes(row[1])) {
            analysis.chartTypes.push(row[1]);
          }
        } else if (this._isTableRow(row)) {
          analysis.tableRows++;
        } else if (this._isBlankRow(row)) {
          analysis.blankRows++;
        }
      });

      // Suggest configuration based on analysis
      if (analysis.chartMarkers > 3) {
        analysis.suggestedConfig = "landscape";
      } else if (analysis.totalRows > 50) {
        analysis.suggestedConfig = "compact";
      }

      return analysis;
    },
  };

  // Backward compatibility helpers
  ExportLibraries.exportToPDF_v1 = ExportLibraries.exportToPDF;

  // Export to Word (DOCX) format - creates a downloadable Word document with plain text
  ExportLibraries.exportToWord = function (data, filename = "export.docx", options = {}, title = "") {
    try {
      console.log('exportToWord called with filename:', filename);
      
      // Process data to remove chart markers for Word
      const processedData = this._processDataForWord(data);
      console.log('Processed data rows:', processedData.length);

      // Build plain text content (no HTML styling)
      let textContent = '';

      // Add title
      if (title) {
        textContent += title + '\n';
        textContent += '='.repeat(title.length) + '\n\n';
      }

      // Process rows as plain text
      processedData.forEach(row => {
        if (this._isSectionHeader(row)) {
          textContent += '\n' + row[0] + '\n';
          textContent += '-'.repeat(row[0].length) + '\n';
        } else if (this._isTableHeader(row) || (row.length === 2 && row[0] && row[1])) {
          // Simple text table format
          const col1 = String(row[0]).padEnd(40);
          const col2 = String(row[1]);
          textContent += col1 + col2 + '\n';
          
          if (this._isTableHeader(row)) {
            textContent += '-'.repeat(40) + '-'.repeat(20) + '\n';
          }
        } else if (this._isDescriptionRow(row)) {
          textContent += '\n' + row[0] + '\n\n';
        } else if (this._isBlankRow(row)) {
          textContent += '\n';
        } else if (row.length > 0 && row[0]) {
          textContent += row[0] + '\n';
        }
      });

      // Add footer
      textContent += '\n' + '='.repeat(50) + '\n';
      textContent += 'Generated by ALUMytics\n';

      console.log('Text content length:', textContent.length);

      // Create blob with plain text (no styling)
      const blob = new Blob([textContent], { type: 'text/plain;charset=utf-8' });
      
      console.log('Blob created, size:', blob.size);

      // Create download link and trigger download
      const url = URL.createObjectURL(blob);
      console.log('Object URL created:', url);
      
      const link = document.createElement('a');
      link.href = url;
      link.download = filename;
      link.style.display = 'none';
      
      document.body.appendChild(link);
      console.log('Link appended to body');
      
      link.click();
      console.log('Link clicked, download should start');
      
      // Cleanup after a delay
      setTimeout(() => {
        try {
          document.body.removeChild(link);
          URL.revokeObjectURL(url);
          console.log('Cleanup completed');
        } catch (e) {
          console.warn('Cleanup error:', e);
        }
      }, 500);

      return Promise.resolve();
    } catch (error) {
      console.error("Word export error:", error);
      alert("Error exporting to Word: " + error.message);
      return Promise.resolve();
    }
  };

  // Helper to escape HTML special characters
  ExportLibraries._escapeHtml = function (text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return String(text).replace(/[&<>"']/g, m => map[m]);
  };

  // Utility method to process data for Word (remove descriptions and chart markers)
  ExportLibraries._processDataForWord = function (data) {
    return data.filter((row) => {
      // Skip chart image markers
      if (row.length === 2 && row[0] === "__CHART_IMAGE__") return false;
      return true;
    });
  };

  // Convenience method for quick exports with minimal configuration
  ExportLibraries.quickExport = function (
    data,
    filename,
    formats = ["pdf"],
    options = {}
  ) {
    const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, "-");
    const baseFilename = filename || `export_${timestamp}`;

    const promises = [];

    if (formats.includes("pdf")) {
      const pdfTitle = options.title || options.pdfTitle || "";
      promises.push(this.exportToPDF(data, `${baseFilename}.pdf`, options, pdfTitle));
    }
    if (formats.includes("excel")) {
      promises.push(
        Promise.resolve(
          this.exportToExcel(data, `${baseFilename}.xlsx`, options)
        )
      );
    }
    if (formats.includes("csv")) {
      promises.push(
        Promise.resolve(this.exportToCSV(data, `${baseFilename}.csv`, options))
      );
    }
    if (formats.includes("word")) {
      const wordTitle = options.title || options.wordTitle || "";
      promises.push(
        Promise.resolve(
          this.exportToWord(data, `${baseFilename}.docx`, options, wordTitle)
        )
      );
    }

    return Promise.all(promises);
  };

  // Usage examples (commented out)
  /*
  // Basic usage (backward compatible):
  ExportLibraries.exportToPDF(data, 'report.pdf', { chartImages }, 'My Report');
  
  // Enhanced usage with configuration:
  ExportLibraries.exportToPDF(data, 'report.pdf', {
    chartImages: chartImages,
    config: ExportLibraries.createConfig('landscape')
  }, 'My Dashboard Report');
  
  // Custom configuration:
  ExportLibraries.exportToPDF(data, 'report.pdf', {
    chartImages: chartImages,
    config: {
      orientation: 'landscape',
      theme: {
        primaryColor: [255, 0, 0], // Red theme
      },
      chart: {
        positioning: 'left',
        defaultWidth: 180
      }
    }
  }, 'Custom Report');
  
  // Quick multi-format export:
  ExportLibraries.quickExport(data, 'dashboard_export', ['pdf', 'excel'], {
    chartImages: chartImages,
    config: ExportLibraries.createConfig('presentation')
  });
  
  // Data analysis:
  const analysis = ExportLibraries.analyzeData(data);
  console.log('Suggested config:', analysis.suggestedConfig);
  console.log('Chart types found:', analysis.chartTypes);
  */

  global.ExportLibraries = ExportLibraries;
  global.exportLibrary = ExportLibraries; // For compatibility with all modules
})(window);
