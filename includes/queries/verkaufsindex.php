<?php
/**
 * Verkaufsindex: Eigen vs. Fremd Vergleich
 */

/**
 * Berechnet den Verkaufsindex fuer Eigen- und Fremdprodukte.
 * Gibt normalisierte Indexwerte (Basis=100) und absolute Zahlen zurueck.
 */
function get_verkaufsindex_data($conn, $time_period, $aggregation = 'day') {
    $days = get_days_for_period($time_period);
    $abgaenge_data = get_daily_abgaenge($conn, $days);

    // Abgaenge nach Eigen/Fremd und Tag sammeln
    $eigen_daily = [];
    $fremd_daily = [];

    foreach ($abgaenge_data as $id => $data) {
        $is_eigen = is_eigenprodukt($data['artikelname']);

        foreach ($data['abgaenge'] as $entry) {
            $tag = $entry['tag'];
            if ($is_eigen) {
                $eigen_daily[$tag] = ($eigen_daily[$tag] ?? 0) + $entry['abgang'];
            } else {
                $fremd_daily[$tag] = ($fremd_daily[$tag] ?? 0) + $entry['abgang'];
            }
        }
    }

    // Alle Tage sammeln und sortieren
    $all_days = array_unique(array_merge(array_keys($eigen_daily), array_keys($fremd_daily)));
    sort($all_days);

    // Nach Aggregation gruppieren
    $eigen_grouped = [];
    $fremd_grouped = [];

    foreach ($all_days as $tag) {
        $period_key = get_period_key($tag, $aggregation);
        $eigen_grouped[$period_key] = ($eigen_grouped[$period_key] ?? 0) + ($eigen_daily[$tag] ?? 0);
        $fremd_grouped[$period_key] = ($fremd_grouped[$period_key] ?? 0) + ($fremd_daily[$tag] ?? 0);
    }

    // Labels (sortiert)
    $labels = array_keys($eigen_grouped + $fremd_grouped);
    sort($labels);

    // Absolute Werte
    $eigen_absolut = [];
    $fremd_absolut = [];
    foreach ($labels as $label) {
        $eigen_absolut[] = $eigen_grouped[$label] ?? 0;
        $fremd_absolut[] = $fremd_grouped[$label] ?? 0;
    }

    // Index normalisieren (Basis = 100)
    $eigen_index = normalize_to_index($eigen_absolut);
    $fremd_index = normalize_to_index($fremd_absolut);

    // Gesamtsummen
    $eigen_total = array_sum($eigen_absolut);
    $fremd_total = array_sum($fremd_absolut);
    $gesamt_total = $eigen_total + $fremd_total;

    return [
        'labels'        => $labels,
        'eigen_index'   => $eigen_index,
        'fremd_index'   => $fremd_index,
        'eigen_absolut' => $eigen_absolut,
        'fremd_absolut' => $fremd_absolut,
        'eigen_total'   => $eigen_total,
        'fremd_total'   => $fremd_total,
        'eigen_anteil'  => $gesamt_total > 0 ? round($eigen_total / $gesamt_total * 100, 1) : 0,
        'fremd_anteil'  => $gesamt_total > 0 ? round($fremd_total / $gesamt_total * 100, 1) : 0,
    ];
}

