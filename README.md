# Fega Lagerbestands-Dashboard

Webbasiertes Dashboard zur Analyse von Lagerbestaenden und Verkaufskennzahlen der Eigenprodukte (Polar), mit optionalem Vergleich zu Fremdprodukten.

## Voraussetzungen

- **Webserver** mit PHP 7.0+ (z.B. Apache, nginx)
- **MySQL 5.7+** Datenbank (`crone_log` auf `192.168.10.144`)
- Internetzugang fuer CDN-Bibliotheken (Chart.js, jQuery, DataTables)

## Installation

1. Gesamten Ordner auf den Webserver kopieren (z.B. `/var/www/html/fega/`)
2. DB-Zugangsdaten in `config/db_config.php` pruefen/anpassen
3. Marken-Mapping in `config/app_config.php` bei Bedarf erweitern
4. `index.php` im Browser aufrufen

## Aufbau

Das Dashboard besteht aus **3 Ansichten** (Tabs):

### 1. KPI-Dashboard (Startseite)
Zeigt ausschliesslich **Eigenprodukte (Polar)**:
- Gesamtbestand und Artikelanzahl
- Tagesabgaenge (klickbar: oeffnet Detailtabelle)
- Kritische Produkte mit Reichweite < Lead Time (klickbar: oeffnet Detailtabelle)
- Wochen-Verkaufstrend (aktuelle vs. letzte Woche)
- Top 5 Seller und Top 5 Ladenhueter

### 2. Produktdetail
Drill-down fuer einzelne **Eigenprodukte**:
- Lagerstandsverlauf + taegliche Abgaenge (Combo-Chart)
- Gleitender 7-Tage-Durchschnitt
- Lineare Bestandsprognose
- KPIs: Aktueller Bestand, Reichweite, Avg. Verbrauch/Tag, Verkaufstrend

### 3. Vergleich Eigen/Fremd
Vergleich der Eigenprodukte mit allen Fremdprodukten:
- **Verkaufsindex** (normalisiert, Basis = 100) als Zeitverlauf
- **Marktanteile** (Doughnut-Chart)
- **Abgaenge pro Marke** (Horizontal Bar + Stacked Bar im Zeitverlauf)
- Detailtabelle mit Marktanteilen

## Datenmodell

Die Anwendung liest aus zwei MySQL-Tabellen (Datenbank `crone_log`):

### `lager_teci` (Artikelstammdaten)
| Spalte | Beschreibung |
|--------|-------------|
| `id` | Primaerschluessel |
| `han` | Herstellerartikelnummer |
| `artikelname` | Name des Artikels |
| `url` | Link zum Artikel |
| `kunde` | Lagerort/Kunde |

### `lager_teci_stand` (Bestandshistorie)
| Spalte | Beschreibung |
|--------|-------------|
| `id` | Fremdschluessel auf `lager_teci.id` |
| `datum` | Zeitstempel der Erfassung |
| `lagerstand` | Bestand zu diesem Zeitpunkt |

### Eigenprodukt-Erkennung
Artikel werden als Eigenprodukt erkannt, wenn `artikelname` den String `Polar` enthaelt.

### Verkaufsberechnung
Da keine direkten Verkaufsdaten vorliegen, wird der **Abgang** (Verkauf) aus negativen Lagerstandsdifferenzen berechnet:

```
Abgang(t) = MAX(0, Lagerstand(t-1) - Lagerstand(t))
```

Positive Differenzen (Nachlieferungen) werden ignoriert.

## Projektstruktur

```
Fega-Lagerstand/
|-- index.php                      # Router / Entry Point
|-- config/
|   |-- db_config.php              # Datenbank-Verbindung
|   |-- app_config.php             # Konstanten, Marken-Mapping, Farben
|-- includes/
|   |-- functions.php              # Shared-Funktionen
|   |-- queries/
|       |-- kpi.php                # Datenlogik KPI-Dashboard
|       |-- produktdetail.php      # Datenlogik Produktdetail
|       |-- verkaufsindex.php      # Datenlogik Verkaufsindex
|       |-- kategorien.php         # Datenlogik Marken-Vergleich
|-- api/
|   |-- kpi.php                    # JSON-API: KPI-Daten
|   |-- produktdetail.php          # JSON-API: Produktdetail-Daten
|   |-- verkaufsindex.php          # JSON-API: Verkaufsindex-Daten
|   |-- kategorien.php             # JSON-API: Kategorien-Daten
|-- views/
|   |-- header.php                 # HTML-Kopf, Navigation
|   |-- footer.php                 # HTML-Fuss, Script-Einbindung
|   |-- kpi.php                    # Ansicht: KPI-Dashboard
|   |-- produktdetail.php          # Ansicht: Produktdetail
|   |-- vergleich.php              # Ansicht: Vergleich Eigen/Fremd
|   |-- verkaufsindex.php          # (Alt) Standalone Verkaufsindex
|   |-- kategorien.php             # (Alt) Standalone Kategorien
|-- assets/
|   |-- css/dashboard.css          # Styling
|   |-- js/
|       |-- charts.js              # Chart.js Hilfsfunktionen
|       |-- dashboard.js           # Navigation, AJAX, DataTables
|-- alteDatenansicht/              # Alte Version (Referenz, nicht aktiv)
```

## Architektur

```
Browser  -->  index.php (Router)  -->  views/{page}.php (HTML + JS)
                                           |
                                           | AJAX
                                           v
                                      api/{page}.php (JSON)
                                           |
                                           v
                                   includes/queries/{page}.php (SQL + Logik)
                                           |
                                           v
                                   includes/functions.php (Shared)
                                           |
                                           v
                                   config/db_config.php (MySQL)
```

## API-Endpunkte

Alle Endpunkte geben JSON zurueck und akzeptieren GET-Parameter.

| Endpunkt | Parameter | Beschreibung |
|----------|-----------|-------------|
| `api/kpi.php` | `time_period` | KPI-Daten (nur Eigenprodukte) |
| `api/produktdetail.php` | `han`, `time_period` | Detaildaten fuer einen Artikel |
| `api/verkaufsindex.php` | `time_period`, `aggregation` | Verkaufsindex Eigen vs. Fremd |
| `api/kategorien.php` | `time_period`, `aggregation` | Marken-Vergleich und Marktanteile |

### Parameter

- `time_period`: `1_week`, `2_weeks`, `3_weeks` (Standard), `4_weeks`, `2_months`, `3_months`, `6_months`
- `aggregation`: `day` (Standard), `week`, `month`
- `han`: Herstellerartikelnummer (z.B. `POLAR-12345`)

## Konfiguration

### Datenbank (`config/db_config.php`)
Zugangsdaten fuer die MySQL-Verbindung. Aendern bei anderem Server/Passwort.

### Anwendung (`config/app_config.php`)
- **Lead Times**: Tage bis zur Nachlieferung (Standard: 7 Tage Eigen, 14 Tage Fremd)
- **Marken-Mapping**: Zuordnung Artikelname -> Markenname fuer den Marken-Vergleich
- **Zeitraeume**: Verfuegbare Filter-Optionen
- **Farben**: Farbschema fuer Charts und UI
- **Sicherheitspuffer**: Faktor fuer Warnlimit-Berechnung (Standard: 1.3 = 30%)

## Externe Bibliotheken (via CDN)

- [Chart.js 4.4.1](https://www.chartjs.org/) - Diagramme
- [jQuery 3.7.1](https://jquery.com/) - AJAX und DOM-Manipulation
- [DataTables 2.0.7](https://datatables.net/) - Sortierbare/durchsuchbare Tabellen

## Alte Version

Der Ordner `alteDatenansicht/` enthaelt die urspruengliche monolithische Version (eine einzige `index.php` mit 600 Zeilen). Diese dient nur als Referenz und wird nicht mehr aktiv genutzt.
