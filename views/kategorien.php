<?php
/**
 * Ansicht 3: Kategorie-/Marken-Vergleich
 */
$aggregation = $_GET['aggregation'] ?? 'week';
if (!in_array($aggregation, ['day', 'week', 'month'])) {
    $aggregation = 'week';
}
?>

<h2 class="section-title">Marken-Vergleich & Marktanteile</h2>

<div class="controls">
    <div>
        <label for="kat-aggregation">Aggregation:</label>
        <select id="kat-aggregation" onchange="loadKategorien()">
            <option value="day" <?php if ($aggregation === 'day') echo 'selected'; ?>>Pro Tag</option>
            <option value="week" <?php if ($aggregation === 'week') echo 'selected'; ?>>Pro Woche</option>
            <option value="month" <?php if ($aggregation === 'month') echo 'selected'; ?>>Pro Monat</option>
        </select>
    </div>
</div>

<!-- Marktanteile: Doughnut + Horizontal Bar nebeneinander -->
<div class="two-columns">
    <div class="section">
        <h3 class="section-title">Marktanteile (Abgang %)</h3>
        <div class="chart-container" id="kat-doughnut-container">
            <canvas id="marktanteilChart"></canvas>
        </div>
    </div>

    <div class="section">
        <h3 class="section-title">Absolute Abgaenge pro Marke</h3>
        <div class="chart-container" id="kat-bar-container">
            <canvas id="markenBarChart"></canvas>
        </div>
    </div>
</div>

<!-- Zeitverlauf: Stacked Bar -->
<div class="section">
    <h3 class="section-title">Abgaenge im Zeitverlauf (nach Marke)</h3>
    <div class="chart-container tall" id="kat-stacked-container">
        <canvas id="markenStackedChart"></canvas>
    </div>
</div>

<!-- Marktanteile-Tabelle -->
<div class="section">
    <h3 class="section-title">Detailtabelle</h3>
    <table class="data-table" id="kat-table">
        <thead>
            <tr>
                <th>Marke</th>
                <th>Abgaenge (Stueck)</th>
                <th>Marktanteil (%)</th>
                <th>Anzahl Artikel</th>
            </tr>
        </thead>
        <tbody id="kat-table-body"></tbody>
    </table>
</div>

<script>
let doughnutChart = null;
let barChart = null;
let stackedChart = null;

function loadKategorien() {
    const aggregation = document.getElementById('kat-aggregation').value;
    const timePeriod = document.getElementById('global-time-period').value;

    showLoading('kat-doughnut-container');

    fetchApi('kategorien.php', {
        time_period: timePeriod,
        aggregation: aggregation
    }, function(data) {
        hideLoading('kat-doughnut-container');
        renderKategorien(data);
    }, function() {
        hideLoading('kat-doughnut-container');
    });
}

function renderKategorien(data) {
    const marken = data.marktanteile;
    const labels = marken.map(m => m.marke);
    const abgaenge = marken.map(m => m.abgang);
    const anteile = marken.map(m => m.anteil);
    const colors = labels.map((_, i) => chartColor(i));
    const bgColors = labels.map((_, i) => chartColor(i, 0.7));

    // Doughnut Chart
    if (doughnutChart) doughnutChart.destroy();
    doughnutChart = new Chart(document.getElementById('marktanteilChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: anteile,
                backgroundColor: bgColors,
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'right' },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.label + ': ' + ctx.parsed + '% (' + formatNumber(abgaenge[ctx.dataIndex]) + ' Stk.)';
                        }
                    }
                }
            }
        }
    });

    // Horizontal Bar Chart
    if (barChart) barChart.destroy();
    barChart = new Chart(document.getElementById('markenBarChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Abgaenge (Stueck)',
                data: abgaenge,
                backgroundColor: bgColors,
                borderColor: colors,
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, title: { display: true, text: 'Abgang (Stueck)' } }
            }
        }
    });

    // Stacked Bar Chart (Zeitverlauf)
    const stackedDatasets = data.zeitverlauf.map((item, i) => ({
        label: item.marke,
        data: item.values,
        backgroundColor: chartColor(i, 0.7),
        borderColor: chartColor(i),
        borderWidth: 1,
    }));

    if (stackedChart) stackedChart.destroy();
    stackedChart = new Chart(document.getElementById('markenStackedChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: data.period_labels,
            datasets: stackedDatasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top' },
                title: { display: true, text: 'Abgaenge pro Marke im Zeitverlauf' }
            },
            scales: {
                x: { stacked: true, title: { display: true, text: 'Periode' } },
                y: { stacked: true, beginAtZero: true, title: { display: true, text: 'Abgang (Stueck)' } }
            }
        }
    });

    // Tabelle
    const tbody = document.getElementById('kat-table-body');
    tbody.innerHTML = '';
    marken.forEach(function(m) {
        tbody.innerHTML += '<tr>' +
            '<td><strong>' + m.marke + '</strong></td>' +
            '<td>' + formatNumber(m.abgang) + '</td>' +
            '<td>' + m.anteil + '%</td>' +
            '<td>' + m.artikel + '</td>' +
            '</tr>';
    });
}

$(document).ready(function() {
    loadKategorien();
});
</script>
