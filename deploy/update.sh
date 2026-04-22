#!/bin/bash
# Fega-Lagerstand — Update-Script (analog den anderen RIESTE-Apps)
set -e
cd /opt/fega-lagerstand
git pull

# Opcache-Reset via FPM-Reload. Version-agnostisch: probiert die
# ueblichen PHP-Versionen durch, nimmt den ersten der funktioniert.
for svc in php8.3-fpm php8.2-fpm php8.1-fpm php8.0-fpm php7.4-fpm php-fpm; do
  if systemctl list-unit-files "$svc.service" >/dev/null 2>&1; then
    sudo /bin/systemctl reload "$svc" && break
  fi
done

echo "✓ Fega-Lagerstand aktualisiert"
