#!/bin/bash
# Fega-Lagerstand — Update-Script (analog den anderen RIESTE-Apps)
set -e
cd /opt/fega-lagerstand
git pull

# Opcache-Reset falls aktiv (vermeidet stale cached Bytecode).
# PHP-FPM-Reload genuegt fuer opcache.revalidate_freq=0 nicht bei
# allen Setups — deshalb explizit reloaden.
sudo /bin/systemctl reload php8.1-fpm 2>/dev/null \
  || sudo /bin/systemctl reload php-fpm 2>/dev/null \
  || true

echo "✓ Fega-Lagerstand aktualisiert"
