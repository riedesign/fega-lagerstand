/**
 * Chart.js Hilfsfunktionen und Farbpaletten
 */
const CHART_COLORS = [
    'rgb(54, 162, 235)',
    'rgb(255, 99, 132)',
    'rgb(75, 192, 192)',
    'rgb(255, 206, 86)',
    'rgb(153, 102, 255)',
    'rgb(255, 159, 64)',
    'rgb(199, 199, 199)',
    'rgb(83, 102, 255)',
    'rgb(255, 99, 255)',
    'rgb(99, 255, 132)',
];

const COLOR_EIGEN = 'rgb(33, 150, 243)';
const COLOR_FREMD = 'rgb(255, 152, 0)';
const COLOR_CRITICAL = 'rgb(244, 67, 54)';
const COLOR_OK = 'rgb(76, 175, 80)';

/**
 * Gibt eine Farbe mit optionaler Transparenz zurueck.
 */
function chartColor(index, alpha) {
    const color = CHART_COLORS[index % CHART_COLORS.length];
    if (alpha !== undefined) {
        return color.replace(')', ', ' + alpha + ')').replace('rgb(', 'rgba(');
    }
    return color;
}

/**
 * Erstellt eine Sparkline (Mini-Chart) in einem Canvas-Element.
 */
function createSparkline(canvasId, data, color) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;

    return new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map((_, i) => i),
            datasets: [{
                data: data,
                borderColor: color || COLOR_EIGEN,
                borderWidth: 2,
                pointRadius: 0,
                fill: false,
                tension: 0.3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: {
                x: { display: false },
                y: { display: false }
            }
        }
    });
}

/**
 * Standard Line-Chart Optionen.
 */
function getLineChartOptions(title, xLabel, yLabel) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            title: { display: !!title, text: title || '' }
        },
        scales: {
            x: { title: { display: !!xLabel, text: xLabel || '' } },
            y: { title: { display: !!yLabel, text: yLabel || '' }, beginAtZero: true }
        }
    };
}
