<?php
/**
 * Ansicht 2: Einzelprodukt-Detailansicht
 */
require_once __DIR__ . '/../includes/queries/produktdetail.php';

$artikel_liste = get_artikel_liste($conn);
$selected_han = $_GET['han'] ?? '';
?>

<h2 class="section-title">Produktdetail-Analyse</h2>

<div class="controls">
    <div>
        <label for="pd-artikel">Artikel auswaehlen:</label>
        <select id="pd-artikel" onchange="loadProduktdetail()" style="min-width: 300px;">
            <option value="">-- Bitte Artikel waehlen --</option>
            <?php foreach ($artikel_liste as $item): ?>
            <option value="<?php echo htmlspecialchars($item['han']); ?>"
                <?php echo ($selected_han === $item['han']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($item['artikelname'] . ' (' . $item['han'] . ')'); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<!-- KPI-Kacheln (initial versteckt) -->
<div class="kpi-grid" id="pd-kpis" style="display:none;">
    <div class="kpi-card" id="pd-kpi-bestand">
        <div class="kpi-label">Aktueller Bestand</div>
        <div class="kpi-value" id="pd-bestand-val">-</div>
        <div class="kpi-sub" id="pd-bestand-sub"></div>
    </div>
    <div class="kpi-card" id="pd-kpi-reichweite">
        <div class="kpi-label">Reichweite</div>
        <div class="kpi-value" id="pd-reichweite-val">-</div>
        <div class="kpi-sub" id="pd-reichweite-sub"></div>
    </div>
    <div class="kpi-card status-neutral">
        <div class="kpi-label">Avg. Verbrauch / Tag</div>
        <div class="kpi-value" id="pd-avg-val">-</div>
        <div class="kpi-sub" id="pd-avg-sub"></div>
    </div>
    <div class="kpi-card status-neutral">
        <div class="kpi-label">Verkaufstrend</div>
        <div class="kpi-value" id="pd-trend-val">-</div>
        <div class="kpi-sub" id="pd-trend-sub"></div>
    </div>
</div>

<!-- Combo-Chart: Lagerstand + Abgaenge -->
<div class="chart-container tall" id="pd-chart-container" style="display:none;">
    <canvas id="produktDetailChart"></canvas>
</div>

<!-- Prognose-Chart -->
<div class="section" id="pd-prognose-section" style="display:none;">
    <h3 class="section-title">Bestandsprognose (linear)</h3>
    <div class="chart-container" id="pd-prognose-container">
        <canvas id="prognoseChart"></canvas>
    </div>
</div>

<div id="pd-no-selection" style="text-align:center; padding:60px 20px; color:#999;">
    Bitte waehlen Sie einen Artikel aus dem Dropdown oben.
</div>

<script>
let pdChart = null;
let progChart = null;

function loadProduktdetail() {
    const han = document.getElementById('pd-artikel').value;
    if (!han) {
        document.getElementById('pd-no-selection').style.display = 'block';
        document.getElementById('pd-kpis').style.display = 'none';
        document.getElementById('pd-chart-container').style.display = 'none';
        document.getElementById('pd-prognose-section').style.display = 'none';
        return;
    }

    document.getElementById('pd-no-selection').style.display = 'none';
    showLoading('pd-chart-container');

    const timePeriod = document.getElementById('global-time-period').value;

    fetchApi('produktdetail.php', { han: han, time_period: timePeriod }, function(data) {
        hideLoading('pd-chart-container');

        if (data.error) {
            document.getElementById('pd-no-selection').style.display = 'block';
            document.getElementById('pd-no-selection').textContent = data.error;
            return;
        }

        renderProduktdetail(data);
    }, function() {
        hideLoading('pd-chart-container');
    });
}

function renderProduktdetail(data) {
    // KPI-Kacheln
    document.getElementById('pd-kpis').style.display = '';
    document.getElementById('pd-chart-container').style.display = '';
    document.getElementById('pd-prognose-section').style.display = '';

    // Bestand
    document.getElementById('pd-bestand-val').textContent = formatNumber(data.current_stock) + ' Stk.';
    document.getElementById('pd-bestand-sub').textContent = data.is_eigen ? 'Eigenprodukt (Polar)' : 'Fremdprodukt';
    const bestandCard = document.getElementById('pd-kpi-bestand');
    bestandCard.className = 'kpi-card ' + (data.status === 'critical' ? 'status-critical' : data.status === 'obsolete' ? 'status-warn' : 'status-ok');

    // Reichweite
    const rwVal = data.reichweite === 'unbegrenzt' ? '∞' : data.reichweite + ' Tage';
    document.getElementById('pd-reichweite-val').textContent = rwVal;
    document.getElementById('pd-reichweite-sub').textContent = 'Lead Time: ' + data.lead_time + ' Tage';
    const rwCard = document.getElementById('pd-kpi-reichweite');
    rwCard.className = 'kpi-card ' + (data.status === 'critical' ? 'status-critical' : 'status-ok');

    // Avg Verbrauch
    document.getElementById('pd-avg-val').textContent = data.avg_daily;
    document.getElementById('pd-avg-sub').textContent = 'Gesamt: ' + formatNumber(data.total_abgang) + ' Stk. | Letzter Abgang: ' + data.last_abgang;

    // Trend
    const trendDir = data.trend.direction;
    document.getElementById('pd-trend-val').innerHTML = trendArrow(trendDir) + ' ' + trendDir;
    document.getElementById('pd-trend-sub').textContent = 'Steigung: ' + data.trend.slope;

    // Combo-Chart: Lagerstand (Line) + Abgaenge (Bar) + Moving Average (Line)
    const comboData = {
        labels: data.lager_labels,
        datasets: [
            {
                type: 'line',
                label: 'Lagerstand',
                data: data.lager_values,
                borderColor: COLOR_EIGEN,
                backgroundColor: 'rgba(33, 150, 243, 0.05)',
                tension: 0.2,
                fill: true,
                yAxisID: 'y',
                order: 1,
            },
            {
                type: 'bar',
                label: 'Tagesabgang',
                data: padArray(data.abgang_values, data.lager_labels.length),
                backgroundColor: 'rgba(255, 99, 132, 0.6)',
                borderColor: 'rgb(255, 99, 132)',
                borderWidth: 1,
                yAxisID: 'y1',
                order: 2,
            },
            {
                type: 'line',
                label: 'Avg. 7 Tage',
                data: padArray(data.moving_avg, data.lager_labels.length),
                borderColor: 'rgb(255, 159, 64)',
                borderDash: [5, 5],
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.3,
                fill: false,
                yAxisID: 'y1',
                order: 0,
            }
        ]
    };

    const comboOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'top' },
            title: { display: true, text: data.artikelname + ' (' + data.han + ')' }
        },
        scales: {
            x: { title: { display: true, text: 'Datum' } },
            y: {
                type: 'linear',
                position: 'left',
                title: { display: true, text: 'Lagerstand' },
                beginAtZero: true,
            },
            y1: {
                type: 'linear',
                position: 'right',
                title: { display: true, text: 'Abgang' },
                beginAtZero: true,
                grid: { drawOnChartArea: false },
            }
        }
    };

    if (pdChart) {
        pdChart.destroy();
    }
    const ctx = document.getElementById('produktDetailChart').getContext('2d');
    pdChart = new Chart(ctx, { type: 'bar', data: comboData, options: comboOptions });

    // Prognose-Chart
    const prognoseData = {
        labels: data.prognose_labels,
        datasets: [{
            label: 'Prognostizierter Bestand',
            data: data.prognose_values,
            borderColor: COLOR_CRITICAL,
            borderDash: [8, 4],
            borderWidth: 2,
            pointRadius: 2,
            fill: {
                target: 'origin',
                above: 'rgba(244, 67, 54, 0.08)',
            },
            tension: 0.1,
        }]
    };

    if (progChart) {
        progChart.destroy();
    }
    const progCtx = document.getElementById('prognoseChart').getContext('2d');
    progChart = new Chart(progCtx, {
        type: 'line',
        data: prognoseData,
        options: getLineChartOptions('Bestandsprognose bei aktuellem Verbrauch', 'Datum', 'Bestand (Stueck)')
    });
}

/**
 * Fuellt ein kuerzeres Array mit null-Werten auf, um es an die Labels anzupassen.
 * Abgaenge starten 1 Tag spaeter als Lagerstand.
 */
function padArray(arr, targetLength) {
    const diff = targetLength - arr.length;
    if (diff <= 0) return arr;
    return Array(diff).fill(null).concat(arr);
}

$(document).ready(function() {
    const han = document.getElementById('pd-artikel').value;
    if (han) {
        loadProduktdetail();
    }
});
</script>
