# Changelog

## v2.0.0 (2026-03-24) - Neues Dashboard

Komplette Neuentwicklung des Lagerbestands-Dashboards mit modularer Architektur.

### Neue Features
- **KPI-Dashboard** (Startseite): Gesamtbestand, Tagesabgaenge, kritische Produkte, Wochen-Trend, Top 5 Seller/Ladenhueter
- **Produktdetail**: Drill-down pro Artikel mit Combo-Chart (Lagerstand + Abgaenge), gleitendem 7-Tage-Durchschnitt, linearer Bestandsprognose
- **Vergleich Eigen/Fremd**: Normalisierter Verkaufsindex, Marktanteile (Doughnut), Marken-Vergleich (Stacked Bar)
- **Klickbare KPI-Kacheln**: Abgang-Detail und kritische Produkte oeffnen sich per Klick
- **Globaler Zeitraum-Filter**: 7 Optionen von 1 Woche bis 6 Monate
- **Aggregation**: Tag, Woche oder Monat

### Architektur
- Modulare Dateistruktur: Config, Includes, Queries, API, Views, Assets
- JSON-APIs fuer alle Ansichten (AJAX-basiert)
- Saubere Trennung von Datenlogik und Darstellung
- Responsive Design (CSS Grid/Flexbox)

### Fokus auf Eigenprodukte
- KPI-Dashboard und Produktdetail zeigen ausschliesslich Eigenprodukte (Polar)
- Vergleichsseite stellt Eigen- und Fremdprodukte gegenueber
- Kritische-Artikel-Warnung nur fuer Eigenprodukte

### Technische Verbesserungen
- MySQL 5.7 kompatibel (kein LAG(), Self-Joins statt Window Functions)
- Konsistente JOIN-Bedingung (`t1.id = t2.id`)
- Konfigurierbares Marken-Mapping
- Zentralisierte Abgangsberechnung als wiederverwendbarer Baustein

---

## v1.0.0 - Alte Datenansicht

Monolithisches Dashboard in `alteDatenansicht/` (3 Dateien):
- `index.php`: Lagerstandsverlauf (Chart.js) + Reichweiten-Tabelle (DataTables)
- `api_data.php`: Separater JSON-Endpunkt mit Topseller-Modus
- `db_config.php`: Datenbank-Verbindung

### Einschraenkungen der alten Version
- Alles in einer Datei (600+ Zeilen PHP/HTML/JS)
- Kein Verkaufsindex, kein Marken-Vergleich
- Keine Prognose-Funktion
- Inkonsistente JOIN-Bedingung zwischen index.php und api_data.php
- Keine Trennung zwischen Eigen- und Fremdprodukten auf Dashboard-Ebene
