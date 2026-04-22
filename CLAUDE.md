# CLAUDE.md

## Projekt

Fega Lagerbestands-Dashboard — PHP-Webanwendung zur Analyse von Lagerbestaenden und Verkaufskennzahlen der Eigenprodukte (Polar) mit Vergleich zu Fremdprodukten.

## Tech-Stack

- **Backend:** PHP 7.0+ (kein Framework, kein Composer)
- **Frontend:** Chart.js 4.x, jQuery 3.x, DataTables 2.x (alle via CDN)
- **Datenbank:** MySQL 5.7 (kein LAG/Window Functions, Abgaenge via Self-Join in PHP)
- **Server:** Apache auf `rieste.org` (Pfad: `/var/www/html/rieste.org/2023_tec_de/fega/`)

## Wichtige Konventionen

- **Eigenprodukte** = Artikelname enthaelt "Polar" (`LIKE '%Polar%'`). Es gibt kein DB-Flag dafuer.
- **JOIN-Bedingung:** Immer `t1.id = t2.id` (NICHT `t2.lager` — das war ein Fehler in der alten `api_data.php`)
- **Kein `fn()` Arrow Functions** — Server laeuft auf PHP < 7.4. Stattdessen `function($x) use ($y) { return ...; }` verwenden.
- **Kein `LAG()`** — MySQL 5.7 hat keine Window Functions. Abgangsberechnung erfolgt in PHP ueber `calculate_abgaenge_from_records()`.
- **Dashboard zeigt nur Eigenprodukte.** Fremdprodukte erscheinen nur auf der Vergleichsseite (`?page=vergleich`).

## Architektur

```
index.php (Router)
  -> views/header.php (Navigation)
  -> views/{page}.php (HTML + inline JS)
  -> views/footer.php

AJAX-Calls gehen an:
  api/{page}.php -> includes/queries/{page}.php -> includes/functions.php -> config/db_config.php
```

- **Config:** `config/db_config.php`, `config/app_config.php`
- **Shared Functions:** `includes/functions.php` — zentrale Abgangsberechnung (`get_daily_abgaenge()`), Trend, Prognose
- **Query-Module:** `includes/queries/{kpi,produktdetail,verkaufsindex,kategorien}.php`
- **API-Endpunkte:** `api/{kpi,produktdetail,verkaufsindex,kategorien}.php` (JSON)
- **Views:** `views/{kpi,produktdetail,vergleich}.php` (3 Tabs)

## Befehle

- Kein Build-Prozess, kein Paketmanager
- Dateien direkt auf den Webserver kopieren
- Testen: `index.php` im Browser aufrufen, API-Endpunkte direkt testen (`api/kpi.php?time_period=3_weeks`)

## Datenbank

- Server: `192.168.10.144`, DB: `crone_log`, User: `teci_viewonly` (nur Lesezugriff)
- Tabellen: `lager_teci` (Artikelstamm), `lager_teci_stand` (Bestandshistorie)
- Daten werden extern durch einen Cronjob befuellt

## Haeufige Stolperfallen

- Weisse Seite = PHP Parse Error. `error_reporting(E_ALL); ini_set('display_errors', 1);` steht in `index.php`.
- Keine Daten = Wahrscheinlich falsche JOIN-Bedingung oder DB nicht erreichbar.
- Marken-Vergleich zeigt nur "Sonstige" = `$MARKEN_MAP` in `config/app_config.php` muss erweitert werden.
