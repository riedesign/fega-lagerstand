<?php
/**
 * Ansicht: Markt-Dashboard (Sales-Sicht beim Kunden Fega)
 *
 * Fuenf Bloecke:
 *  1. KPI-Zeile
 *  2. "Wem gehoert der Umsatz?" — Stacked-Bar
 *  3. Marken-Ranking — DataTable
 *  4. Zeitverlauf — Line-Chart
 *  5. Top-20 Artikel — DataTable
 */
?>

<h2 class="section-title">Marktlage bei Fega &mdash; Sales-Sicht</h2>

<!-- Warnbanner fuer Sonstige-Anteil > 15% -->
<div id="markt-sonstige-warn" class="markt-warn" style="display:none;">
    <strong>Hinweis:</strong> <span id="markt-sonstige-text"></span>
    Die Marken-Zuordnung ist dadurch unvollstaendig &mdash;
    <code>$MARKEN_MAP</code> in <code>config/app_config.php</code> sollte
    ergaenzt werden.
</div>

<!-- Block 1: KPI-Zeile -->
<div class="kpi-grid">
    <div class="kpi-card status-neutral">
        <div class="kpi-label">Abverkauf Fega (Zeitraum)</div>
        <div class="kpi-value" id="markt-kpi-gesamt">&ndash;</div>
        <div class="kpi-sub" id="markt-kpi-gesamt-vor">&ndash;</div>
    </div>

    <div class="kpi-card status-eigen">
        <div class="kpi-label">Davon Polar (Eigen)</div>
        <div class="kpi-value text-eigen" id="markt-kpi-polar">&ndash;</div>
        <div class="kpi-sub" id="markt-kpi-polar-anteil">&ndash;</div>
    </div>

    <div class="kpi-card status-neutral">
        <div class="kpi-label">Trend Polar-Anteil</div>
        <div class="kpi-value" id="markt-kpi-trend">&ndash;</div>
        <div class="kpi-sub" id="markt-kpi-trend-sub">vs. Vorperiode</div>
    </div>

    <div class="kpi-card status-neutral">
        <div class="kpi-label">Aktive Artikel</div>
        <div class="kpi-value" id="markt-kpi-active">&ndash;</div>
        <div class="kpi-sub" id="markt-kpi-active-sub">Polar / Fremd mit Abgang</div>
    </div>
</div>

<!-- Block 2: Stacked-Bar "Wem gehoert der Umsatz?" -->
<div class="section">
    <h3 class="section-title">Wem gehoert der Umsatz?</h3>
    <div class="markt-stackbar-wrap">
        <canvas id="markt-stackbar-chart" height="80"></canvas>
    </div>
    <p class="markt-stackbar-hint">Ein Balken = 100% des Abverkaufs bei Fega im Zeitraum. Groesstes Segment zuerst.</p>
</div>

<!-- Block 3: Marken-Ranking Tabelle -->
<div class="section">
    <h3 class="section-title">Marken-Ranking</h3>
    <p class="markt-filter-hint">
        <span id="markt-filter-active" style="display:none;">
            Filter aktiv: <strong id="markt-filter-label"></strong>
            <a href="#" onclick="clearMarktFilter(); return false;">(Filter aufheben)</a>
        </span>
    </p>
    <table class="data-table" id="markt-marken-table">
        <thead>
            <tr>
                <th>Marke</th>
                <th>Abverkauf</th>
                <th>Anteil</th>
                <th>vs. Vorperiode</th>
                <th>Aktive Artikel</th>
                <th>Top-Artikel</th>
            </tr>
        </thead>
        <tbody id="markt-marken-body"></tbody>
    </table>
</div>

<!-- Block 4: Zeitverlauf -->
<div class="section">
    <h3 class="section-title">Zeitverlauf Abverkauf</h3>
    <div class="chart-container tall" id="markt-line-container">
        <canvas id="markt-line-chart"></canvas>
    </div>
</div>

<!-- Block 5: Top-Artikel -->
<div class="section">
    <h3 class="section-title">Top-Artikel beim Kunden</h3>
    <div class="controls">
        <label>
            <input type="radio" name="markt-artikel-filter" value="all" checked onchange="renderArtikelTable()">
            Alle
        </label>
        <label>
            <input type="radio" name="markt-artikel-filter" value="eigen" onchange="renderArtikelTable()">
            Nur Polar
        </label>
        <label>
            <input type="radio" name="markt-artikel-filter" value="fremd" onchange="renderArtikelTable()">
            Nur Fremd
        </label>
    </div>
    <table class="data-table" id="markt-artikel-table">
        <thead>
            <tr>
                <th>Artikelname</th>
                <th>HAN</th>
                <th>Marke</th>
                <th>Abverkauf</th>
                <th>Anteil</th>
                <th>Trend</th>
                <th>Bestand</th>
            </tr>
        </thead>
        <tbody id="markt-artikel-body"></tbody>
    </table>
</div>

<style>
.markt-warn {
    background: #fff3cd;
    border-left: 4px solid #FFC107;
    color: #725c02;
    padding: 10px 14px;
    border-radius: 4px;
    margin-bottom: 16px;
    font-size: 0.9em;
}
.markt-stackbar-wrap {
    background: #fafafa;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 16px;
}
.markt-stackbar-hint, .markt-filter-hint {
    font-size: 0.82em;
    color: #888;
    margin-top: 6px;
}
.markt-trend-up    { color: #4CAF50; font-weight: 600; }
.markt-trend-down  { color: #F44336; font-weight: 600; }
.markt-trend-flat  { color: #607D8B; }
.markt-marken-row-eigen {
    background: #e3f2fd !important;
    font-weight: 600;
}
.markt-marken-row-eigen td {
    color: #0d47a1;
}
.markt-marken-row-click {
    cursor: pointer;
}
.markt-sparkline-cell {
    width: 80px;
    height: 24px;
}
.markt-sparkline-cell canvas {
    display: block;
}
.markt-han-link {
    color: #2196F3;
    text-decoration: none;
}
.markt-han-link:hover { text-decoration: underline; }
</style>

<script>
var marktData = null;
var marktStackChart = null;
var marktLineChart = null;
var marktFilterMarke = null;  // Wenn gesetzt: nur diese Marke in Block 5 zeigen
var marktArtikelDT = null;

function formatNumberInt(n) {
    if (n === null || n === undefined) return '-';
    return Number(n).toLocaleString('de-DE');
}

function marktTrendArrow(direction, delta) {
    var sign = delta > 0 ? '+' : '';
    if (direction === 'steigend') {
        return '<span class="markt-trend-up">&#9650; ' + sign + delta + '%</span>';
    } else if (direction === 'fallend') {
        return '<span class="markt-trend-down">&#9660; ' + sign + delta + '%</span>';
    }
    return '<span class="markt-trend-flat">&#9654; ' + sign + delta + '%</span>';
}

function loadMarkt() {
    var timePeriod = document.getElementById('global-time-period').value;
    showLoading('markt-line-container');

    fetchApi('markt.php', { time_period: timePeriod }, function(data) {
        hideLoading('markt-line-container');
        marktData = data;
        renderKpi();
        renderStackbar();
        renderMarkenTable();
        renderLineChart();
        renderArtikelTable();
        renderSonstigeWarnung();
    }, function() {
        hideLoading('markt-line-container');
    });
}

function renderSonstigeWarnung() {
    var warn = document.getElementById('markt-sonstige-warn');
    var text = document.getElementById('markt-sonstige-text');
    if (marktData.sonstige_warnung) {
        text.textContent = 'Der Anteil "Sonstige" liegt bei ' + marktData.sonstige_anteil + '%.';
        warn.style.display = 'block';
    } else {
        warn.style.display = 'none';
    }
}

function renderKpi() {
    var k = marktData.kpi;
    document.getElementById('markt-kpi-gesamt').textContent = formatNumberInt(k.gesamt_abgang) + ' Stk.';
    document.getElementById('markt-kpi-gesamt-vor').textContent =
        'Vorperiode: ' + formatNumberInt(k.gesamt_abgang_vor) + ' Stk.';

    document.getElementById('markt-kpi-polar').textContent = formatNumberInt(k.polar_abgang) + ' Stk.';
    document.getElementById('markt-kpi-polar-anteil').textContent =
        'Anteil: ' + k.polar_anteil + '%';

    document.getElementById('markt-kpi-trend').innerHTML = marktTrendArrow(k.anteil_direction, k.anteil_delta);
    document.getElementById('markt-kpi-trend-sub').textContent =
        'Jetzt ' + k.polar_anteil + '% / vorher ' + k.polar_anteil_vor + '%';

    document.getElementById('markt-kpi-active').textContent = (k.polar_active + k.fremd_active);
    document.getElementById('markt-kpi-active-sub').textContent =
        k.polar_active + ' Polar / ' + k.fremd_active + ' Fremd';
}

function markenColor(marke, index) {
    if (marke.indexOf('Polar') !== -1) return COLOR_EIGEN;
    return chartColor(index);
}

function renderStackbar() {
    var marken = marktData.marken;
    // Wir bauen eine einzelne Zeile mit einem Datensatz pro Marke.
    // Labels = [''] damit nur ein horizontaler Balken entsteht.
    var datasets = marken.map(function(m, i) {
        return {
            label: m.marke + ' (' + m.anteil + '%)',
            data: [m.abgang],
            backgroundColor: markenColor(m.marke, i),
            borderWidth: 0,
        };
    });

    if (marktStackChart) marktStackChart.destroy();
    var ctx = document.getElementById('markt-stackbar-chart').getContext('2d');
    marktStackChart = new Chart(ctx, {
        type: 'bar',
        data: { labels: [''], datasets: datasets },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var m = marken[ctx.datasetIndex];
                            return m.marke + ': ' + formatNumberInt(m.abgang) + ' Stk. (' + m.anteil + '%)';
                        }
                    }
                }
            },
            scales: {
                x: { stacked: true, display: false },
                y: { stacked: true, display: false }
            }
        }
    });
}

function renderMarkenTable() {
    var tbody = document.getElementById('markt-marken-body');
    tbody.innerHTML = '';
    marktData.marken.forEach(function(m) {
        var cls = m.is_eigen ? 'markt-marken-row-eigen markt-marken-row-click' : 'markt-marken-row-click';
        var topHan = m.top_artikel
            ? '<a href="index.php?page=produktdetail&han=' + encodeURIComponent(m.top_artikel.han)
                + '&time_period=' + marktData.time_period + '" class="markt-han-link" onclick="event.stopPropagation();">'
                + m.top_artikel.han + '</a>'
            : '-';
        var row = '<tr class="' + cls + '" onclick="setMarktFilter(\'' + m.marke.replace(/'/g, "\\'") + '\')">'
            + '<td><strong>' + m.marke + '</strong></td>'
            + '<td>' + formatNumberInt(m.abgang) + '</td>'
            + '<td>' + m.anteil + '%</td>'
            + '<td>' + marktTrendArrow(m.direction, m.delta_pct) + '</td>'
            + '<td>' + m.artikel_count + '</td>'
            + '<td>' + topHan + '</td>'
            + '</tr>';
        tbody.innerHTML += row;
    });
}

function renderLineChart() {
    var z = marktData.zeitverlauf;

    var datasets = [
        {
            label: 'Polar (Eigen)',
            data: z.eigen,
            borderColor: COLOR_EIGEN,
            backgroundColor: COLOR_EIGEN.replace(')', ', 0.1)').replace('rgb(', 'rgba('),
            borderWidth: 3,
            tension: 0.25,
            fill: false,
        },
        {
            label: 'Fremd gesamt',
            data: z.fremd,
            borderColor: COLOR_FREMD,
            backgroundColor: COLOR_FREMD.replace(')', ', 0.1)').replace('rgb(', 'rgba('),
            borderWidth: 3,
            borderDash: [6, 4],
            tension: 0.25,
            fill: false,
        }
    ];

    // Top-3 Fremdmarken als duenne Hintergrundlinien
    z.fremd_top3.forEach(function(item, i) {
        datasets.push({
            label: item.marke,
            data: item.values,
            borderColor: chartColor(i + 2, 0.7),
            borderWidth: 1,
            borderDash: [2, 2],
            pointRadius: 0,
            tension: 0.25,
            fill: false,
        });
    });

    if (marktLineChart) marktLineChart.destroy();
    var ctx = document.getElementById('markt-line-chart').getContext('2d');
    marktLineChart = new Chart(ctx, {
        type: 'line',
        data: { labels: z.labels, datasets: datasets },
        options: getLineChartOptions('', 'Zeitraum (' + marktData.aggregation + ')', 'Abverkauf (Stk.)')
    });
}

function setMarktFilter(marke) {
    marktFilterMarke = marke;
    document.getElementById('markt-filter-active').style.display = 'inline';
    document.getElementById('markt-filter-label').textContent = marke;
    renderArtikelTable();
    // Sanft zur Tabelle scrollen
    document.getElementById('markt-artikel-table').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function clearMarktFilter() {
    marktFilterMarke = null;
    document.getElementById('markt-filter-active').style.display = 'none';
    renderArtikelTable();
}

function currentArtikelFilter() {
    var radios = document.getElementsByName('markt-artikel-filter');
    for (var i = 0; i < radios.length; i++) {
        if (radios[i].checked) return radios[i].value;
    }
    return 'all';
}

function renderArtikelTable() {
    if (!marktData) return;
    var filter = currentArtikelFilter();
    var rows = marktData.top_artikel.filter(function(a) {
        if (filter === 'eigen' && !a.is_eigen) return false;
        if (filter === 'fremd' && a.is_eigen) return false;
        if (marktFilterMarke && a.marke !== marktFilterMarke) return false;
        return true;
    });

    // DataTable zerstoeren bevor tbody ersetzt wird
    if (marktArtikelDT) {
        marktArtikelDT.destroy();
        marktArtikelDT = null;
    }

    var tbody = document.getElementById('markt-artikel-body');
    tbody.innerHTML = '';
    rows.forEach(function(a, i) {
        var canvasId = 'spark-' + i + '-' + a.id;
        var detailLink = 'index.php?page=produktdetail&han=' + encodeURIComponent(a.han)
            + '&time_period=' + marktData.time_period;
        var row = '<tr>'
            + '<td><a href="' + detailLink + '" class="markt-han-link">' + a.artikelname + '</a></td>'
            + '<td>' + a.han + '</td>'
            + '<td>' + a.marke + '</td>'
            + '<td>' + formatNumberInt(a.abgang) + '</td>'
            + '<td>' + a.anteil + '%</td>'
            + '<td class="markt-sparkline-cell"><canvas id="' + canvasId + '" width="80" height="24"></canvas></td>'
            + '<td>' + formatNumberInt(a.bestand) + '</td>'
            + '</tr>';
        tbody.innerHTML += row;
    });

    // Sparklines rendern (muss nach DOM-Insert passieren)
    rows.forEach(function(a, i) {
        var canvasId = 'spark-' + i + '-' + a.id;
        var color = a.is_eigen ? COLOR_EIGEN : COLOR_FREMD;
        createSparkline(canvasId, a.sparkline, color);
    });

    marktArtikelDT = initDataTable('#markt-artikel-table', {
        order: [[3, 'desc']],
        pageLength: 20,
        columnDefs: [
            { targets: [5], orderable: false, searchable: false }
        ]
    });
}

$(document).ready(function() { loadMarkt(); });
</script>
