# Konfiguration

## Datenbank (`.env` + `config/db_config.php`)

Credentials liegen in der `.env`-Datei im Projekt-Root (nicht in Git).
Template ist `.env.example`. Erwartete Keys:

```
DB_SERVER=192.168.10.144
DB_USERNAME=teci_viewonly
DB_PASSWORD=<aus Passwort-Manager>
DB_NAME=crone_log
```

Der User `teci_viewonly` hat nur Lesezugriff. Daten werden extern durch einen Cronjob in die Datenbank geschrieben.

`config/db_config.php` liest die Werte ueber `includes/env.php` (ein minimaler
dotenv-Loader ohne Composer-Abhaengigkeit) und ruft `load_env(__DIR__ . '/../.env')`.

---

## Anwendung (`config/app_config.php`)

### Lead Times

Tage bis zur Nachlieferung. Wird fuer die Berechnung des Warnstatus (Reichweite < Lead Time) verwendet.

```php
$LEAD_TIMES = [
    'POLAR'   => 7,   // Eigenprodukte: 7 Tage
    'DEFAULT' => 14    // Fremdprodukte: 14 Tage
];
```

Die Zuordnung erfolgt ueber die Herstellerartikelnummer (HAN): Enthaelt der HAN den String `POLAR`, gilt die Eigen-Lead-Time.

### Marken-Mapping

Ordnet Suchbegriffe im Artikelnamen einer Marke zu. Wird fuer die Marken-Vergleichsseite verwendet.

```php
$MARKEN_MAP = [
    'Polar' => 'Polar (Eigen)',
    // Beispiele fuer Erweiterungen:
    // 'Bosch'   => 'Bosch',
    // 'Siemens' => 'Siemens',
    // 'Makita'  => 'Makita',
];
```

**So erweitern Sie das Mapping:**
1. `config/app_config.php` oeffnen
2. Neuen Eintrag in `$MARKEN_MAP` hinzufuegen
3. Key = Suchbegriff im Artikelnamen (Gross-/Kleinschreibung egal)
4. Value = Anzeigename der Marke

Nicht zugeordnete Artikel werden als `$DEFAULT_MARKE` ("Sonstige (Fremd)") klassifiziert.

### Zeitraeume

Verfuegbare Filter-Optionen fuer das Zeitraum-Dropdown:

```php
$TIME_PERIODS = [
    '1_week'   => ['label' => 'Letzte Woche',      'days' => 7],
    '2_weeks'  => ['label' => 'Letzte 2 Wochen',    'days' => 14],
    '3_weeks'  => ['label' => 'Letzte 3 Wochen',    'days' => 21],   // Standard
    '4_weeks'  => ['label' => 'Letzte 4 Wochen',    'days' => 28],
    '2_months' => ['label' => 'Letzte 2 Monate',    'days' => 60],
    '3_months' => ['label' => 'Letzte 3 Monate',    'days' => 90],
    '6_months' => ['label' => 'Letzte 6 Monate',    'days' => 180],
];
```

Neue Zeitraeume koennen einfach hinzugefuegt werden. Der Standard-Zeitraum ist `3_weeks`.

### Farbschema

```php
$FARBEN = [
    'eigen'    => '#2196F3',  // Blau
    'fremd'    => '#FF9800',  // Orange
    'kritisch' => '#F44336',  // Rot
    'warnung'  => '#FFC107',  // Gelb
    'ok'       => '#4CAF50',  // Gruen
    'neutral'  => '#607D8B',  // Grau-Blau
];
```

### Sicherheitspuffer

Faktor fuer die Warnlimit-Berechnung (aktuell nicht aktiv in der neuen Version, reserviert fuer kuenftige Verwendung):

```php
$SAFETY_BUFFER = 1.3; // 30% Puffer
```

---

## Haeufige Anpassungen

### Neue Marke hinzufuegen
`config/app_config.php` -> `$MARKEN_MAP` erweitern.

### Lead Time aendern
`config/app_config.php` -> `$LEAD_TIMES` anpassen.

### Neuen Zeitraum hinzufuegen
`config/app_config.php` -> `$TIME_PERIODS` erweitern. Das Dropdown aktualisiert sich automatisch.

### Datenbank-Server wechseln
`config/db_config.php` -> `DB_SERVER`, `DB_USERNAME`, `DB_PASSWORD` anpassen.

### Eigenprodukt-Erkennung aendern
Aktuell basiert die Erkennung auf `artikelname LIKE '%Polar%'`. Um dies zu aendern:
1. `includes/functions.php` -> `is_eigenprodukt()` anpassen
2. `includes/queries/kpi.php` -> `EIGEN_FILTER_SQL` Konstante anpassen
3. `includes/queries/produktdetail.php` -> `get_artikel_liste()` SQL-Filter anpassen
