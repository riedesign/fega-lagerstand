<?php
// db_config.php einbinden, um die DB-Verbindung zu nutzen
require_once 'db_config.php';

// Parameter aus der URL auslesen
$default_view = 'day';
$view = $_GET['view'] ?? $default_view;

$default_han_filter = 'polar_only'; // Standardwert für die Tabelle
$han_filter = $_GET['han'] ?? 'all'; // Filter für das Diagramm (Artikel)
$table_filter = $_GET['table_filter'] ?? $default_han_filter; 

// Parameter für den Zeitraum der Visualisierung/Tabelle
$default_time_period = '3_weeks'; 
$time_period = $_GET['time_period'] ?? $default_time_period;


// ***********************************************************************************
// * PHP-FUNKTIONEN FÜR DATENABRUF UND HILFSFUNKTIONEN
// ***********************************************************************************

/**
 * Hilfsfunktion zur Berechnung der Tage aus dem Zeit-String.
 */
function calculate_days_from_period($time_period) {
    switch ($time_period) {
        case '2_weeks':
            return 14;
        case '3_weeks':
        default:
            return 21; 
        case '4_weeks':
            return 28;
        case '2_months':
            return 60;
    }
}

/**
 * HILFSFUNKTION FÜR Lead Time (Wird statisch angenommen)
 * Würde später aus einer DB-Tabelle für jeden Artikel gelesen.
 * @return int Die Lead Time in Tagen.
 */
function get_lead_time_days($han) {
    // Beispiel Logik: Für Polar-Produkte 7 Tage, für Fremdprodukte 14 Tage
    if (strpos($han, 'POLAR') !== false) {
        return 7;
    }
    return 14; 
}


/**
 * Liefert die aggregierten Lagerbestandsdaten für das Diagramm.
 */
function get_lagerstand_data($conn, $view, $han_filter, $time_period) {
    
    $days_to_filter = calculate_days_from_period($time_period); 

    // Definiert das SQL-Format für die Periode (Tag, Woche, Monat)
    $date_format_sql = '';
    
    switch ($view) {
        case 'week':
            $date_format_sql = "CONCAT(YEAR(t2.datum), '-KW', WEEK(t2.datum, 3))";
            break;
        case 'month':
            $date_format_sql = "DATE_FORMAT(t2.datum, '%Y-%m')";
            break;
        case 'day':
        default:
            $date_format_sql = "DATE(t2.datum)";
            break;
    }
    
    $sql = "
        SELECT
            t1.han,
            t1.artikelname,
            $date_format_sql AS periode,
            SUBSTRING_INDEX(GROUP_CONCAT(t2.lagerstand ORDER BY t2.datum DESC), ',', 1) AS lagerstand 
        FROM
            `lager_teci` t1
        JOIN
            `lager_teci_stand` t2 ON t1.id = t2.id 
        WHERE
            t1.han IS NOT NULL 
            AND t2.datum >= DATE_SUB(CURDATE(), INTERVAL " . (int)$days_to_filter . " DAY)
    ";
    
    if ($han_filter === 'polar') {
        $sql .= " AND t1.artikelname LIKE '%Polar%'";
    } elseif ($han_filter !== 'all') {
        $sql .= " AND t1.han = '" . $conn->real_escape_string($han_filter) . "'";
    }

    $sql .= "
        GROUP BY 
            periode, t1.han, t1.artikelname
        ORDER BY 
            periode ASC, t1.artikelname ASC
    ";

    $result = execute_query($conn, $sql);
    
    $aggregated_data = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $artikel_name = $row['artikelname'] . ' (' . $row['han'] . ')';
            $periode_label = $row['periode'];

            if (!isset($aggregated_data[$artikel_name])) {
                $aggregated_data[$artikel_name] = ['labels' => [], 'data' => []];
            }
            
            $aggregated_data[$artikel_name]['labels'][] = $periode_label;
            $aggregated_data[$artikel_name]['data'][] = (int)$row['lagerstand']; 
        }
    }

    return $aggregated_data;
}


/**
 * Berechnet den dynamischen Warnwert und liefert die Topseller-Liste.
 * NEU: Integriert Lead Time, Reichweite und Letzte Bewegung.
 */
function get_topseller_list($conn, $initial_filter, $time_period) {
    
    // Basis für die Historie (Verbrauchsberechnung)
    $days_to_filter_base = calculate_days_from_period($time_period);
    // +1 Tag Puffer, um Differenzen zu berechnen
    $days_interval = $days_to_filter_base + 1; 
    
    $sql_history = "
        SELECT 
            t1.id, 
            t1.han,
            t1.artikelname,
            t1.url,
            t1.kunde AS lager, 
            t2.datum,
            t2.lagerstand
        FROM 
            `lager_teci` t1
        JOIN 
            `lager_teci_stand` t2 ON t1.id = t2.id 
        WHERE 
            t2.datum >= DATE_SUB(CURDATE(), INTERVAL " . (int)$days_interval . " DAY) 
    ";

    // FILTER-LOGIK für die Topseller-Tabelle: Standard ist "Polar"
    if ($initial_filter === 'polar_only') {
        $sql_history .= " AND t1.artikelname LIKE '%Polar%'";
    }

    $sql_history .= "
        ORDER BY 
            t1.id, t2.datum DESC 
    ";
    
    $result_history = execute_query($conn, $sql_history);
    
    $raw_history = [];
    $latest_records = []; 

    if ($result_history) {
        while ($row = $result_history->fetch_assoc()) {
            $key = $row['id'];
            
            if (!isset($latest_records[$key])) {
                $latest_records[$key] = $row;
            }
            
            $raw_history[$key][] = $row;
        }
    }
    
    $topseller_list = [];

    foreach ($latest_records as $key => $current_data) {
        $records = $raw_history[$key];
        $daily_consumption = [];
        $records = array_reverse($records); 

        // Initialisiere die Variable für die letzte Bewegung (Verbrauch)
        $last_consumption_date = null;
        
        // --- 1. Verbrauch und letzte Bewegung berechnen ---
        for ($i = 1; $i < count($records); $i++) {
            $current_stock = (int)$records[$i]['lagerstand'];
            $previous_stock = (int)$records[$i-1]['lagerstand']; 
            
            $consumption = $previous_stock - $current_stock;

            if ($consumption > 0) {
                $daily_consumption[] = $consumption;
                
                // Datum des letzten Verbrauchs speichern
                if ($last_consumption_date === null) {
                    $last_consumption_date = $records[$i-1]['datum']; 
                }
            }
            
            if (count($daily_consumption) >= 10) {
                $daily_consumption = array_slice($daily_consumption, -10); 
                break; 
            }
        }
        
        $days_counted = count($daily_consumption);
        
        // Berechnung des durchschnittlichen Verbrauchs im Zeitraum
        $sum_consumption = array_sum($daily_consumption);
        $avg_consumption = !empty($daily_consumption) ? $sum_consumption / $days_counted : 0;
        
        // Durchschnittlicher TÄGLICHER Verbrauch über den gesamten Filter-Zeitraum (für Reichweite)
        $avg_daily_consumption_period = $sum_consumption / $days_to_filter_base; 
        
        // --- 2. Lead Time, Reichweite und neues Warnlimit berechnen ---
        $han = $current_data['han'];
        $lead_time = get_lead_time_days($han); // Statisch angenommene Lead Time
        
        // Dynamisches Warnlimit: Lead Time in Tagen * Durchschnittsverbrauch pro Tag (+ 30% Sicherheitspuffer)
        $dynamic_limit = ceil($avg_consumption * 1.3);
        
        // Reichweite in Tagen: Lagerstand / (Durchschnittlicher täglicher Verbrauch über den Zeitraum)
        if ($avg_daily_consumption_period > 0) {
            $days_of_supply = floor($current_data['lagerstand'] / $avg_daily_consumption_period);
        } else {
            $days_of_supply = $current_data['lagerstand'] > 0 ? '∞' : 0; // Unendlich, wenn Bestand > 0 aber Verbrauch = 0
        }
        
        // --- 3. Daten speichern ---
        $current_data['avg_consumption'] = round($avg_consumption, 2);
        $current_data['lead_time'] = $lead_time;
        $current_data['days_of_supply'] = $days_of_supply;
        $current_data['last_consumption'] = $last_consumption_date ?? 'Nie im Zeitraum';
        
        // Das Warnlimit wird nun als KRITISCH markiert, wenn die Reichweite < Lead Time ist!
        $current_data['warning_limit'] = ($days_of_supply !== '∞' && $days_of_supply < $lead_time) ? 'CRITICAL' : 'OK';

        $topseller_list[] = $current_data;
    }
    
    // Sortiere nach Reichweite (aufsteigend: kritische zuerst)
    usort($topseller_list, function($a, $b) {
        $a_supply = ($a['days_of_supply'] === '∞') ? PHP_INT_MAX : $a['days_of_supply'];
        $b_supply = ($b['days_of_supply'] === '∞') ? PHP_INT_MAX : $b['days_of_supply'];
        return $a_supply <=> $b_supply;
    });
    
    return $topseller_list;
}

// AJAX-Abfrage-Handler
if (isset($_GET['ajax']) && $_GET['ajax'] == 'true') {
    header('Content-Type: application/json');
    $data = get_lagerstand_data($conn, $view, $han_filter, $time_period); 
    
    if (empty($data)) {
        echo json_encode([]); 
    } else {
        echo json_encode($data);
    }
    $conn->close();
    exit;
}

// Daten für die Topseller-Tabelle abrufen
$topseller_table_data = get_topseller_list($conn, $table_filter, $time_period); 
// ***********************************************************************************
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lagerbestands-Visualisierung</title>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.7/css/dataTables.dataTables.min.css">
    <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/2.0.7/js/dataTables.min.js"></script>
    
    <style>
        /* Grundlegendes Styling */
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f4f4f4; }
        .container { max-width: 1300px; margin: auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        h1, h2 { color: #333; }
        .controls { margin-bottom: 20px; padding: 15px; background: #eee; border-radius: 5px; display: flex; gap: 20px; align-items: center;}
        .controls label { margin-right: 5px; font-weight: bold; }
        /* HÖHE DES DIAGRAMMS ERHÖHT */
        .chart-container { position: relative; height: 600px; width: 100%; margin-top: 20px; } 
        
        /* Tabellen Styling */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #5cb85c; color: white; cursor: pointer; } 
        
        /* Lagerbestands-Hervorhebung NEUE LOGIK */
        .critical { background-color: #ffc4c4 !important; font-weight: bold; color: #cc0000; }
        .low-stock { background-color: #fff0b3 !important; }
        .obsolete { background-color: #e0e0e0 !important; color: #666; font-style: italic; } /* Für Ladenhüter */

        /* DataTables Anpassung */
        .dataTables_wrapper { font-size: 0.9em; }
    </style>
</head>
<body>

<div class="container">
    <h1>📈 Lagerbestands-Visualisierung</h1>
    
    <div class="controls">
        <div>
            <label for="view">Aggregat:</label>
            <select name="view" id="view" onchange="loadChartData()">
                <option value="day" <?php if ($view == 'day') echo 'selected'; ?>>Pro Tag (Letzter Stand)</option>
                <option value="week" <?php if ($view == 'week') echo 'selected'; ?>>Pro Woche (Letzter Stand)</option>
                <option value="month" <?php if ($view == 'month') echo 'selected'; ?>>Pro Monat (Letzter Stand)</option>
            </select>
        </div>
        
        <div>
            <label for="han-filter">Artikel-Filter:</label>
            <select name="han" id="han-filter" onchange="loadChartData()">
                <option value="all" <?php if ($han_filter == 'all') echo 'selected'; ?>>Alle Artikel anzeigen</option>
                <option value="polar" <?php if ($han_filter == 'polar') echo 'selected'; ?>>NUR EIGENE PRODUKTE ("Polar")</option>
                <option disabled>--- Topseller/Artikel ---</option>
                <?php 
                // Füge die Topseller als Filteroptionen hinzu
                foreach ($topseller_table_data as $item) {
                    $selected = ($han_filter == $item['han']) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($item['han']) . '" ' . $selected . '>' 
                        . htmlspecialchars($item['artikelname']) . ' (' . htmlspecialchars($item['han']) . ')' 
                        . '</option>';
                }
                ?>
            </select>
        </div>
        
        <div>
            <label for="chart_time_period">Zeitraum (Diagramm):</label>
            <select name="chart_time_period" id="chart_time_period" onchange="loadChartData()">
                <option value="2_weeks" <?php if ($time_period == '2_weeks') echo 'selected'; ?>>Letzte 2 Wochen</option>
                <option value="3_weeks" <?php if ($time_period == '3_weeks') echo 'selected'; ?>>Letzte 3 Wochen (Standard)</option>
                <option value="4_weeks" <?php if ($time_period == '4_weeks') echo 'selected'; ?>>Letzte 4 Wochen</option>
                <option value="2_months" <?php if ($time_period == '2_months') echo 'selected'; ?>>Letzte 2 Monate</option>
            </select>
        </div>
    </div>

    <div class="chart-container">
        <canvas id="lagerChart"></canvas>
    </div>

    <hr>
    
    <h2>🚨 Lager-Reichweiten-Analyse (Sortiert nach Dringlichkeit)</h2>
    
    <div class="controls" style="justify-content: flex-start;">
        <div>
            <label for="table_filter">Artikel-Auswahl:</label>
            <select name="table_filter" id="table_filter" onchange="window.location.href = 'index.php?view=<?php echo $view; ?>&han=<?php echo $han_filter; ?>&time_period=<?php echo $time_period; ?>&table_filter=' + this.value;">
                <option value="polar_only" <?php if ($table_filter == 'polar_only') echo 'selected'; ?>>NUR EIGENE PRODUKTE ("Polar")</option>
                <option value="all" <?php if ($table_filter == 'all') echo 'selected'; ?>>ALLE ARTIKEL ANZEIGEN</option>
            </select>
        </div>

        <div>
            <label for="table_time_period">Historie/Verbrauch für:</label>
            <select name="table_time_period" id="table_time_period" onchange="window.location.href = 'index.php?view=<?php echo $view; ?>&han=<?php echo $han_filter; ?>&table_filter=<?php echo $table_filter; ?>&time_period=' + this.value;">
                <option value="2_weeks" <?php if ($time_period == '2_weeks') echo 'selected'; ?>>Letzte 2 Wochen</option>
                <option value="3_weeks" <?php if ($time_period == '3_weeks') echo 'selected'; ?>>Letzte 3 Wochen (Standard)</option>
                <option value="4_weeks" <?php if ($time_period == '4_weeks') echo 'selected'; ?>>Letzte 4 Wochen</option>
                <option value="2_months" <?php if ($time_period == '2_months') echo 'selected'; ?>>Letzte 2 Monate</option>
            </select>
        </div>
    </div>
    
    <p>Die Tabelle zeigt: **Reichweite in Tagen** vs. **Mindestbestelldauer (Lead Time)**. **Kritisch** bei Reichweite < Lead Time.</p>
    
    <table id="topsellerTable" class="display" style="width:100%">
        <thead>
            <tr>
                <th>Artikelnummer (HAN)</th>
                <th>Artikelname</th>
                <th>Kunde/Lager</th>
                <th>Aktueller Lagerstand</th>
                <th>Mindestbestelldauer (Tage)</th>
                <th>**Reichweite (Tage)**</th>
                <th>Datum des letzten Verbrauchs</th>
                <th>Warnstatus</th>
                <th>Datum des Stands</th>
                <th>Direktlink</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            if (!empty($topseller_table_data)) {
                foreach ($topseller_table_data as $item) {
                    $critical_class = '';
                    $warning_status_display = $item['warning_limit'];
                    
                    // WARNLOGIK: Reichweite < Lead Time oder Ladenhüter?
                    if ($item['warning_limit'] === 'CRITICAL') {
                        $critical_class = 'critical';
                        $warning_status_display = 'Zu kurz! (' . $item['days_of_supply'] . ' Tage)';
                    }
                    
                    if ($item['days_of_supply'] === '∞' && $item['lagerstand'] > 0) {
                        $critical_class = 'obsolete';
                        $warning_status_display = 'Ladenhüter';
                    }

                    // Link erstellen
                    $url_link = !empty($item['url']) 
                        ? '<a href="' . htmlspecialchars($item['url']) . '" target="_blank">Link</a>' 
                        : 'N/A';
                    
                    echo '<tr class="' . $critical_class . '">';
                    echo '<td>' . htmlspecialchars($item['han']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['artikelname']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['lager'] ?? 'N/A') . '</td>'; 
                    echo '<td>' . htmlspecialchars($item['lagerstand']) . '</td>';
                    // NEUE SPALTEN
                    echo '<td>' . htmlspecialchars($item['lead_time']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['days_of_supply']) . '</td>';
                    echo '<td>' . htmlspecialchars($item['last_consumption']) . '</td>';
                    echo '<td>' . htmlspecialchars($warning_status_display) . '</td>';
                    // /NEUE SPALTEN
                    echo '<td>' . htmlspecialchars($item['datum']) . '</td>';
                    echo '<td>' . $url_link . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="10">Keine aktuellen Lagerbestandsdaten gefunden.</td></tr>';
            }
            ?>
        </tbody>
    </table>

</div>

<script>
    let lagerChart;
    const CHART_COLORS = [
        'rgb(54, 162, 235)', 'rgb(255, 99, 132)', 'rgb(75, 192, 192)', 'rgb(255, 206, 86)', 
        'rgb(153, 102, 255)', 'rgb(255, 159, 64)',
    ];

    function initializeDataTables() {
        if ($.fn.DataTable.isDataTable('#topsellerTable')) {
            $('#topsellerTable').DataTable().destroy();
        }
        $('#topsellerTable').DataTable({
            "paging": true,          
            "searching": true,       
            "info": true,            
            "order": [[ 5, "asc" ]], // Sortiere standardmäßig nach Reichweite (5. Spalte)
            "columnDefs": [
                { "orderSequence": [ "asc", "desc" ], "targets": 5 } // Stelle sicher, dass "∞" am Ende ist
            ],
            "language": {
                "processing": "Daten werden verarbeitet...",
                "search": "Suchen:",
                "lengthMenu": "Zeige _MENU_ Einträge",
                "info": "Zeige _START_ bis _END_ von _TOTAL_ Einträgen",
                "infoEmpty": "Zeige 0 bis 0 von 0 Einträgen",
                "infoFiltered": "(gefiltert aus _MAX_ Gesamteinträgen)",
                "infoPostFix": "",
                "loadingRecords": "Daten werden geladen...",
                "zeroRecords": "Keine passenden Einträge gefunden",
                "emptyTable": "Keine Daten in der Tabelle vorhanden",
                "paginate": {
                    "first": "Erste",
                    "previous": "Zurück",
                    "next": "Nächste",
                    "last": "Letzte"
                },
                "aria": {
                    "sortAscending": ": aktivieren, um Spalte aufsteigend zu sortieren",
                    "sortDescending": ": aktivieren, um Spalte absteigend zu sortieren"
                }
            }
        });
    }

    function loadChartData() {
        const view = $('#view').val();
        const han = $('#han-filter').val();
        const timePeriod = $('#chart_time_period').val(); 

        $('#lagerChart').parent().find('#loading').remove();
        $('#lagerChart').parent().append('<div id="loading" style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);display:flex;justify-content:center;align-items:center;font-size:1.2em;">Daten werden geladen...</div>');

        $.ajax({
            url: 'index.php',
            type: 'GET',
            data: { 
                ajax: 'true', 
                view: view,
                han: han,
                time_period: timePeriod 
            },
            dataType: 'json',
            success: function(data) {
                $('#loading').remove();
                
                if (Object.keys(data).length === 0) {
                     $('#lagerChart').parent().append('<div id="no-data" style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);display:flex;justify-content:center;align-items:center;font-size:1.2em;color:red;">FEHLER: Keine Daten für das Diagramm gefunden. (Prüfen Sie SQL-Query / DB-Inhalt)</div>');
                     if (lagerChart) {
                        lagerChart.destroy();
                        lagerChart = null;
                     }
                     return;
                }

                const chartData = { labels: [], datasets: [] };
                let allLabels = new Set();
                let datasetIndex = 0;

                for (const artikelName in data) {
                    data[artikelName].labels.forEach(label => allLabels.add(label));
                }
                
                chartData.labels = Array.from(allLabels).sort();
                
                for (const artikelName in data) {
                    const dataset = data[artikelName];
                    const color = CHART_COLORS[datasetIndex % CHART_COLORS.length];
                    const dataMap = {}; 

                    dataset.labels.forEach((label, i) => { dataMap[label] = dataset.data[i]; });

                    const finalData = chartData.labels.map(label => {
                        return dataMap[label] !== undefined ? dataMap[label] : null; 
                    });

                    chartData.datasets.push({
                        label: artikelName,
                        data: finalData,
                        borderColor: color,
                        backgroundColor: color.replace(')', ', 0.2)'),
                        tension: 0.2, 
                        fill: false 
                    });
                    datasetIndex++;
                }

                updateChart(chartData);
            },
            error: function(xhr, status, error) {
                $('#loading').remove();
                console.error("Fehler beim Laden der Chart-Daten:", status, error);
                $('#lagerChart').parent().append('<div id="ajax-error" style="position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(255,255,255,0.8);display:flex;justify-content:center;align-items:center;font-size:1.2em;color:red;">AJAX FEHLER: Daten konnten nicht vom Server geladen werden. (Status: ' + status + ')</div>');
            }
        });
    }

    function updateChart(data) {
        const ctx = document.getElementById('lagerChart').getContext('2d');
        const view = $('#view').val();
        let xTitle = 'Periode';

        const config = {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    title: { display: true, text: 'Lagerstand (Letzter Stand pro ' + view.charAt(0).toUpperCase() + view.slice(1) + ')' }
                },
                scales: {
                    x: { title: { display: true, text: xTitle } },
                    y: { title: { display: true, text: 'Lagerstand (Menge)' }, beginAtZero: true }
                }
            }
        };

        if (lagerChart) {
            lagerChart.data = data;
            lagerChart.options.plugins.title.text = 'Lagerstand (Letzter Stand pro ' + view.charAt(0).toUpperCase() + view.slice(1) + ')';
            lagerChart.update();
        } else {
            lagerChart = new Chart(ctx, config);
        }
    }
    
    $(document).ready(function() {
        const urlTimePeriod = '<?php echo $time_period; ?>';
        $('#chart_time_period').val(urlTimePeriod);

        initializeDataTables();
        loadChartData();
    });
</script>

</body>
</html>