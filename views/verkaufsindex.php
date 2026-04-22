<?php
/**
 * Ansicht 1: Eigen vs. Fremd Verkaufsindex
 */
$aggregation = $_GET['aggregation'] ?? 'day';
if (!in_array($aggregation, ['day', 'week', 'month'])) {
    $aggregation = 'day';
}
?>

<h2 class="section-title">Verkaufsindex: Eigenprodukte vs. Fremdprodukte</h2>

<div class="controls">
    <div>
        <label for="vi-aggregation">Aggregation:</label>
        <select id="vi-aggregation" onchange="loadVerkaufsindex()">
            <option value="day" <?php if ($aggregation === 'day') echo 'selected'; ?>>Pro Tag</option>
            <option value="week" <?php if ($aggregation === 'week') echo 'selected'; ?>>Pro Woche</option>
            <option value="month" <?php if ($aggregation === 'month') echo 'selected'; ?>>Pro Monat</option>
        </select>
    </div>
    <div>
        <label>
            <input type="checkbox" id="vi-show-absolut" onchange="toggleVerkaufsindexMode()">
            Absolute Zahlen anzeigen
        </label>
    </div>
</div>

<!-- KPI-Kacheln fuer Anteile -->
<div class="kpi-grid" id="vi-kpi-grid">
    <div class="kpi-card status-eigen">
        <div class="kpi-label">Eigenprodukte (Polar)</div>
        <div class="kpi-value text-eigen" id="vi-eigen-total">-</div>
        <div class="kpi-sub" id="vi-eigen-anteil">-</div>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #FF9800;">
        <div class="kpi-label">Fremdprodukte</div>
        <div class="kpi-value text-fremd" id="vi-fremd-total">-</div>
        <div class="kpi-sub" id="vi-fremd-anteil">-</div>
    </div>
</div>

<!-- Index-Chart -->
<div class="chart-container tall" id="vi-chart-container">
    <canvas id="verkaufsindexChart"></canvas>
</div>

<!-- Absolut-Tabelle -->
<div class="section" id="vi-table-section" style="display:none;">
    <h3 class="section-title">Absolute Abgaenge pro Periode</h3>
    <table class="data-table" id="vi-table">
        <thead>
            <tr>
                <th>Periode</th>
                <th>Eigenprodukte</th>
                <th>Fremdprodukte</th>
                <th>Gesamt</th>
            </tr>
        </thead>
        <tbody id="vi-table-body"></tbody>
    </table>
</div>

<script>
let viChart = null;
let viData = null;

function loadVerkaufsindex() {
    const aggregation = document.getElementById('vi-aggregation').value;
    const timePeriod = document.getElementById('global-time-period').value;

    showLoading('vi-chart-container');

    fetchApi('verkaufsindex.php', {
        time_period: timePeriod,
        aggregation: aggregation
    }, function(data) {
        hideLoading('vi-chart-container');
        viData = data;
        renderVerkaufsindex();
    }, function() {
        hideLoading('vi-chart-container');
    });
}

function renderVerkaufsindex() {
    if (!viData) return;

    const showAbsolut = document.getElementById('vi-show-absolut').checked;

    // KPI-Kacheln
    document.getElementById('vi-eigen-total').textContent = formatNumber(viData.eigen_total) + ' Stk.';
    document.getElementById('vi-fremd-total').textContent = formatNumber(viData.fremd_total) + ' Stk.';
    document.getElementById('vi-eigen-anteil').textContent = 'Anteil: ' + viData.eigen_anteil + '%';
    document.getElementById('vi-fremd-anteil').textContent = 'Anteil: ' + viData.fremd_anteil + '%';

    // Chart
    const eigenValues = showAbsolut ? viData.eigen_absolut : viData.eigen_index;
    const fremdValues = showAbsolut ? viData.fremd_absolut : viData.fremd_index;
    const yLabel = showAbsolut ? 'Abgang (Stueck)' : 'Index (Basis = 100)';

    const chartData = {
        labels: viData.labels,
        datasets: [
            {
                label: 'Eigenprodukte (Polar)',
                data: eigenValues,
                borderColor: COLOR_EIGEN,
                backgroundColor: COLOR_EIGEN.replace(')', ', 0.1)').replace('rgb(', 'rgba('),
                tension: 0.3,
                fill: true,
            },
            {
                label: 'Fremdprodukte',
                data: fremdValues,
                borderColor: COLOR_FREMD,
                backgroundColor: COLOR_FREMD.replace(')', ', 0.1)').replace('rgb(', 'rgba('),
                tension: 0.3,
                fill: true,
            }
        ]
    };

    if (viChart) {
        viChart.data = chartData;
        viChart.options.scales.y.title.text = yLabel;
        viChart.update();
    } else {
        const ctx = document.getElementById('verkaufsindexChart').getContext('2d');
        viChart = new Chart(ctx, {
            type: 'line',
            data: chartData,
            options: getLineChartOptions('Verkaufsindex: Eigen vs. Fremd', 'Periode', yLabel)
        });
    }

    // Tabelle
    const tbody = document.getElementById('vi-table-body');
    tbody.innerHTML = '';
    for (let i = 0; i < viData.labels.length; i++) {
        const eigen = viData.eigen_absolut[i] || 0;
        const fremd = viData.fremd_absolut[i] || 0;
        tbody.innerHTML += '<tr>' +
            '<td>' + viData.labels[i] + '</td>' +
            '<td>' + formatNumber(eigen) + '</td>' +
            '<td>' + formatNumber(fremd) + '</td>' +
            '<td><strong>' + formatNumber(eigen + fremd) + '</strong></td>' +
            '</tr>';
    }
}

function toggleVerkaufsindexMode() {
    const showAbsolut = document.getElementById('vi-show-absolut').checked;
    document.getElementById('vi-table-section').style.display = showAbsolut ? 'block' : 'none';
    renderVerkaufsindex();
}

$(document).ready(function() {
    loadVerkaufsindex();
});
</script>
