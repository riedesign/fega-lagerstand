<?php
/**
 * Dispo-Dashboard: Datenlogik (nur Eigenprodukte / Polar)
 * — kritische Produkte, Ladenhueter, Topseller, Lead-Time-Warnungen.
 */

if (!defined('EIGEN_FILTER_SQL')) {
    define('EIGEN_FILTER_SQL', "AND t1.artikelname LIKE '%Polar%'");
}

/**
 * Gesamtuebersicht fuer Eigenprodukte (Dispo-Sicht).
 */
function get_dispo_overview($conn, $time_period) {
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

/**
 * Berechnet den Nachbestellungs-Vorschlag fuer einen einzelnen Artikel.
 *
 * Logik: wenn die aktuelle Reichweite beim Kunden unter Lead-Time + 30%
 * Safety-Puffer faellt, empfehlen wir eine Nachbestellung die den Bestand
 * auf eine Ziel-Reichweite von TARGET_REICHWEITE_DAYS auffuellt.
 * Empfehlung wird auf die naechste Zehnerstelle aufgerundet damit die
 * Mengen fuer Sales-Kommunikation "sauber" sind.
 */
function berechne_nachbestell_vorschlag($fega_bestand, $avg_daily, $lead_time_days) {
    $target_days = 21;       // Ziel-Reichweite nach Lieferung
    $safety_factor = 1.3;    // 30% Puffer auf Lead-Time
    $round_to = 10;          // Vorschlaege auf 10er-Schritte runden

    if ($avg_daily <= 0) {
        return [
            'stk'             => 0,
            'grund'           => 'Kein Abverkauf im Zeitraum',
            'reichweite_tage' => null,
            'empfehlen'       => false,
        ];
    }

    $reichweite = $fega_bestand / $avg_daily;
    $schwelle   = $lead_time_days * $safety_factor;

    if ($reichweite >= $schwelle) {
        return [
            'stk'             => 0,
            'grund'           => 'Bestand ausreichend (' . round($reichweite, 1) . ' Tage Reichweite)',
            'reichweite_tage' => round($reichweite, 1),
            'empfehlen'       => false,
        ];
    }

    $soll_bestand  = $target_days * $avg_daily;
    $rohmenge      = max(0, $soll_bestand - $fega_bestand);
    $nachbestellen = (int)(ceil($rohmenge / $round_to) * $round_to);

    return [
        'stk'             => $nachbestellen,
        'grund'           => 'Reichweite ' . round($reichweite, 1) . ' T < '
                             . round($schwelle, 1) . ' T (Lead + Safety)',
        'reichweite_tage' => round($reichweite, 1),
        'empfehlen'       => $nachbestellen > 0,
    ];
}

/**
 * Bestand-Abgleich: aktueller Lagerstand aller Polar-Artikel bei Fega +
 * (falls JTL erreichbar) bei uns (Rieste). Wird ueber die HAN verknuepft.
 *
 * Ergaenzt pro Artikel auch den Nachbestellungs-Vorschlag basierend auf
 * den Verkaufsmengen im uebergebenen Zeitraum.
 *
 * @param mysqli    $conn        Fega-DB
 * @param PDO|null  $jtl_conn    optionale JTL-MSSQL-Verbindung
 * @param string    $time_period Key aus $TIME_PERIODS (fuer Verkaufs-Mittel)
 * @return array
 */
function get_bestand_abgleich($conn, $jtl_conn, $time_period = '4_weeks') {
    // Alle Polar-Artikel mit aktuellem Fega-Bestand
    $sql = "
        SELECT t1.id, t1.han, t1.artikelname, t2.lagerstand
        FROM lager_teci t1
        JOIN lager_teci_stand t2 ON t1.id = t2.id
        WHERE t1.artikelname LIKE '%Polar%'
          AND (t2.id, t2.datum) IN (
              SELECT id, MAX(datum) FROM lager_teci_stand GROUP BY id
          )
        ORDER BY t1.artikelname
    ";

    $artikel = [];
    $hans = [];
    $result = execute_query($conn, $sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $artikel[] = [
                'id'             => (int)$row['id'],
                'han'            => $row['han'],
                'artikelname'    => $row['artikelname'],
                'fega_bestand'   => (int)$row['lagerstand'],
                'rieste_bestand' => null,
                'summe'          => null,
            ];
            if ($row['han'] !== null && $row['han'] !== '') {
                $hans[] = $row['han'];
            }
        }
    }

    $rieste_map = [];
    $jtl_available = ($jtl_conn !== null);
    if ($jtl_conn !== null && count($hans) > 0) {
        $rieste_map = get_rieste_bestand_map($jtl_conn, array_unique($hans));
    }

    // Abverkaufs-Daten im gewaehlten Zeitraum holen (nur Polar), fuer die
    // Nachbestellungs-Vorschlaege. Liefert pro Artikel-ID die Abgaenge.
    $days = get_days_for_period($time_period);
    $abgaenge_data = get_daily_abgaenge($conn, $days, EIGEN_FILTER_SQL);

    $sum_fega = 0;
    $sum_rieste = 0;
    $rieste_match_count = 0;
    $vorschlaege_count = 0;
    foreach ($artikel as $i => $a) {
        $sum_fega += $a['fega_bestand'];
        if ($jtl_available && isset($rieste_map[$a['han']])) {
            $rb = (int)round($rieste_map[$a['han']]);
            $artikel[$i]['rieste_bestand'] = $rb;
            $artikel[$i]['summe'] = $a['fega_bestand'] + $rb;
            $sum_rieste += $rb;
            $rieste_match_count++;
        }

        // Verkaufs-Daten fuer den Artikel → avg_daily → Vorschlag
        $total_abgang = 0;
        if (isset($abgaenge_data[$a['id']])) {
            foreach ($abgaenge_data[$a['id']]['abgaenge'] as $e) {
                $total_abgang += $e['abgang'];
            }
        }
        $avg_daily = ($days > 0) ? $total_abgang / $days : 0;
        $lead_time = get_lead_time_days($a['han']);

        $vorschlag = berechne_nachbestell_vorschlag(
            $a['fega_bestand'], $avg_daily, $lead_time
        );
        $artikel[$i]['avg_daily']    = round($avg_daily, 2);
        $artikel[$i]['total_abgang'] = $total_abgang;
        $artikel[$i]['lead_time']    = $lead_time;
        $artikel[$i]['vorschlag']    = $vorschlag;

        if ($vorschlag['empfehlen']) {
            $vorschlaege_count++;
        }
    }

    return [
        'artikel'            => $artikel,
        'time_period'        => $time_period,
        'vorschlaege_count'  => $vorschlaege_count,
        'sum_fega'           => $sum_fega,
        'sum_rieste'         => $sum_rieste,
        'sum_gesamt'         => $sum_fega + $sum_rieste,
        'artikel_count'      => count($artikel),
        'rieste_match_count' => $rieste_match_count,
        'jtl_available'      => $jtl_available,
    ];
}

/**
 * Holt den aktuellen Rieste-Lagerbestand pro HAN aus JTL (eazybusiness).
 *
 * Da tArtikel.cHAN nicht unique ist (ein HAN kann mehreren JTL-Artikeln
 * zugeordnet sein), wird pro HAN summiert. tlagerbestand hat bei Rieste
 * nur einen Lagerort — daher reicht LEFT JOIN + SUM.
 *
 * @param PDO $jtl_conn
 * @param array $hans Liste distincter HANs
 * @return array [han => bestand]
 */
function get_rieste_bestand_map($jtl_conn, array $hans) {
    if (empty($hans)) return [];

    // HANs sicher quoten (Read-Only-User, aber quoten bleibt richtig).
    $placeholders = [];
    foreach ($hans as $han) {
        $placeholders[] = $jtl_conn->quote((string)$han);
    }
    $in = implode(',', $placeholders);

    $sql = "
        SELECT a.cHAN AS han,
               SUM(ISNULL(lb.fLagerbestand, 0)) AS bestand
        FROM tArtikel a
        LEFT JOIN tlagerbestand lb ON lb.kArtikel = a.kArtikel
        WHERE a.cHAN IN ({$in})
        GROUP BY a.cHAN
    ";

    try {
        $stmt = $jtl_conn->query($sql);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[$row['han']] = (float)$row['bestand'];
        }
        return $map;
    } catch (Throwable $e) {
        error_log('JTL Bestand-Query fehlgeschlagen: ' . $e->getMessage());
        return [];
    }
}
