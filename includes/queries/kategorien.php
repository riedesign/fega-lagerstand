<?php
/**
 * Kategorie-/Marken-Vergleich: Marktanteile und Abgangs-Vergleich
 */

/**
 * Berechnet Marktanteile und Abgangs-Vergleich pro Marke.
 */
function get_marken_vergleich($conn, $time_period, $aggregation = 'day') {
    $days = get_days_for_period($time_period);
    $abgaenge_data = get_daily_abgaenge($conn, $days);

    // Abgaenge nach Marke und Periode sammeln
    $marken_daily = [];   // [marke][tag] => abgang
    $marken_totals = [];  // [marke] => total_abgang
    $marken_artikel = []; // [marke] => count unique articles

    foreach ($abgaenge_data as $id => $data) {
        $marke = extract_marke($data['artikelname']);

        if (!isset($marken_totals[$marke])) {
            $marken_totals[$marke] = 0;
            $marken_artikel[$marke] = 0;
        }
        $marken_artikel[$marke]++;

        foreach ($data['abgaenge'] as $entry) {
            $period_key = get_period_key($entry['tag'], $aggregation);
            if (!isset($marken_daily[$marke][$period_key])) {
                $marken_daily[$marke][$period_key] = 0;
            }
            $marken_daily[$marke][$period_key] += $entry['abgang'];
            $marken_totals[$marke] += $entry['abgang'];
        }
    }

    // Gesamtabgang
    $gesamt_total = array_sum($marken_totals);

    // Marktanteile berechnen
    $marktanteile = [];
    foreach ($marken_totals as $marke => $total) {
        $marktanteile[] = [
            'marke'      => $marke,
            'abgang'     => $total,
            'anteil'     => $gesamt_total > 0 ? round($total / $gesamt_total * 100, 1) : 0,
            'artikel'    => $marken_artikel[$marke],
        ];
    }
    usort($marktanteile, function($a, $b) { return $b['abgang'] <=> $a['abgang']; });

    // Zeitverlauf pro Marke
    $all_periods = [];
    foreach ($marken_daily as $marke => $periods) {
        foreach (array_keys($periods) as $p) {
            $all_periods[$p] = true;
        }
    }
    $period_labels = array_keys($all_periods);
    sort($period_labels);

    $zeitverlauf = [];
    foreach ($marken_daily as $marke => $periods) {
        $values = [];
        foreach ($period_labels as $label) {
            $values[] = $periods[$label] ?? 0;
        }
        $zeitverlauf[] = [
            'marke'  => $marke,
            'values' => $values,
        ];
    }

    // Sortiere Zeitverlauf nach Gesamtabgang (groesste Marke zuerst)
    usort($zeitverlauf, function($a, $b) use ($marken_totals) {
        return ($marken_totals[$b['marke']] ?? 0) <=> ($marken_totals[$a['marke']] ?? 0);
    });

    return [
        'marktanteile'   => $marktanteile,
        'period_labels'  => $period_labels,
        'zeitverlauf'    => $zeitverlauf,
        'gesamt_total'   => $gesamt_total,
    ];
}

