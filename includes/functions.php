<?php
/**
 * Shared-Funktionen: Zeitraum, Abgangsberechnung, Trend, Prognose
 */

/**
 * Berechnet die Anzahl Tage aus einem Zeitraum-String.
 */
function get_days_for_period($time_period) {
    global $TIME_PERIODS;
    return $TIME_PERIODS[$time_period]['days'] ?? 28;
}

/**
 * Normalisiert einen Zeitraum-Key: wendet Alias-Mapping an und faellt auf
 * den Standard-Key zurueck wenn unbekannt. Rueckgabewert ist immer ein
 * Key der in $TIME_PERIODS existiert.
 */
function resolve_time_period($time_period, $default = '4_weeks') {
    global $TIME_PERIODS, $TIME_PERIOD_ALIAS;
    if (isset($TIME_PERIOD_ALIAS[$time_period])) {
        $time_period = $TIME_PERIOD_ALIAS[$time_period];
    }
    if (!isset($TIME_PERIODS[$time_period])) {
        return $default;
    }
    return $time_period;
}

/**
 * Lead Time fuer einen Artikel ermitteln.
 */
function get_lead_time_days($han) {
    global $LEAD_TIMES;
    if (stripos($han, 'POLAR') !== false) {
        return $LEAD_TIMES['POLAR'];
    }
    return $LEAD_TIMES['DEFAULT'];
}

/**
 * Marke aus Artikelname extrahieren.
 */
function extract_marke($artikelname) {
    global $MARKEN_MAP, $DEFAULT_MARKE;
    foreach ($MARKEN_MAP as $keyword => $marke) {
        if (stripos($artikelname, $keyword) !== false) {
            return $marke;
        }
    }
    return $DEFAULT_MARKE;
}

/**
 * Prueft ob ein Artikel ein Eigenprodukt (Polar) ist.
 */
function is_eigenprodukt($artikelname) {
    return stripos($artikelname, 'Polar') !== false;
}

/**
 * Berechnet taegliche Abgaenge aus Lagerstandsdaten.
 * Erwartet ein Array von Datensaetzen sortiert nach tag ASC.
 * Gibt ein Array mit tag => abgang zurueck.
 */
function calculate_abgaenge_from_records($records) {
    $abgaenge = [];
    for ($i = 1; $i < count($records); $i++) {
        $prev_stock = (int)$records[$i - 1]['lagerstand_end'];
        $curr_stock = (int)$records[$i]['lagerstand_end'];
        $abgang = max(0, $prev_stock - $curr_stock);
        $abgaenge[] = [
            'tag'    => $records[$i]['tag'],
            'abgang' => $abgang,
        ];
    }
    return $abgaenge;
}

/**
 * Holt die Tagesend-Bestaende pro Artikel fuer einen Zeitraum.
 * MySQL 5.7 kompatibel (kein LAG).
 *
 * @return array Gruppiert nach Artikel-ID: [id => [['tag' => ..., 'lagerstand_end' => ...], ...]]
 */
function get_daily_stock($conn, $days, $filter_sql = '') {
    $days = (int)$days;
    $sql = "
        SELECT
            t1.id,
            t1.han,
            t1.artikelname,
            DATE(t2.datum) AS tag,
            SUBSTRING_INDEX(GROUP_CONCAT(t2.lagerstand ORDER BY t2.datum DESC), ',', 1) + 0 AS lagerstand_end
        FROM lager_teci t1
        JOIN lager_teci_stand t2 ON t1.id = t2.id
        WHERE t2.datum >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
        {$filter_sql}
        GROUP BY t1.id, t1.han, t1.artikelname, DATE(t2.datum)
        ORDER BY t1.id, tag ASC
    ";

    $result = execute_query($conn, $sql);
    $grouped = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $id = $row['id'];
            if (!isset($grouped[$id])) {
                $grouped[$id] = [
                    'han'          => $row['han'],
                    'artikelname'  => $row['artikelname'],
                    'records'      => [],
                ];
            }
            $grouped[$id]['records'][] = [
                'tag'            => $row['tag'],
                'lagerstand_end' => (int)$row['lagerstand_end'],
            ];
        }
    }

    return $grouped;
}

/**
 * Berechnet taegliche Abgaenge pro Artikel via PHP (Self-Join-Alternative).
 * Gibt ein Array zurueck: [id => ['han' => ..., 'artikelname' => ..., 'abgaenge' => [['tag' => ..., 'abgang' => ...], ...]]]
 */
function get_daily_abgaenge($conn, $days, $filter_sql = '') {
    $daily_stock = get_daily_stock($conn, $days + 1, $filter_sql); // +1 Tag fuer Differenzberechnung

    $result = [];
    foreach ($daily_stock as $id => $data) {
        $abgaenge = calculate_abgaenge_from_records($data['records']);
        $result[$id] = [
            'han'          => $data['han'],
            'artikelname'  => $data['artikelname'],
            'abgaenge'     => $abgaenge,
            'records'      => $data['records'],
        ];
    }

    return $result;
}

/**
 * Lineare Prognose: Wie lange reicht der Bestand bei gegebenem Verbrauch?
 */
function calculate_prognose($current_stock, $avg_daily_consumption, $days_ahead = 30) {
    $prognose = [];
    for ($d = 0; $d <= $days_ahead; $d++) {
        $projected = max(0, $current_stock - ($avg_daily_consumption * $d));
        $prognose[] = [
            'tag'     => date('Y-m-d', strtotime("+{$d} days")),
            'bestand' => round($projected),
        ];
        if ($projected <= 0) break;
    }
    return $prognose;
}

/**
 * Einfache lineare Trendberechnung.
 * Gibt slope und Richtung zurueck.
 */
function calculate_trend($data_points) {
    $n = count($data_points);
    if ($n < 2) return ['slope' => 0, 'direction' => 'stabil'];

    $sum_x = $sum_y = $sum_xy = $sum_x2 = 0;
    for ($i = 0; $i < $n; $i++) {
        $sum_x  += $i;
        $sum_y  += $data_points[$i];
        $sum_xy += $i * $data_points[$i];
        $sum_x2 += $i * $i;
    }
    $denom = ($n * $sum_x2 - $sum_x * $sum_x);
    if ($denom == 0) return ['slope' => 0, 'direction' => 'stabil'];

    $slope = ($n * $sum_xy - $sum_x * $sum_y) / $denom;
    $direction = $slope > 0.5 ? 'steigend' : ($slope < -0.5 ? 'fallend' : 'stabil');

    return ['slope' => round($slope, 2), 'direction' => $direction];
}

/**
 * Normalisiert eine Datenreihe auf einen Index (Basis = 100).
 */
function normalize_to_index($data_series) {
    $base = $data_series[0] ?? 1;
    if ($base == 0) $base = 1;
    return array_map(function($v) use ($base) { return round(($v / $base) * 100, 1); }, $data_series);
}

/**
 * Datum in Perioden-Key umwandeln (Tag/Woche/Monat).
 */
function get_period_key($date_str, $aggregation) {
    $ts = strtotime($date_str);
    switch ($aggregation) {
        case 'week':
            return date('o', $ts) . '-KW' . date('W', $ts);
        case 'month':
            return date('Y-m', $ts);
        default:
            return $date_str;
    }
}

/**
 * Gibt die verfuegbaren Zeitraum-Optionen als HTML-Options zurueck.
 */
function render_time_period_options($selected = '3_weeks') {
    global $TIME_PERIODS;
    $html = '';
    foreach ($TIME_PERIODS as $key => $config) {
        $sel = ($key === $selected) ? ' selected' : '';
        $html .= "<option value=\"{$key}\"{$sel}>{$config['label']}</option>\n";
    }
    return $html;
}
