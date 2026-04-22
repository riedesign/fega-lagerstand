#!/bin/bash
# Erstinstallation Fega-Lagerstand auf 192.168.10.25
# Einmalig ausfuehren. Danach Updates via deploy/update.sh.
set -e

TARGET=/opt/fega-lagerstand

# 1. PHP-FPM + MySQL-Client (falls noch nicht installiert)
if ! command -v php-fpm8.1 >/dev/null 2>&1 && ! command -v php-fpm >/dev/null 2>&1; then
  echo "==> PHP-FPM installieren..."
  sudo apt update
  sudo apt install -y php-fpm php-mysqli php-cli
else
  echo "==> PHP-FPM bereits installiert"
fi

# 2. Repo klonen falls noch nicht vorhanden
if [ ! -d "$TARGET/.git" ]; then
  echo "==> Repo klonen nach $TARGET..."
  sudo mkdir -p "$TARGET"
  sudo chown "$USER":"$USER" "$TARGET"
  git clone https://github.com/riedesign/fega-lagerstand.git "$TARGET"
else
  echo "==> Repo schon vorhanden, ueberspringe clone"
fi

# 3. .env anlegen falls nicht vorhanden (aus .env.example)
if [ ! -f "$TARGET/.env" ]; then
  echo "==> .env aus Template erstellen"
  cp "$TARGET/.env.example" "$TARGET/.env"
  # www-data (PHP-FPM) muss lesen duerfen, sonst kann PHP die ENV nicht
  # laden und JWT-Validation schlaegt still fehl.
  sudo chgrp www-data "$TARGET/.env"
  chmod 640 "$TARGET/.env"
  echo "!! BITTE .env jetzt manuell editieren und DB_PASSWORD + JWT_SECRET_KEY setzen:"
  echo "   nano $TARGET/.env"
fi

# 4. nginx-vhost verlinken
if [ ! -L /etc/nginx/sites-enabled/fega.rieste.org ]; then
  echo "==> nginx-vhost verlinken..."
  sudo cp "$TARGET/deploy/nginx-fega.conf" /etc/nginx/sites-available/fega.rieste.org
  sudo ln -sf /etc/nginx/sites-available/fega.rieste.org /etc/nginx/sites-enabled/fega.rieste.org
  sudo nginx -t && sudo systemctl reload nginx
fi

# 5. sudoers-Eintrag fuer passwortlosen FPM-Reload (alle gaengigen Versionen)
SUDOERS=/etc/sudoers.d/fega-lagerstand-reload
if [ ! -f "$SUDOERS" ]; then
  echo "==> sudoers-Eintrag anlegen..."
  echo "$USER ALL=(root) NOPASSWD: /bin/systemctl reload php8.3-fpm, /bin/systemctl reload php8.2-fpm, /bin/systemctl reload php8.1-fpm, /bin/systemctl reload php-fpm" \
    | sudo tee "$SUDOERS" > /dev/null
  sudo chmod 0440 "$SUDOERS"
fi

# 6. Owner setzen + Berechtigungen
sudo chown -R "$USER":"$USER" "$TARGET"
# nginx/www-data muss zumindest lesen duerfen
sudo chmod -R g+rX "$TARGET"

echo ""
echo "==> Setup fertig."
echo ""
echo "Naechste Schritte:"
echo "  1. .env vervollstaendigen: nano $TARGET/.env"
echo "     - DB_PASSWORD (aus Altinstallation uebernehmen)"
echo "     - JWT_SECRET_KEY (identisch zum Rieste-Auth-Portal)"
echo "     - AUTH_APP_SLUG=fega"
echo "  2. DNS: fega.rieste.org → 192.168.10.25 (intern)"
echo "     Oder public: A-Record setzen + Reverse-Proxy vor 192.168.10.25"
echo "  3. Im Auth-Portal App 'fega' anlegen + User-Freigaben"
echo "  4. Test: curl -I http://fega.rieste.org"
echo "  5. Ab jetzt Updates via: $TARGET/deploy/update.sh"
