<?php
/**
 * Ansicht: Vergleich Eigenprodukte vs. Fremdprodukte
 * Kombiniert Verkaufsindex und Marken-Vergleich auf einer Seite.
 */
$aggregation = $_GET['aggregation'] ?? 'day';
if (!in_array($aggregation, ['day', 'week', 'month'])) {
    $aggregation = 'day';
}
?>

<h2 class="section-title">Vergleich: Eigenprodukte vs. Fremdprodukte</h2>

<div class="controls">
    <div>
        <label for="vgl-aggregation">Aggregation:</label>
        <select id="vgl-aggregation" onchange="loadVergleich()">
            <option value="day" <?php if ($aggregation === 'day') echo 'selected'; ?>>Pro Tag</option>
            <option value="week" <?php if ($aggregation === 'week') echo 'selected'; ?>>Pro Woche</option>
            <option value="month" <?php if ($aggregation === 'month') echo 'selected'; ?>>Pro Monat</option>
        </select>
    </div>
    <div>
        <label>
            <input type="checkbox" id="vgl-show-absolut" onchange="renderVerkaufsindex()">
            Absolute Zahlen statt Index
        </label>
    </div>
</div>

<!-- Anteils-Kacheln -->
<div class="kpi-grid" id="vgl-kpi-grid">
    <div class="kpi-card status-eigen">
        <div class="kpi-label">Eigenprodukte (Polar)</div>
        <div class="kpi-value text-eigen" id="vgl-eigen-total">-</div>
        <div class="kpi-sub" id="vgl-eigen-anteil">-</div>
    </div>
    <div class="kpi-card" style="border-left: 4px solid #FF9800;">
        <div class="kpi-label">Fremdprodukte</div>
        <div class="kpi-value text-fremd" id="vgl-fremd-total">-</div>
        <div class="kpi-sub" id="vgl-fremd-anteil">-</div>
    </div>
</div>

<!-- Verkaufsindex Chart -->
<div class="section">
    <h3 class="section-title">Verkaufsindex im Zeitverlauf</h3>
    <div class="chart-container tall" id="vgl-index-container">
        <canvas id="vergleichIndexChart"></canvas>
    </div>
</div>

<!-- Marken-Vergleich: Doughnut + Bar -->
<div class="two-columns">
    <div class="section">
        <h3 class="section-title">Marktanteile (Abgang %)</h3>
        <div class="chart-container" id="vgl-doughnut-container">
            <canvas id="vergleichDoughnutChart"></canvas>
        </div>
    </div>
    <div class="section">
        <h3 class="section-title">Abgaenge pro Marke</h3>
        <div class="chart-container" id="vgl-bar-container">
            <canvas id="vergleichBarChart"></canvas>
        </div>
    </div>
</div>

<!-- Zeitverlauf Stacked -->
<div class="section">
    <h3 class="section-title">Abgaenge im Zeitverlauf (nach Marke)</h3>
    <div class="chart-container tall" id="vgl-stacked-container">
        <canvas id="vergleichStackedChart"></canvas>
    </div>
</div>

<!-- Detailtabelle -->
<div class="section">
    <h3 class="section-title">Detailtabelle Marktanteile</h3>
    <table class="data-table" id="vgl-table">
        <thead>
            <tr><th>Marke</th><th>Abgaenge (Stk.)</th><th>Marktanteil (%)</th><th>Anzahl Artikel</th></tr>
        </thead>
        <tbody id="vgl-table-body"></tbody>
    </table>
</div>

<script>
var viChart = null, viData = null;
var doughnutChart = null, barChart = null, stackedChart = null;

function loadVergleich() {
    var aggregation = document.getElementById('vgl-aggregation').value;
    var timePeriod = document.getElementById('global-time-period').value;

    showLoading('vgl-index-container');

    // Beide APIs parallel laden
    var loaded = 0;
    var indexData = null, katData = null;

    function checkDone() {
        loaded++;
        if (loaded === 2) {
            hideLoading('vgl-index-container');
            if (indexData) renderVerkaufsindex();
            if (katData) renderKategorien(katData);
        }
    }

    fetchApi('verkaufsindex.php', { time_period: timePeriod, aggregation: aggregation }, function(data) {
        indexData = data;
        viData = data;
        checkDone();
    }, checkDone);

    fetchApi('kategorien.php', { time_period: timePeriod, aggregation: aggregation }, function(data) {
        katData = data;
        checkDone();
    }, checkDone);
}

function renderVerkaufsindex() {
    if (!viData) return;
    var showAbsolut = document.getElementById('vgl-show-absolut').checked;

    // KPI-Kacheln
    document.getElementById('vgl-eigen-total').textContent = formatNumber(viData.eigen_total) + ' Stk.';
    document.getElementById('vgl-fremd-total').textContent = formatNumber(viData.fremd_total) + ' Stk.';
    document.getElementById('vgl-eigen-anteil').textContent = 'Anteil: ' + viData.eigen_anteil + '%';
    document.getElementById('vgl-fremd-anteil').textContent = 'Anteil: ' + viData.fremd_anteil + '%';

    var eigenValues = showAbsolut ? viData.eigen_absolut : viData.eigen_index;
    var fremdValues = showAbsolut ? viData.fremd_absolut : viData.fremd_index;
    var yLabel = showAbsolut ? 'Abgang (Stueck)' : 'Index (Basis = 100)';

    var chartData = {
        labels: viData.labels,
        datasets: [
            {
                label: 'Eigenprodukte (Polar)',
                data: eigenValues,
                borderColor: COLOR_EIGEN,
                backgroundColor: COLOR_EIGEN.replace(')', ', 0.1)').replace('rgb(', 'rgba('),
                tension: 0.3, fill: true,
            },
            {
                label: 'Fremdprodukte',
                data: fremdValues,
                borderColor: COLOR_FREMD,
                backgroundColor: COLOR_FREMD.replace(')', ', 0.1)').replace('rgb(', 'rgba('),
                tension: 0.3, fill: true,
            }
        ]
    };

    if (viChart) { viChart.destroy(); }
    var ctx = document.getElementById('vergleichIndexChart').getContext('2d');
    viChart = new Chart(ctx, {
        type: 'line',
        data: chartData,
        options: getLineChartOptions('Verkaufsindex: Eigen vs. Fremd', 'Periode', yLabel)
    });
}

function renderKategorien(data) {
    var marken = data.marktanteile;
    var labels = marken.map(function(m) { return m.marke; });
    var abgaenge = marken.map(function(m) { return m.abgang; });
    var anteile = marken.map(function(m) { return m.anteil; });
    var colors = labels.map(function(_, i) { return chartColor(i); });
    var bgColors = labels.map(function(_, i) { return chartColor(i, 0.7); });

    // Doughnut
    if (doughnutChart) doughnutChart.destroy();
    doughnutChart = new Chart(document.getElementById('vergleichDoughnutChart').getContext('2d'), {
        type: 'doughnut',
        data: { labels: labels, datasets: [{ data: anteile, backgroundColor: bgColors, borderWidth: 2 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' },
                tooltip: { callbacks: { label: function(ctx) {
                    return ctx.label + ': ' + ctx.parsed + '% (' + formatNumber(abgaenge[ctx.dataIndex]) + ' Stk.)';
                }}}
            }
        }
    });

    // Bar
    if (barChart) barChart.destroy();
    barChart = new Chart(document.getElementById('vergleichBarChart').getContext('2d'), {
        type: 'bar',
        data: { labels: labels, datasets: [{ label: 'Abgaenge', data: abgaenge, backgroundColor: bgColors, borderColor: colors, borderWidth: 1 }] },
        options: {
            responsive: true, maintainAspectRatio: false, indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, title: { display: true, text: 'Abgang (Stk.)' } } }
        }
    });

    // Stacked
    var stackedDatasets = data.zeitverlauf.map(function(item, i) {
        return { label: item.marke, data: item.values, backgroundColor: chartColor(i, 0.7), borderColor: chartColor(i), borderWidth: 1 };
    });
    if (stackedChart) stackedChart.destroy();
    stackedChart = new Chart(document.getElementById('vergleichStackedChart').getContext('2d'), {
        type: 'bar',
        data: { labels: data.period_labels, datasets: stackedDatasets },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { position: 'top' }, title: { display: true, text: 'Abgaenge nach Marke' } },
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } }
        }
    });

    // Tabelle
    var tbody = document.getElementById('vgl-table-body');
    tbody.innerHTML = '';
    marken.forEach(function(m) {
        tbody.innerHTML += '<tr><td><strong>' + m.marke + '</strong></td><td>' + formatNumber(m.abgang) + '</td><td>' + m.anteil + '%</td><td>' + m.artikel + '</td></tr>';
    });
}

$(document).ready(function() { loadVergleich(); });
</script>
