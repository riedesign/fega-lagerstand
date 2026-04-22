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

// Farbschema
$FARBEN = [
    'eigen'    => '#2196F3', // Blau
    'fremd'    => '#FF9800', // Orange
    'kritisch' => '#F44336', // Rot
    'warnung'  => '#FFC107', // Gelb
    'ok'       => '#4CAF50', // Gruen
    'neutral'  => '#607D8B', // Grau-Blau
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

// Verfuegbare Zeitraeume
$TIME_PERIODS = [
    '1_week'   => ['label' => 'Letzte Woche',      'days' => 7],
    '2_weeks'  => ['label' => 'Letzte 2 Wochen',    'days' => 14],
    '3_weeks'  => ['label' => 'Letzte 3 Wochen',    'days' => 21],
    '4_weeks'  => ['label' => 'Letzte 4 Wochen',    'days' => 28],
    '2_months' => ['label' => 'Letzte 2 Monate',    'days' => 60],
    '3_months' => ['label' => 'Letzte 3 Monate',    'days' => 90],
    '6_months' => ['label' => 'Letzte 6 Monate',    'days' => 180],
];

// Sicherheitspuffer fuer Warnlimit (30%)
$SAFETY_BUFFER = 1.3;
