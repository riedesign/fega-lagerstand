<?php
/**
 * Anwendungskonfiguration: Konstanten, Marken-Mapping, Schwellwerte
 */

// Lead Times in Tagen (statisch, spaeter aus DB lesbar)
$LEAD_TIMES = [
    'POLAR' => 7,   // Eigenprodukte
    'DEFAULT' => 14  // Fremdprodukte
];

// Marken-Mapping: Keyword im Artikelnamen => Markenname
$MARKEN_MAP = [
    'Polar' => 'Polar (Eigen)',
    // Weitere Marken hier ergaenzen, z.B.:
    // 'Bosch' => 'Bosch',
    // 'Siemens' => 'Siemens',
];

// Standardmarke fuer nicht zugeordnete Artikel
$DEFAULT_MARKE = 'Sonstige (Fremd)';

// Farbschema — CSS-Vars mit Hex-Fallback.
// Chart.js liest die Werte zur Laufzeit via
// getComputedStyle(document.documentElement).getPropertyValue(varName).trim()
// aus, wenn der JS-Code einen var() String erkennt.
$FARBEN = [
    'eigen'    => 'var(--color-primary, #2196F3)',          // Eigen-Brand
    'fremd'    => 'var(--color-brand-accent-red, #FF9800)', // Fremd-Akzent
    'kritisch' => 'var(--color-error-text, #F44336)',
    'warnung'  => 'var(--color-warning-text, #FFC107)',
    'ok'       => 'var(--color-success-text, #4CAF50)',
    'neutral'  => 'var(--color-text-muted, #607D8B)',
];

// Chart-Farben Palette
$CHART_COLORS = [
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

// Verfuegbare Zeitraeume — die sechs die im Markt-Dashboard angeboten werden.
// Alte Keys (1_week, 3_weeks) bleiben als Alias erhalten damit Bookmarks
// auf produktdetail.php nicht crashen, sie tauchen aber nicht im Dropdown auf.
$TIME_PERIODS = [
    '2_weeks'   => ['label' => 'Letzte 2 Wochen',  'days' => 14],
    '4_weeks'   => ['label' => 'Letzte 4 Wochen',  'days' => 28],
    '2_months'  => ['label' => 'Letzte 2 Monate',  'days' => 60],
    '3_months'  => ['label' => 'Letzte 3 Monate',  'days' => 90],
    '6_months'  => ['label' => 'Letzte 6 Monate',  'days' => 180],
    '12_months' => ['label' => 'Letzte 12 Monate', 'days' => 365],
];

// Alias-Map fuer alte Zeitraum-Keys. `render_time_period_options()` zeigt
// nur $TIME_PERIODS an, aber URLs mit ?time_period=1_week werden nicht
// hart abgelehnt, sondern in den naechsten sinnvollen Key umgemappt.
$TIME_PERIOD_ALIAS = [
    '1_week'  => '2_weeks',
    '3_weeks' => '4_weeks',
];

// Sicherheitspuffer fuer Warnlimit (30%)
$SAFETY_BUFFER = 1.3;
