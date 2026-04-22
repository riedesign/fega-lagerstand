<?php
/**
 * Ansicht: Dispo-Dashboard (nur Eigenprodukte: kritische, Ladenhueter, Topseller)
 */
require_once __DIR__ . '/../includes/queries/dispo.php';

$kpi      = get_dispo_overview($conn, $time_period);
$jtl_conn = get_jtl_mssql_conn();
$abgleich = get_bestand_abgleich($conn, $jtl_conn, $time_period);

// Label fuer Email-Text (aus $TIME_PERIODS)
$zeitraum_label = $TIME_PERIODS[$time_period]['label'] ?? $time_period;
$nutzer_name = $user['display_name'] ?? 'Team Rieste';

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

<h2 class="section-title">Dispo &mdash; Eigenprodukte (Polar)</h2>

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

<!-- Bestands-Abgleich Fega vs. Rieste -->
<h3 class="section-title" style="margin-top: 12px;">Bestands-Abgleich: Fega vs. Rieste</h3>
<div class="kpi-grid">
    <div class="kpi-card status-eigen">
        <div class="kpi-label">Bestand bei Fega</div>
        <div class="kpi-value text-eigen">
            <?php echo number_format($abgleich['sum_fega'], 0, ',', '.'); ?>
        </div>
        <div class="kpi-sub"><?php echo $abgleich['artikel_count']; ?> Polar-Artikel</div>
    </div>

    <div class="kpi-card status-neutral">
        <div class="kpi-label">Bestand bei Rieste</div>
        <?php if ($abgleich['jtl_available']): ?>
        <div class="kpi-value">
            <?php echo number_format($abgleich['sum_rieste'], 0, ',', '.'); ?>
        </div>
        <div class="kpi-sub">
            <?php echo $abgleich['rieste_match_count']; ?> von <?php echo $abgleich['artikel_count']; ?> HAN gematcht
        </div>
        <?php else: ?>
        <div class="kpi-value" style="font-size: 1.1em; color: #888;">nicht verfuegbar</div>
        <div class="kpi-sub">JTL MSSQL nicht konfiguriert</div>
        <?php endif; ?>
    </div>

    <?php if ($abgleich['jtl_available']): ?>
    <div class="kpi-card status-ok">
        <div class="kpi-label">Gesamt (Fega + Rieste)</div>
        <div class="kpi-value text-ok">
            <?php echo number_format($abgleich['sum_gesamt'], 0, ',', '.'); ?>
        </div>
        <div class="kpi-sub">im Markt verfuegbar</div>
    </div>
    <?php endif; ?>
</div>

<div class="section">
    <table class="data-table" id="abgleich-table">
        <thead>
            <tr>
                <th>Artikelname</th>
                <th>HAN</th>
                <th>Bei Fega</th>
                <?php if ($abgleich['jtl_available']): ?>
                <th>Bei Rieste</th>
                <th>Summe</th>
                <?php endif; ?>
                <th>Verkauf Zeitraum</th>
                <th>Vorschlag</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($abgleich['artikel'] as $a): ?>
            <?php
                $rieste_cell = '&ndash;';
                if ($a['rieste_bestand'] !== null) {
                    $rieste_cell = number_format($a['rieste_bestand'], 0, ',', '.');
                }
                $summe_cell = '&ndash;';
                if ($a['summe'] !== null) {
                    $summe_cell = '<strong>' . number_format($a['summe'], 0, ',', '.') . '</strong>';
                }
                $han_link = 'index.php?page=produktdetail&han=' . urlencode($a['han']) . '&time_period=' . urlencode($time_period);

                $v = $a['vorschlag'];
                $row_class = '';
                if ($v['empfehlen']) {
                    $row_class = 'row-warn';
                    if ($v['reichweite_tage'] !== null && $v['reichweite_tage'] < $a['lead_time']) {
                        $row_class = 'row-critical';
                    }
                }
                $check_attr     = $v['empfehlen'] ? 'checked' : '';
                $default_qty    = $v['empfehlen'] ? (int)$v['stk'] : '';
                $grund_color    = $v['empfehlen'] ? '#777' : '#999';
                $han_js         = htmlspecialchars($a['han'], ENT_QUOTES);
                $name_js        = htmlspecialchars($a['artikelname'], ENT_QUOTES);
            ?>
            <tr class="<?php echo $row_class; ?>">
                <td><a href="<?php echo htmlspecialchars($han_link); ?>" class="markt-han-link" style="color:#2196F3;text-decoration:none;">
                    <?php echo htmlspecialchars($a['artikelname']); ?>
                </a></td>
                <td><?php echo htmlspecialchars($a['han']); ?></td>
                <td><?php echo number_format($a['fega_bestand'], 0, ',', '.'); ?></td>
                <?php if ($abgleich['jtl_available']): ?>
                <td><?php echo $rieste_cell; ?></td>
                <td><?php echo $summe_cell; ?></td>
                <?php endif; ?>
                <td><?php echo number_format($a['total_abgang'], 0, ',', '.'); ?></td>
                <td class="vorschlag-cell">
                    <label class="v-label" style="display:flex;align-items:center;gap:6px;">
                        <input type="checkbox" class="v-select"
                               data-han="<?php echo $han_js; ?>"
                               data-name="<?php echo $name_js; ?>"
                               <?php echo $check_attr; ?>>
                        <input type="number" class="v-qty"
                               value="<?php echo $default_qty; ?>"
                               min="1" step="1"
                               style="width:80px;padding:3px 6px;"
                               placeholder="Menge">
                        <span style="font-size:0.85em;color:#555;">Stk.</span>
                    </label>
                    <small style="color:<?php echo $grund_color; ?>;display:block;margin-top:4px;">
                        <?php echo htmlspecialchars($v['grund']); ?>
                    </small>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="section" id="email-vorschlag-section">
    <h3 class="section-title">E-Mail-Vorschlag an Fega</h3>
    <p style="margin-bottom:10px;color:#555;font-size:0.9em;">
        Haken setzen + Menge anpassen in der Tabelle oben. Der Text unten
        wird automatisch aktualisiert und ist vor dem Versenden editierbar.
    </p>
    <p id="email-empty-hint" style="color:#888;font-style:italic;display:none;">
        Aktuell sind keine Artikel fuer die Nachbestellung ausgewaehlt.
    </p>
    <div id="email-content">
        <div class="email-toolbar">
            <button type="button" class="btn-primary" onclick="copyEmailText()">In Zwischenablage kopieren</button>
            <a class="btn-secondary" href="#" id="email-mailto-link" onclick="openMailto(event)">
                In E-Mail-Programm oeffnen
            </a>
            <span id="email-copy-hint" style="margin-left:12px;color:#4CAF50;display:none;">Kopiert!</span>
            <span id="email-item-count" style="margin-left:12px;color:#555;font-size:0.85em;"></span>
        </div>
        <textarea id="email-vorschlag-text" rows="18"
            style="width:100%;font-family:ui-monospace,Menlo,monospace;font-size:0.85em;padding:12px;border:1px solid #ddd;border-radius:6px;"></textarea>
    </div>
</div>

<script>
// Wert aus PHP: Nutzername fuer die E-Mail-Signatur
var EMAIL_SIGNATUR = <?php echo json_encode($nutzer_name); ?>;
</script>

<style>
.email-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}
.email-toolbar .btn-primary,
.email-toolbar .btn-secondary {
    padding: 6px 14px;
    border-radius: 6px;
    border: 1px solid transparent;
    cursor: pointer;
    font-size: 0.9em;
    text-decoration: none;
    display: inline-block;
}
.email-toolbar .btn-primary {
    background: #2196F3;
    color: #fff;
    border-color: #1976D2;
}
.email-toolbar .btn-primary:hover { background: #1976D2; }
.email-toolbar .btn-secondary {
    background: #fff;
    color: #333;
    border-color: #ccc;
}
.email-toolbar .btn-secondary:hover { background: #f5f5f5; }
#abgleich-table td small { line-height: 1.2; }
</style>

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

$(document).ready(function() {
    // Bestands-Abgleich-Tabelle sortierbar + suchbar machen.
    // Letzte Spalte (Vorschlag) enthaelt HTML + Inputs, nicht sortierbar.
    var colCount = document.querySelectorAll('#abgleich-table thead th').length;
    initDataTable('#abgleich-table', {
        pageLength: 25,
        order: [[2, 'desc']],  // sortiert nach Fega-Bestand absteigend
        columnDefs: [
            { targets: colCount - 1, orderable: false }
        ]
    });

    // Initial Email-Text aus den vorausgewaehlten Zeilen bauen
    updateEmailText();

    // Live-Updates bei Aenderungen an Checkbox oder Mengen-Input.
    // Delegation, damit DataTables-Paging nicht die Listener verliert.
    $(document).on('change', '#abgleich-table .v-select', function() {
        var qty = $(this).closest('label').find('.v-qty');
        if (this.checked && (!qty.val() || parseInt(qty.val(), 10) <= 0)) {
            qty.val(10).focus();
        }
        updateEmailText();
    });
    $(document).on('input change', '#abgleich-table .v-qty', function() {
        updateEmailText();
    });
});

function collectSelectedItems() {
    var items = [];
    $('#abgleich-table .v-select:checked').each(function() {
        var qty = parseInt($(this).closest('label').find('.v-qty').val(), 10);
        if (!qty || qty <= 0) return;
        items.push({
            han:  this.dataset.han,
            name: this.dataset.name,
            qty:  qty
        });
    });
    return items;
}

function updateEmailText() {
    var items = collectSelectedItems();
    var countEl = document.getElementById('email-item-count');
    var hintEl  = document.getElementById('email-empty-hint');
    var contentEl = document.getElementById('email-content');

    if (items.length === 0) {
        if (hintEl) hintEl.style.display = 'block';
        if (contentEl) contentEl.style.display = 'none';
        return;
    }
    if (hintEl) hintEl.style.display = 'none';
    if (contentEl) contentEl.style.display = 'block';
    if (countEl) countEl.textContent = items.length + ' Artikel ausgewaehlt';

    var lines = [];
    lines.push('Betreff: Vorschlag Nachbestellung Polar-Produkte');
    lines.push('');
    lines.push('Hallo Team Fega,');
    lines.push('');
    lines.push('da die Lagermengen bei einigen Polar-Produkten bei Ihnen zur Neige gehen,');
    lines.push('moechten wir Ihnen folgende Nachbestellung vorschlagen:');
    lines.push('');
    items.forEach(function(it) {
        lines.push('- ' + it.name + ' (HAN: ' + it.han + '): ' + it.qty + ' Stk.');
    });
    lines.push('');
    lines.push('Passen Sie die Mengen gerne an, wenn etwas nicht passt.');
    lines.push('');
    lines.push('Viele Gruesse,');
    lines.push(EMAIL_SIGNATUR);

    document.getElementById('email-vorschlag-text').value = lines.join('\n');
}

function copyEmailText() {
    var ta = document.getElementById('email-vorschlag-text');
    if (!ta) return;
    ta.select();
    ta.setSelectionRange(0, ta.value.length);
    var done = false;
    try {
        done = document.execCommand('copy');
    } catch (e) { done = false; }
    if (!done && navigator.clipboard) {
        navigator.clipboard.writeText(ta.value);
        done = true;
    }
    if (done) {
        var hint = document.getElementById('email-copy-hint');
        if (hint) {
            hint.style.display = 'inline';
            setTimeout(function() { hint.style.display = 'none'; }, 1500);
        }
    }
}

function openMailto(e) {
    e.preventDefault();
    var ta = document.getElementById('email-vorschlag-text');
    if (!ta) return;
    var txt = ta.value;
    // Betreff aus der ersten Zeile ziehen wenn sie mit "Betreff:" beginnt
    var lines = txt.split('\n');
    var subject = 'Vorschlag Nachbestellung Polar-Produkte';
    var body = txt;
    if (lines.length > 0 && lines[0].toLowerCase().indexOf('betreff:') === 0) {
        subject = lines[0].substring(lines[0].indexOf(':') + 1).trim();
        body = lines.slice(1).join('\n').replace(/^\n+/, '');
    }
    var href = 'mailto:?subject=' + encodeURIComponent(subject)
             + '&body=' + encodeURIComponent(body);
    window.location.href = href;
}
</script>
