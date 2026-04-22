# API-Dokumentation

Alle Endpunkte befinden sich im Ordner `api/` und geben JSON zurueck.

## Gemeinsame Parameter

| Parameter | Werte | Standard | Beschreibung |
|-----------|-------|----------|-------------|
| `time_period` | `1_week`, `2_weeks`, `3_weeks`, `4_weeks`, `2_months`, `3_months`, `6_months` | `3_weeks` | Betrachtungszeitraum |
| `aggregation` | `day`, `week`, `month` | `day` | Zeitliche Aggregation |

---

## GET `api/kpi.php`

KPI-Daten fuer das Dashboard (nur Eigenprodukte).

### Parameter
| Parameter | Pflicht | Beschreibung |
|-----------|---------|-------------|
| `time_period` | Nein | Zeitraum |

### Response
```json
{
    "gesamt_bestand": 1250,
    "artikel_count": 15,
    "abgang_heute": 42,
    "abgang_gestern": 38,
    "kritische_count": 3,
    "kritische_artikel": [
        {
            "han": "POLAR-001",
            "artikelname": "Polar Produkt A",
            "lagerstand": 5,
            "reichweite": 3,
            "lead_time": 7
        }
    ],
    "topseller": [
        {
            "han": "POLAR-002",
            "artikelname": "Polar Produkt B",
            "total_abgang": 120,
            "avg_daily": 5.7
        }
    ],
    "ladenhueter": [
        {
            "han": "POLAR-003",
            "artikelname": "Polar Produkt C",
            "lagerstand": 85
        }
    ],
    "trend_pct": 12.5,
    "trend_direction": "steigend",
    "abgang_aktuelle_woche": 180,
    "abgang_letzte_woche": 160
}
```

### Felder
| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `gesamt_bestand` | int | Summe aller aktuellen Lagerbestaende (Eigenprodukte) |
| `artikel_count` | int | Anzahl Eigenprodukte |
| `abgang_heute` | int | Summe Abgaenge am heutigen Tag |
| `abgang_gestern` | int | Summe Abgaenge am gestrigen Tag |
| `kritische_count` | int | Anzahl Artikel mit Reichweite < Lead Time |
| `kritische_artikel` | array | Top 10 kritischste Artikel (sortiert nach Reichweite aufsteigend) |
| `topseller` | array | Top 5 nach Gesamtabgang (absteigend) |
| `ladenhueter` | array | Top 5 mit Bestand > 0 aber Abgang = 0 |
| `trend_pct` | float | Prozentuale Veraenderung aktuelle vs. letzte Woche |
| `trend_direction` | string | `steigend`, `fallend` oder `stabil` |

---

## GET `api/produktdetail.php`

Detaildaten fuer einen einzelnen Artikel.

### Parameter
| Parameter | Pflicht | Beschreibung |
|-----------|---------|-------------|
| `han` | Ja | Herstellerartikelnummer |
| `time_period` | Nein | Zeitraum |

### Response (Erfolg)
```json
{
    "han": "POLAR-001",
    "artikelname": "Polar Produkt A",
    "is_eigen": true,
    "current_stock": 45,
    "lead_time": 7,
    "reichweite": 15,
    "avg_daily": 3.0,
    "avg_active_days": 4.5,
    "total_abgang": 63,
    "trend": { "slope": -0.3, "direction": "stabil" },
    "last_abgang": "2026-03-22",
    "lager_labels": ["2026-03-01", "2026-03-02", "..."],
    "lager_values": [80, 78, "..."],
    "abgang_labels": ["2026-03-02", "2026-03-03", "..."],
    "abgang_values": [2, 0, "..."],
    "moving_avg": [2.0, 1.0, "..."],
    "prognose_labels": ["2026-03-24", "2026-03-25", "..."],
    "prognose_values": [45, 42, "..."],
    "status": "ok"
}
```

### Response (Fehler)
```json
{ "error": "Keine Daten fuer diesen Artikel gefunden." }
```

### Felder
| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `reichweite` | int/string | Tage bis Bestand aufgebraucht, oder `"unbegrenzt"` |
| `avg_daily` | float | Durchschnittlicher Tagesverbrauch ueber gesamten Zeitraum |
| `avg_active_days` | float | Durchschnitt nur an Tagen mit Abgang > 0 |
| `moving_avg` | array | Gleitender 7-Tage-Durchschnitt der Abgaenge |
| `prognose_labels/values` | array | Lineare Bestandsprognose (max. 30 Tage in die Zukunft) |
| `status` | string | `ok`, `critical` (Reichweite < Lead Time) oder `obsolete` (Ladenhueter) |

---

## GET `api/verkaufsindex.php`

Verkaufsindex Eigen vs. Fremd (alle Produkte).

### Parameter
| Parameter | Pflicht | Beschreibung |
|-----------|---------|-------------|
| `time_period` | Nein | Zeitraum |
| `aggregation` | Nein | Aggregation (day/week/month) |

### Response
```json
{
    "labels": ["2026-03-01", "2026-03-02", "..."],
    "eigen_index": [100, 105.3, "..."],
    "fremd_index": [100, 98.7, "..."],
    "eigen_absolut": [50, 53, "..."],
    "fremd_absolut": [120, 118, "..."],
    "eigen_total": 800,
    "fremd_total": 2100,
    "eigen_anteil": 27.6,
    "fremd_anteil": 72.4
}
```

### Felder
| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `*_index` | array | Normalisierter Index (Basis = 100 am ersten Datenpunkt) |
| `*_absolut` | array | Absolute Abgaenge pro Periode |
| `*_total` | int | Gesamtsumme Abgaenge im Zeitraum |
| `*_anteil` | float | Prozentualer Anteil am Gesamtabgang |

---

## GET `api/kategorien.php`

Marken-Vergleich und Marktanteile (alle Produkte).

### Parameter
| Parameter | Pflicht | Beschreibung |
|-----------|---------|-------------|
| `time_period` | Nein | Zeitraum |
| `aggregation` | Nein | Aggregation (day/week/month) |

### Response
```json
{
    "marktanteile": [
        { "marke": "Polar (Eigen)", "abgang": 800, "anteil": 27.6, "artikel": 15 },
        { "marke": "Sonstige (Fremd)", "abgang": 2100, "anteil": 72.4, "artikel": 42 }
    ],
    "period_labels": ["2026-03-01", "2026-03-02", "..."],
    "zeitverlauf": [
        { "marke": "Polar (Eigen)", "values": [50, 53, "..."] },
        { "marke": "Sonstige (Fremd)", "values": [120, 118, "..."] }
    ],
    "gesamt_total": 2900
}
```

### Felder
| Feld | Typ | Beschreibung |
|------|-----|-------------|
| `marktanteile` | array | Pro Marke: Abgang, Anteil (%), Anzahl Artikel |
| `zeitverlauf` | array | Abgaenge pro Marke und Periode (fuer Stacked Chart) |
| `gesamt_total` | int | Gesamtabgang aller Marken |
