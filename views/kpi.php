<?php
/**
 * Ansicht: KPI-Dashboard (nur Eigenprodukte)
 */
require_once __DIR__ . '/../includes/queries/kpi.php';

$kpi = get_kpi_overview($conn, $time_period);

// Trend
$trend_arrow = '&#9654;';
$trend_class = 'text-neutral';
if ($kpi['trend_direction'] === 'steigend') {
    $trend_arrow = '&#9650;'; $trend_class = 'text-ok';
} elseif ($kpi['trend_direction'] === 'fallend') {
    $trend_arrow = '&#9660;'; $trend_class = 'text-critical';
}

// Abgang heute vs. gestern
$abgang_diff = $kpi['abgang_heute'] - $kpi['abgang_gestern'];
$abgang_diff_text = ($abgang_diff >= 0 ? '+' : '') . $abgang_diff . ' vs. gestern';
$abgang_class = $abgang_diff >= 0 ? 'text-ok' : 'text-critical';
?>

<h2 class="section-title">Eigenprodukte &mdash; KPI-Dashboard</h2>

<!-- KPI Kacheln -->
<div class="kpi-grid">
    <div class="kpi-card status-neutral">
        <div class="kpi-label">Gesamtbestand</div>
        <div class="kpi-value"><?php echo number_format($kpi['gesamt_bestand'], 0, ',', '.'); ?></div>
        <div class="kpi-sub"><?php echo $kpi['artikel_count']; ?> Eigenprodukte</div>
    </div>

    <div class="kpi-card status-eigen kpi-clickable" onclick="toggleDetail('abgang-detail')">
        <div class="kpi-label">Abgang heute &#9662;</div>
        <div class="kpi-value"><?php echo number_format($kpi['abgang_heute'], 0, ',', '.'); ?></div>
        <div class="kpi-sub <?php echo $abgang_class; ?>"><?php echo $abgang_diff_text; ?></div>
    </div>

    <div class="kpi-card <?php echo $kpi['kritische_count'] > 0 ? 'status-critical' : 'status-ok'; ?> kpi-clickable" onclick="toggleDetail('kritisch-detail')">
        <div class="kpi-label">Kritische Produkte &#9662;</div>
        <div class="kpi-value <?php echo $kpi['kritische_count'] > 0 ? 'text-critical' : 'text-ok'; ?>">
            <?php echo $kpi['kritische_count']; ?>
        </div>
        <div class="kpi-sub">Reichweite &lt; Lead Time</div>
    </div>

    <div class="kpi-card status-neutral">
        <div class="kpi-label">Verkaufstrend (Woche)</div>
        <div class="kpi-value <?php echo $trend_class; ?>">
            <?php echo $trend_arrow; ?> <?php echo $kpi['trend_pct']; ?>%
        </div>
        <div class="kpi-sub">
            KW aktuell: <?php echo $kpi['abgang_aktuelle_woche']; ?> |
            KW letzte: <?php echo $kpi['abgang_letzte_woche']; ?>
        </div>
    </div>
</div>

<!-- Abgang-Detail (eingeklappt, oeffnet per Klick auf Kachel) -->
<div class="section detail-panel" id="abgang-detail" style="display:none;">
    <h3 class="section-title">Heutige Abgaenge im Detail</h3>
    <table class="data-table" id="abgang-table">
        <thead>
            <tr><th>Artikelname</th><th>HAN</th><th>Abgang heute</th><th>Avg. / Tag</th></tr>
        </thead>
        <tbody>
        <?php
        // Alle Topseller mit Abgang > 0 zeigen (sortiert nach Abgang)
        $alle_mit_abgang = $kpi['topseller'];
        usort($alle_mit_abgang, function($a, $b) { return $b['total_abgang'] <=> $a['total_abgang']; });
        foreach ($alle_mit_abgang as $item):
            if ($item['total_abgang'] <= 0) continue;
        ?>
            <tr>
                <td><?php echo htmlspecialchars($item['artikelname']); ?></td>
                <td><?php echo htmlspecialchars($item['han']); ?></td>
                <td><?php echo $item['total_abgang']; ?></td>
                <td><?php echo $item['avg_daily']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Kritische-Detail (eingeklappt, oeffnet per Klick auf Kachel) -->
<div class="section detail-panel" id="kritisch-detail" style="display:none;">
    <h3 class="section-title">Kritische Produkte (Reichweite &lt; Lead Time)</h3>
    <?php if (!empty($kpi['kritische_artikel'])): ?>
    <table class="data-table">
        <thead>
            <tr><th>Artikelname</th><th>HAN</th><th>Bestand</th><th>Reichweite (Tage)</th><th>Lead Time (Tage)</th></tr>
        </thead>
        <tbody>
        <?php foreach ($kpi['kritische_artikel'] as $item): ?>
            <tr class="row-critical">
                <td><?php echo htmlspecialchars($item['artikelname']); ?></td>
                <td><?php echo htmlspecialchars($item['han']); ?></td>
                <td><?php echo $item['lagerstand']; ?></td>
                <td><strong><?php echo $item['reichweite']; ?></strong></td>
                <td><?php echo $item['lead_time']; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p style="color: #4CAF50; font-weight: 600;">Keine kritischen Produkte &mdash; alles im gruenen Bereich.</p>
    <?php endif; ?>
</div>

<!-- Top 5 Seller + Ladenhueter -->
<div class="two-columns">
    <div class="section">
        <h3 class="section-title">Top 5 Seller</h3>
        <?php if (!empty($kpi['topseller'])): ?>
        <ul class="ranking-list">
            <?php foreach ($kpi['topseller'] as $i => $item): ?>
            <li>
                <span class="rank-name">
                    <?php echo ($i + 1) . '. ' . htmlspecialchars($item['artikelname']); ?>
                    <small>(<?php echo htmlspecialchars($item['han']); ?>)</small>
                </span>
                <span class="rank-value"><?php echo $item['total_abgang']; ?> Stk.</span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p>Keine Abgaenge im Zeitraum.</p>
        <?php endif; ?>
    </div>

    <div class="section">
        <h3 class="section-title">Ladenhueter</h3>
        <?php if (!empty($kpi['ladenhueter'])): ?>
        <ul class="ranking-list">
            <?php foreach ($kpi['ladenhueter'] as $i => $item): ?>
            <li>
                <span class="rank-name">
                    <?php echo ($i + 1) . '. ' . htmlspecialchars($item['artikelname']); ?>
                    <small>(<?php echo htmlspecialchars($item['han']); ?>)</small>
                </span>
                <span class="rank-value" style="color: #999;"><?php echo $item['lagerstand']; ?> Stk.</span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <p>Keine Ladenhueter.</p>
        <?php endif; ?>
    </div>
</div>

<script>
function toggleDetail(id) {
    var el = document.getElementById(id);
    if (el.style.display === 'none') {
        // Alle anderen Detail-Panels schliessen
        var panels = document.querySelectorAll('.detail-panel');
        for (var i = 0; i < panels.length; i++) {
            panels[i].style.display = 'none';
        }
        el.style.display = 'block';
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    } else {
        el.style.display = 'none';
    }
}
</script>
