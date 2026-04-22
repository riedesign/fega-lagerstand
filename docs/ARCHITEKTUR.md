# Architektur-Dokumentation

## Uebersicht

Das Dashboard folgt einer schichtbasierten Architektur mit klarer Trennung von Konfiguration, Datenlogik, API und Darstellung.

## Schichten

### 1. Konfiguration (`config/`)

| Datei | Zweck |
|-------|-------|
| `db_config.php` | MySQL-Verbindung, `execute_query()` Hilfsfunktion |
| `app_config.php` | Geschaeftslogik-Konstanten: Lead Times, Marken-Mapping, Zeitraeume, Farben |

Die Konfiguration wird von `index.php` einmalig geladen und steht allen Schichten zur Verfuegung.

### 2. Shared Functions (`includes/functions.php`)

Zentrale Funktionsbibliothek, die von allen Ansichten genutzt wird:

| Funktion | Beschreibung |
|----------|-------------|
| `get_days_for_period($time_period)` | Zeitraum-String in Tage umrechnen |
| `get_lead_time_days($han)` | Lead Time fuer einen Artikel (7 Tage Polar, 14 Tage Fremd) |
| `extract_marke($artikelname)` | Marke aus Artikelname extrahieren (via Mapping) |
| `is_eigenprodukt($artikelname)` | Prueft ob Artikel ein Eigenprodukt ist |
| `get_daily_stock($conn, $days, $filter_sql)` | Tagesend-Bestaende pro Artikel aus DB holen |
| `get_daily_abgaenge($conn, $days, $filter_sql)` | Taegliche Abgaenge berechnen (Kern-Baustein) |
| `calculate_abgaenge_from_records($records)` | Abgaenge aus Lagerstandsdaten ableiten |
| `calculate_prognose($stock, $avg, $days)` | Lineare Bestandsprognose |
| `calculate_trend($data_points)` | Lineare Regression fuer Trendrichtung |
| `normalize_to_index($data_series)` | Datenreihe auf Index normalisieren (Basis=100) |
| `get_period_key($date, $aggregation)` | Datum in Perioden-Key (Tag/Woche/Monat) |
| `render_time_period_options($selected)` | HTML-Options fuer Zeitraum-Dropdown |

### 3. Query-Module (`includes/queries/`)

Jede Ansicht hat ein eigenes Query-Modul mit spezifischer Geschaeftslogik:

| Modul | Hauptfunktion | Beschreibung |
|-------|--------------|-------------|
| `kpi.php` | `get_kpi_overview()` | Gesamtbestand, Abgaenge, Topseller, Ladenhueter, Trend (nur Eigen) |
| `produktdetail.php` | `get_produkt_detail()` | Verlauf, Abgaenge, Prognose, KPIs fuer einen Artikel |
| `verkaufsindex.php` | `get_verkaufsindex_data()` | Aggregierter Verkaufsindex Eigen vs. Fremd |
| `kategorien.php` | `get_marken_vergleich()` | Marktanteile und Abgangs-Vergleich pro Marke |

### 4. API-Endpunkte (`api/`)

Jeder Endpunkt:
1. Setzt `Content-Type: application/json`
2. Laedt Konfiguration und Shared Functions
3. Laedt das zugehoerige Query-Modul
4. Validiert GET-Parameter
5. Ruft die Hauptfunktion auf
6. Gibt JSON zurueck

Die API-Endpunkte enthalten **keine Geschaeftslogik** — sie sind reine Adapter.

### 5. Views (`views/`)

Jede View:
1. Wird von `index.php` per `include` geladen (Zugriff auf `$conn`, `$time_period`, etc.)
2. Laedt ggf. ihr Query-Modul fuer serverseitige Daten
3. Gibt HTML aus mit eingebettetem JavaScript
4. Das JavaScript laedt weitere Daten via AJAX von den API-Endpunkten

## Datenfluss

### Seitenaufruf (serverseitig)
```
Browser GET index.php?page=kpi
    -> index.php laedt config + functions
    -> include views/header.php (Navigation)
    -> include views/kpi.php
        -> require queries/kpi.php
        -> get_kpi_overview() -> get_daily_abgaenge() -> get_daily_stock() -> SQL
        -> PHP rendert HTML mit Daten
    -> include views/footer.php
```

### AJAX-Update (clientseitig)
```
User aendert Filter
    -> JavaScript ruft api/kpi.php?time_period=4_weeks
    -> api/kpi.php -> queries/kpi.php -> SQL -> JSON Response
    -> JavaScript aktualisiert Charts/Tabellen
```

## Kern-Algorithmus: Abgangsberechnung

Da keine direkten Verkaufsdaten vorliegen, werden Abgaenge aus Lagerstandsdifferenzen abgeleitet.

### Schritt 1: Tagesend-Bestaende (`get_daily_stock`)
```sql
SELECT t1.id, t1.han, t1.artikelname, DATE(t2.datum) AS tag,
       SUBSTRING_INDEX(GROUP_CONCAT(t2.lagerstand ORDER BY t2.datum DESC), ',', 1) + 0 AS lagerstand_end
FROM lager_teci t1
JOIN lager_teci_stand t2 ON t1.id = t2.id
WHERE t2.datum >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
GROUP BY t1.id, t1.han, t1.artikelname, DATE(t2.datum)
```

Pro Artikel und Tag wird der **letzte** Lagerstand des Tages ermittelt (via `GROUP_CONCAT` + `SUBSTRING_INDEX`, MySQL 5.7 kompatibel).

### Schritt 2: Differenzen (`calculate_abgaenge_from_records`)
```
Fuer jeden Tag t > t0:
    Abgang(t) = MAX(0, Lagerstand(t-1) - Lagerstand(t))
```

- Positive Differenzen (Nachlieferungen) werden mit `MAX(0, ...)` herausgefiltert
- Die Berechnung erfolgt in PHP statt SQL, da MySQL 5.7 keine `LAG()` Window-Funktion hat

### Einschraenkungen
- An Tagen mit Nachlieferung + Verkauf wird der Verkauf unterschaetzt
- Bestandskorrekturen koennen als Verkauf fehlinterpretiert werden
- Fehlende Tage fuehren zu Luecken in der Berechnung

## Verkaufsindex

Fuer den Vergleich Eigen vs. Fremd wird ein normalisierter Index berechnet:

```
Index(t) = ( Summe_Abgaenge(t) / Summe_Abgaenge(t0) ) * 100
```

Der erste Datenpunkt hat immer den Wert 100 (Basiswert). Das macht die beiden Gruppen vergleichbar, auch wenn die absoluten Stueckzahlen stark unterschiedlich sind.

## Eigenprodukt-Erkennung

Artikel werden als Eigenprodukt erkannt per:
```php
stripos($artikelname, 'Polar') !== false
```

Fuer SQL-Filter:
```sql
WHERE t1.artikelname LIKE '%Polar%'
```

Es gibt kein explizites Flag in der Datenbank. Die Erkennung basiert auf der Namenskonvention.

## Marken-Zuordnung

Da keine Marken-Spalte in der Datenbank existiert, werden Marken ueber ein konfigurierbares Mapping in `config/app_config.php` erkannt:

```php
$MARKEN_MAP = [
    'Polar' => 'Polar (Eigen)',
    // weitere Marken hier ergaenzen
];
```

Nicht zugeordnete Artikel werden als "Sonstige (Fremd)" klassifiziert.
