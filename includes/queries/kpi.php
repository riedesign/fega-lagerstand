<?php
/**
 * KPI-Dashboard: Datenlogik (nur Eigenprodukte / Polar)
 */

define('EIGEN_FILTER_SQL', "AND t1.artikelname LIKE '%Polar%'");

/**
 * Gesamtuebersicht fuer Eigenprodukte.
 */
function get_kpi_overview($conn, $time_period) {
    $days = get_days_for_period($time_period);

    // 1. Gesamtbestand Eigenprodukte (aktuellster Stand pro Artikel)
    $sql_bestand = "
        SELECT SUM(latest.lagerstand) AS gesamt_bestand, COUNT(*) AS artikel_count
        FROM (
            SELECT t2.lagerstand
            FROM lager_teci t1
            JOIN lager_teci_stand t2 ON t1.id = t2.id
            WHERE t1.artikelname LIKE '%Polar%'
            AND (t2.id, t2.datum) IN (
                SELECT id, MAX(datum) FROM lager_teci_stand GROUP BY id
            )
        ) latest
    ";
    $result = execute_query($conn, $sql_bestand);
    $bestand_row = $result ? $result->fetch_assoc() : null;

    // 2. Abgaenge nur Eigenprodukte
    $abgaenge_data = get_daily_abgaenge($conn, $days, EIGEN_FILTER_SQL);

    $gesamt_abgang_heute = 0;
    $gesamt_abgang_gestern = 0;
    $heute = date('Y-m-d');
    $gestern = date('Y-m-d', strtotime('-1 day'));

    $kritische_artikel = [];
    $topseller = [];
    $ladenhueter = [];

    // Wochen-Trend
    $abgang_aktuelle_woche = 0;
    $abgang_letzte_woche = 0;
    $start_aktuelle_woche = date('Y-m-d', strtotime('monday this week'));
    $start_letzte_woche = date('Y-m-d', strtotime('monday last week'));
    $ende_letzte_woche = date('Y-m-d', strtotime('sunday last week'));

    foreach ($abgaenge_data as $id => $data) {
        $total_abgang = 0;

        foreach ($data['abgaenge'] as $entry) {
            $total_abgang += $entry['abgang'];

            if ($entry['tag'] === $heute) {
                $gesamt_abgang_heute += $entry['abgang'];
            }
            if ($entry['tag'] === $gestern) {
                $gesamt_abgang_gestern += $entry['abgang'];
            }

            if ($entry['tag'] >= $start_aktuelle_woche) {
                $abgang_aktuelle_woche += $entry['abgang'];
            } elseif ($entry['tag'] >= $start_letzte_woche && $entry['tag'] <= $ende_letzte_woche) {
                $abgang_letzte_woche += $entry['abgang'];
            }
        }

        $records = $data['records'];
        $current_stock = !empty($records) ? end($records)['lagerstand_end'] : 0;
        $avg_daily = ($days > 0) ? $total_abgang / $days : 0;
        $reichweite = ($avg_daily > 0) ? floor($current_stock / $avg_daily) : ($current_stock > 0 ? PHP_INT_MAX : 0);
        $lead_time = get_lead_time_days($data['han']);

        // Kritisch?
        if ($reichweite < $lead_time && $reichweite !== PHP_INT_MAX) {
            $kritische_artikel[] = [
                'han'          => $data['han'],
                'artikelname'  => $data['artikelname'],
                'lagerstand'   => $current_stock,
                'reichweite'   => $reichweite,
                'lead_time'    => $lead_time,
            ];
        }

        // Topseller
        $topseller[] = [
            'han'          => $data['han'],
            'artikelname'  => $data['artikelname'],
            'total_abgang' => $total_abgang,
            'avg_daily'    => round($avg_daily, 1),
        ];

        // Ladenhueter
        if ($current_stock > 0 && $total_abgang == 0) {
            $ladenhueter[] = [
                'han'         => $data['han'],
                'artikelname' => $data['artikelname'],
                'lagerstand'  => $current_stock,
            ];
        }
    }

    usort($kritische_artikel, function($a, $b) { return $a['reichweite'] <=> $b['reichweite']; });
    usort($topseller, function($a, $b) { return $b['total_abgang'] <=> $a['total_abgang']; });
    usort($ladenhueter, function($a, $b) { return $b['lagerstand'] <=> $a['lagerstand']; });

    // Wochen-Trend
    $trend_pct = 0;
    $trend_direction = 'stabil';
    if ($abgang_letzte_woche > 0) {
        $trend_pct = round(($abgang_aktuelle_woche - $abgang_letzte_woche) / $abgang_letzte_woche * 100, 1);
        $trend_direction = $trend_pct > 5 ? 'steigend' : ($trend_pct < -5 ? 'fallend' : 'stabil');
    }

    return [
        'gesamt_bestand'        => (int)($bestand_row['gesamt_bestand'] ?? 0),
        'artikel_count'         => (int)($bestand_row['artikel_count'] ?? 0),
        'abgang_heute'          => $gesamt_abgang_heute,
        'abgang_gestern'        => $gesamt_abgang_gestern,
        'kritische_count'       => count($kritische_artikel),
        'kritische_artikel'     => array_slice($kritische_artikel, 0, 10),
        'topseller'             => array_slice($topseller, 0, 5),
        'ladenhueter'           => array_slice($ladenhueter, 0, 5),
        'trend_pct'             => $trend_pct,
        'trend_direction'       => $trend_direction,
        'abgang_aktuelle_woche' => $abgang_aktuelle_woche,
        'abgang_letzte_woche'   => $abgang_letzte_woche,
    ];
}
