<?php
header('Content-Type: application/json');
require 'db_config.php';

// Standard-Parameter
$han = isset($_GET['han']) ? $conn->real_escape_string($_GET['han']) : null;
$aggregation = isset($_GET['aggregation']) ? $_GET['aggregation'] : 'day';
$is_topseller = isset($_GET['topseller']) && $_GET['topseller'] === 'true';

// Bestimme das Format für die Aggregation
$date_format_sql = '%Y-%m-%d';
$date_alias = 'DATE(t2.datum)';

switch ($aggregation) {
    case 'week':
        $date_format_sql = '%Y-%u'; // Jahr und Kalenderwoche
        $date_alias = 'YEAR(t2.datum), WEEK(t2.datum)';
        break;
    case 'month':
        $date_format_sql = '%Y-%m'; // Jahr und Monat
        $date_alias = 'YEAR(t2.datum), MONTH(t2.datum)';
        break;
    default: // day
        // Standard ist bereits gesetzt
        break;
}

// **SQL-Query-Logik**
$select_cols = "
    DATE_FORMAT(t2.datum, '$date_format_sql') as datum_agg,
    AVG(t2.lagerstand) as avg_lagerstand,
    t1.artikelname,
    t1.han
";
$group_by = "t1.han, t1.artikelname, datum_agg";
$order_by = "datum_agg ASC";
$limit = "";

// Query für Topseller (Artikel mit den meisten Lagerstand-Einträgen, kann angepasst werden)
if ($is_topseller) {
    // Topseller definieren wir hier als die Artikel mit der höchsten durchschnittlichen Lagerstand-Differenz
    // Über diese einfache Logik kann man aber streiten. Eine bessere wäre: Summe der negativen Lagerstands-Differenzen (Verkäufe)

    // Einfache Variante: Artikel mit den meisten Einträgen (zeigt Aktivität)
    $topseller_query = "
        SELECT 
            t1.han, 
            COUNT(t2.lagerstand) AS stand_count
        FROM 
            lager_teci t1
        JOIN 
            lager_teci_stand t2 ON t1.id = t2.lager
        GROUP BY 
            t1.han
        ORDER BY 
            stand_count DESC
        LIMIT 10
    ";
    
    $topseller_result = $conn->query($topseller_query);
    $top_hans = [];
    while ($row = $topseller_result->fetch_assoc()) {
        $top_hans[] = "'" . $conn->real_escape_string($row['han']) . "'";
    }

    if (!empty($top_hans)) {
        $han_filter = "t1.han IN (" . implode(',', $top_hans) . ")";
    } else {
        // Falls keine Topseller gefunden
        $han_filter = "1=0"; 
    }
    
    // Für die Visualisierung nehmen wir alle Topseller-HANs
    $where_clause = "WHERE $han_filter";
    $group_by = "t1.han, t1.artikelname, datum_agg"; // Gruppieren nach Artikel und Datum

} else {
    // Normaler Modus, entweder ein einzelner Artikel oder alle
    if ($han) {
        $where_clause = "WHERE t1.han = '$han'";
    } else {
        $where_clause = "";
        // Wenn alle Artikel (kein HAN gewählt), Gruppierung nach Datum (Gesamtbestand)
        $group_by = "datum_agg";
        $select_cols = "
            DATE_FORMAT(t2.datum, '$date_format_sql') as datum_agg,
            AVG(t2.lagerstand) as avg_lagerstand
        ";
    }
}


$sql = "
    SELECT 
        $select_cols
    FROM 
        lager_teci t1
    JOIN 
        lager_teci_stand t2 ON t1.id = t2.lager
    $where_clause
    GROUP BY 
        $group_by
    ORDER BY 
        $order_by
    $limit;
";

$result = $conn->query($sql);
$data = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Bei Topsellern geben wir alle Artikel zurück.
        if ($is_topseller) {
             $data[] = [
                'datum' => $row['datum_agg'],
                'lagerstand' => (float)$row['avg_lagerstand'],
                'artikelname' => $row['artikelname'],
                'han' => $row['han']
            ];
        } else {
            // Im Normalfall (alle oder ein Artikel) wird nur der Gesamtbestand/Einzelbestand übermittelt
             $data[] = [
                'datum' => $row['datum_agg'],
                'lagerstand' => (float)$row['avg_lagerstand']
            ];
        }
    }
}

echo json_encode(['data' => $data, 'is_topseller' => $is_topseller]);

$conn->close();
?>