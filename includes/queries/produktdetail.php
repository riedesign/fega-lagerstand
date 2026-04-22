<?php
/**
 * Produktdetail: Einzelartikel Drill-down mit Verlauf, KPIs und Prognose
 */

/**
 * Liefert alle Artikelnummern (HAN) fuer das Dropdown.
 */
function get_artikel_liste($conn, $nur_eigen = true) {
    $filter = $nur_eigen ? "AND t1.artikelname LIKE '%Polar%'" : "";
    $sql = "
        SELECT DISTINCT t1.han, t1.artikelname
        FROM lager_teci t1
        WHERE t1.han IS NOT NULL AND t1.han != ''
        {$filter}
        ORDER BY t1.artikelname ASC
    ";
    $result = execute_query($conn, $sql);
    $list = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

/**
 * Holt alle Detaildaten fuer einen einzelnen Artikel.
 */
function get_produkt_detail($conn, $han, $time_period) {
    $days = get_days_for_period($time_period);
    $han_escaped = $conn->real_escape_string($han);
    $filter_sql = "AND t1.han = '{$han_escaped}'";

    $abgaenge_data = get_daily_abgaenge($conn, $days, $filter_sql);

    if (empty($abgaenge_data)) {
        return null;
    }

    // Erstes (einziges) Element nehmen
    $data = reset($abgaenge_data);
    $records = $data['records'];
    $abgaenge = $data['abgaenge'];

    // Aktueller Bestand
    $current_stock = !empty($records) ? end($records)['lagerstand_end'] : 0;

    // Lagerstandsverlauf (Labels + Werte)
    $lager_labels = array_column($records, 'tag');
    $lager_values = array_column($records, 'lagerstand_end');

    // Abgangsverlauf
    $abgang_labels = array_column($abgaenge, 'tag');
    $abgang_values = array_column($abgaenge, 'abgang');

    // Durchschnittlicher Tagesverbrauch
    $total_abgang = array_sum($abgang_values);
    $abgang_tage_count = count(array_filter($abgang_values, function($v) { return $v > 0; }));
    $avg_daily = ($days > 0) ? $total_abgang / $days : 0;
    $avg_on_active_days = ($abgang_tage_count > 0) ? $total_abgang / $abgang_tage_count : 0;

    // Reichweite
    $reichweite = ($avg_daily > 0) ? floor($current_stock / $avg_daily) : ($current_stock > 0 ? PHP_INT_MAX : 0);
    $lead_time = get_lead_time_days($data['han']);

    // Trend
    $trend = calculate_trend($abgang_values);

    // Prognose
    $prognose = calculate_prognose($current_stock, $avg_daily);
    $prognose_labels = array_column($prognose, 'tag');
    $prognose_values = array_column($prognose, 'bestand');

    // Gleitender 7-Tage-Durchschnitt der Abgaenge
    $moving_avg = [];
    for ($i = 0; $i < count($abgang_values); $i++) {
        $window_start = max(0, $i - 6);
        $window = array_slice($abgang_values, $window_start, $i - $window_start + 1);
        $moving_avg[] = round(array_sum($window) / count($window), 1);
    }

    // Letzter Abgang (Datum)
    $last_abgang_date = null;
    for ($i = count($abgaenge) - 1; $i >= 0; $i--) {
        if ($abgaenge[$i]['abgang'] > 0) {
            $last_abgang_date = $abgaenge[$i]['tag'];
            break;
        }
    }

    return [
        'han'              => $data['han'],
        'artikelname'      => $data['artikelname'],
        'is_eigen'         => is_eigenprodukt($data['artikelname']),
        'current_stock'    => $current_stock,
        'lead_time'        => $lead_time,
        'reichweite'       => $reichweite === PHP_INT_MAX ? 'unbegrenzt' : $reichweite,
        'avg_daily'        => round($avg_daily, 1),
        'avg_active_days'  => round($avg_on_active_days, 1),
        'total_abgang'     => $total_abgang,
        'trend'            => $trend,
        'last_abgang'      => $last_abgang_date ?? 'Kein Abgang im Zeitraum',
        'lager_labels'     => $lager_labels,
        'lager_values'     => $lager_values,
        'abgang_labels'    => $abgang_labels,
        'abgang_values'    => $abgang_values,
        'moving_avg'       => $moving_avg,
        'prognose_labels'  => $prognose_labels,
        'prognose_values'  => $prognose_values,
        'status'           => ($reichweite !== PHP_INT_MAX && $reichweite < $lead_time) ? 'critical' :
                              ($total_abgang == 0 && $current_stock > 0 ? 'obsolete' : 'ok'),
    ];
}
