// Utility for chart colors
function getDistinctPalette(count) {
  // A palette of 20 distinguishable colors
  const palette = [
    '#1f77b4', // muted blue
    '#ff7f0e', // safety orange
    '#2ca02c', // cooked asparagus green
    '#d62728', // brick red
    '#9467bd', // muted purple
    '#8c564b', // chestnut
    '#e377c2', // raspberry yogurt pink
    '#7f7f7f', // middle gray
    '#bcbd22', // curry yellow-green
    '#17becf', // blue-teal
    '#393b79',
    '#637939',
    '#8c6d31',
    '#843c39',
    '#7b4173',
    '#3182bd',
    '#31a354',
    '#756bb1',
    '#e6550d',
    '#fdd0a2'
  ];
  if (count <= palette.length) return palette.slice(0, count);
  // Repeat palette with slight opacity variance if more needed
  const result = [];
  for (let i = 0; i < count; i++) {
    const base = palette[i % palette.length];
    result.push(base);
  }
  return result;
}

// Helper to get colors with alpha
function applyAlpha(colors, alpha) {
  return colors.map(c => {
    // convert hex to rgba
    const bigint = parseInt(c.replace('#',''), 16);
    const r = (bigint >> 16) & 255;
    const g = (bigint >> 8) & 255;
    const b = bigint & 255;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
  });
}

// Expose for other scripts
window.getDistinctPalette = getDistinctPalette;
window.applyAlpha = applyAlpha;
