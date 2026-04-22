<?php
/**
 * Markt-Dashboard: Datenlogik fuer die Sales-Sicht.
 *
 * Liefert alle Daten fuer die fuenf Bloecke der `markt.php`-Seite in einem
 * einzigen Aufruf. Nutzt ausschliesslich bestehende Bausteine aus
 * includes/functions.php (get_daily_abgaenge, extract_marke,
 * is_eigenprodukt, get_period_key, calculate_abgaenge_from_records).
 *
 * Prinzip:
 *  - Einmal alle Abgaenge ueber $days * 2 Tage holen.
 *  - In PHP trennen in "aktuelle Periode" und "Vorperiode gleicher Laenge".
 *  - Aggregieren nach Marke, Zeitraum-Bucket, Artikel.
 *
 * MySQL 5.7 / PHP 7.0 kompatibel: keine Window-Funktionen, keine
 * Arrow-Functions, keine `match()`.
 */

/**
 * Gesamtuebersicht fuer das Markt-Dashboard.
 *
 * @param mysqli $conn
 * @param string $time_period Key aus $TIME_PERIODS
 * @return array
 */
function get_markt_data($conn, $time_period) {
    $days = get_days_for_period($time_period);

    // Aggregations-Granularitaet: kurze Zeitraeume = Tag, mittel = Woche,
    // lang = Monat. Hart gesetzt, passt gut fuer die 6 Standard-Zeitraeume.
    if ($days <= 30) {
        $aggregation = 'day';
    } elseif ($days <= 90) {
        $aggregation = 'week';
    } else {
        $aggregation = 'month';
    }

    // Doppeltes Fenster: aktuelle Periode + Vorperiode gleicher Laenge.
    $abgaenge_data = get_daily_abgaenge($conn, $days * 2);

    $aktuell_cutoff = date('Y-m-d', strtotime("-{$days} days"));

    // Akkumulatoren fuer Block 1 (KPI)
    $gesamt_abgang_aktuell     = 0;
    $gesamt_abgang_vorperiode  = 0;
    $polar_abgang_aktuell      = 0;
    $polar_abgang_vorperiode   = 0;
    $polar_active_ids          = [];
    $fremd_active_ids          = [];

    // Marken-Aggregation (Blocks 2 + 3)
    $marken_totals      = [];  // [marke] => abgang aktuell
    $marken_vorperiode  = [];  // [marke] => abgang vorperiode
    $marken_artikel     = [];  // [marke] => [id => true] (aktive Artikel)
    $marken_top_artikel = [];  // [marke] => ['id','han','artikelname','abgang']

    // Zeitverlauf (Block 4): nur aktuelle Periode
    $eigen_by_period       = [];  // [pkey] => abgang
    $fremd_by_period       = [];  // [pkey] => abgang
    $fremdmarken_by_period = [];  // [marke][pkey] => abgang

    // Top-Artikel (Block 5)
    $artikel_totals = [];

    foreach ($abgaenge_data as $id => $data) {
        $marke    = extract_marke($data['artikelname']);
        $is_eigen = is_eigenprodukt($data['artikelname']);

        $abgang_aktuell    = 0;
        $abgang_vorperiode = 0;
        $spark_buckets     = [];  // [pkey] => abgang  (fuer Sparkline)

        foreach ($data['abgaenge'] as $entry) {
            $tag    = $entry['tag'];
            $abgang = (int)$entry['abgang'];

            if ($tag >= $aktuell_cutoff) {
                $abgang_aktuell += $abgang;

                $pkey = get_period_key($tag, $aggregation);

                if ($is_eigen) {
                    if (!isset($eigen_by_period[$pkey])) $eigen_by_period[$pkey] = 0;
                    $eigen_by_period[$pkey] += $abgang;
                } else {
                    if (!isset($fremd_by_period[$pkey])) $fremd_by_period[$pkey] = 0;
                    $fremd_by_period[$pkey] += $abgang;

                    if (!isset($fremdmarken_by_period[$marke])) $fremdmarken_by_period[$marke] = [];
                    if (!isset($fremdmarken_by_period[$marke][$pkey])) $fremdmarken_by_period[$marke][$pkey] = 0;
                    $fremdmarken_by_period[$marke][$pkey] += $abgang;
                }

                if (!isset($spark_buckets[$pkey])) $spark_buckets[$pkey] = 0;
                $spark_buckets[$pkey] += $abgang;
            } else {
                $abgang_vorperiode += $abgang;
            }
        }

        $gesamt_abgang_aktuell    += $abgang_aktuell;
        $gesamt_abgang_vorperiode += $abgang_vorperiode;

        if ($is_eigen) {
            $polar_abgang_aktuell    += $abgang_aktuell;
            $polar_abgang_vorperiode += $abgang_vorperiode;
        }

        if ($abgang_aktuell > 0) {
            if ($is_eigen) {
                $polar_active_ids[$id] = true;
            } else {
                $fremd_active_ids[$id] = true;
            }
        }

        // Marken-Zaehlung
        if (!isset($marken_totals[$marke])) {
            $marken_totals[$marke]      = 0;
            $marken_vorperiode[$marke]  = 0;
            $marken_artikel[$marke]     = [];
            $marken_top_artikel[$marke] = null;
        }
        $marken_totals[$marke]     += $abgang_aktuell;
        $marken_vorperiode[$marke] += $abgang_vorperiode;
        if ($abgang_aktuell > 0) {
            $marken_artikel[$marke][$id] = true;
            if ($marken_top_artikel[$marke] === null
                || $abgang_aktuell > $marken_top_artikel[$marke]['abgang']) {
                $marken_top_artikel[$marke] = [
                    'id'          => $id,
                    'han'         => $data['han'],
                    'artikelname' => $data['artikelname'],
                    'abgang'      => $abgang_aktuell,
                ];
            }
        }

        // Artikel-Zeile fuer Tabelle (Block 5)
        $records       = $data['records'];
        $current_stock = !empty($records) ? end($records)['lagerstand_end'] : 0;

        // Sparkline: letzte bis zu 12 Buckets der aktuellen Periode
        ksort($spark_buckets);
        $spark_values = array_values($spark_buckets);
        if (count($spark_values) > 12) {
            $spark_values = array_slice($spark_values, -12);
        }

        $artikel_totals[$id] = [
            'id'          => $id,
            'han'         => $data['han'],
            'artikelname' => $data['artikelname'],
            'marke'       => $marke,
            'is_eigen'    => $is_eigen,
            'abgang'      => $abgang_aktuell,
            'abgang_vor'  => $abgang_vorperiode,
            'sparkline'   => $spark_values,
            'bestand'     => (int)$current_stock,
        ];
    }

    // === Block 1: KPI-Zahlen ===
    $polar_anteil     = $gesamt_abgang_aktuell > 0
        ? round($polar_abgang_aktuell / $gesamt_abgang_aktuell * 100, 1) : 0;
    $polar_anteil_vor = $gesamt_abgang_vorperiode > 0
        ? round($polar_abgang_vorperiode / $gesamt_abgang_vorperiode * 100, 1) : 0;
    $anteil_delta     = round($polar_anteil - $polar_anteil_vor, 1);
    if ($anteil_delta > 1) {
        $anteil_direction = 'steigend';
    } elseif ($anteil_delta < -1) {
        $anteil_direction = 'fallend';
    } else {
        $anteil_direction = 'stabil';
    }

    // === Blocks 2 + 3: Marken-Liste ===
    $marken_liste = [];
    foreach ($marken_totals as $marke => $total) {
        $vor = (int)($marken_vorperiode[$marke] ?? 0);
        if ($vor > 0) {
            $delta_pct = round(($total - $vor) / $vor * 100, 1);
        } else {
            $delta_pct = $total > 0 ? 100.0 : 0.0;
        }
        if ($delta_pct > 5) {
            $direction = 'steigend';
        } elseif ($delta_pct < -5) {
            $direction = 'fallend';
        } else {
            $direction = 'stabil';
        }
        $marken_liste[] = [
            'marke'             => $marke,
            'abgang'            => $total,
            'anteil'            => $gesamt_abgang_aktuell > 0
                ? round($total / $gesamt_abgang_aktuell * 100, 1) : 0,
            'vorperiode_abgang' => $vor,
            'delta_pct'         => $delta_pct,
            'direction'         => $direction,
            'artikel_count'     => count($marken_artikel[$marke]),
            'top_artikel'       => $marken_top_artikel[$marke],
            'is_eigen'          => (stripos($marke, 'Polar') !== false),
        ];
    }
    usort($marken_liste, function($a, $b) { return $b['abgang'] - $a['abgang']; });

    // Sonstige-Warnung: >15% sagt "$MARKEN_MAP fehlt Eintraege"
    $sonstige_anteil = 0;
    foreach ($marken_liste as $m) {
        if (stripos($m['marke'], 'Sonstige') !== false) {
            $sonstige_anteil = $m['anteil'];
            break;
        }
    }

    // === Block 4: Zeitverlauf ===
    $all_periods = array_unique(array_merge(
        array_keys($eigen_by_period),
        array_keys($fremd_by_period)
    ));
    sort($all_periods);

    $eigen_series = [];
    $fremd_series = [];
    foreach ($all_periods as $pkey) {
        $eigen_series[] = (int)($eigen_by_period[$pkey] ?? 0);
        $fremd_series[] = (int)($fremd_by_period[$pkey] ?? 0);
    }

    // Top-3 Fremdmarken fuer Hintergrundlinien
    $fremd_marken_totals = [];
    foreach ($fremdmarken_by_period as $marke => $periods) {
        $fremd_marken_totals[$marke] = array_sum($periods);
    }
    arsort($fremd_marken_totals);
    $top3_marken = array_slice(array_keys($fremd_marken_totals), 0, 3);

    $fremd_top3_series = [];
    foreach ($top3_marken as $marke) {
        $values = [];
        foreach ($all_periods as $pkey) {
            $values[] = (int)($fremdmarken_by_period[$marke][$pkey] ?? 0);
        }
        $fremd_top3_series[] = [
            'marke'  => $marke,
            'values' => $values,
        ];
    }

    // === Block 5: Top-20 Artikel ===
    $aktive_artikel = [];
    foreach ($artikel_totals as $a) {
        if ($a['abgang'] > 0) $aktive_artikel[] = $a;
    }
    usort($aktive_artikel, function($a, $b) { return $b['abgang'] - $a['abgang']; });
    $top_artikel = array_slice($aktive_artikel, 0, 20);
    foreach ($top_artikel as $i => $a) {
        $top_artikel[$i]['anteil'] = $gesamt_abgang_aktuell > 0
            ? round($a['abgang'] / $gesamt_abgang_aktuell * 100, 1) : 0;
    }

    return [
        'time_period' => $time_period,
        'days'        => $days,
        'aggregation' => $aggregation,
        'kpi' => [
            'gesamt_abgang'     => $gesamt_abgang_aktuell,
            'gesamt_abgang_vor' => $gesamt_abgang_vorperiode,
            'polar_abgang'      => $polar_abgang_aktuell,
            'polar_anteil'      => $polar_anteil,
            'polar_anteil_vor'  => $polar_anteil_vor,
            'anteil_delta'      => $anteil_delta,
            'anteil_direction'  => $anteil_direction,
            'polar_active'      => count($polar_active_ids),
            'fremd_active'      => count($fremd_active_ids),
        ],
        'marken'            => $marken_liste,
        'sonstige_anteil'   => $sonstige_anteil,
        'sonstige_warnung'  => $sonstige_anteil > 15,
        'zeitverlauf' => [
            'labels'     => $all_periods,
            'eigen'      => $eigen_series,
            'fremd'      => $fremd_series,
            'fremd_top3' => $fremd_top3_series,
        ],
        'top_artikel' => array_values($top_artikel),
    ];
}
