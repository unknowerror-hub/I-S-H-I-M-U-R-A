#!/bin/bash
# ==============================================================================
# SYSTEM ISHIMURA — AGENT: ASHKA [FULL BARE-METAL INFRASTRUCTURE SNAPSHOT SUITE]
# ==============================================================================

BACKUP_DIR="/opt/ishimura/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TMP_DIR="/tmp/system_infra_$TIMESTAMP"
FINAL_TAR="$BACKUP_DIR/system_infra_$TIMESTAMP.tar.gz"

mkdir -p "$TMP_DIR/configs"
mkdir -p "$TMP_DIR/core"

# Шаг A: Копируем конфигурационные файлы веб-окружения и СУБД (По ТЗ)
cp -r /etc/apache2 "$TMP_DIR/configs/" 2>/dev/null
cp -r /etc/postgresql "$TMP_DIR/configs/" 2>/dev/null
crontab -l > "$TMP_DIR/configs/system_crontab" 2>/dev/null

# Шаг B: Экспортируем список пакетных зависимостей операционной системы (По ТЗ)
dpkg --get-selections > "$TMP_DIR/configs/apt_packages.list"

# Шаг C: Делаем полный бинарный дамп баз данных PostgreSQL
DB_NAME=$(php -r "require '/opt/ishimura/web/config.php'; echo DB_NAME;")
DB_PASS=$(php -r "require '/opt/ishimura/web/config.php'; echo DB_PASS;")
export PGPASSWORD="$DB_PASS"
pg_dump -h 127.0.0.1 -U ishimura_admin -d "$DB_NAME" -F c -b -v -f "$TMP_DIR/arlechino_full.dump" 2>/dev/null

# Шаг D: Копируем все файлы комплекса /opt/ishimura/ (Исключая папку бэкапов)
rsync -a --exclude='backups' /opt/ishimura/ "$TMP_DIR/core/" 2>/dev/null

# Шаг E: Вшиваем внутрь архива автономный скрипт автоматического "Оживления" (По ТЗ)
cat << 'DEPLOY_EOF' > "$TMP_DIR/deploy.sh"
#!/bin/bash
# ==============================================================================
# AUTOMATED RECOVERY SCRIPT — ISHIMURA INFRASTRUCTURE REVIVAL BARE-METAL
# ==============================================================================
if [ "$EUID" -ne 0 ]; then echo "[-] Запустите скрипт от имени root"; exit 1; fi

echo "[*] Обновление репозиториев и восстановление пакетной базы зависимостей..."
apt-get update -y && apt-get install -y dselect rsync php-cli php-pdo-pgsql postgresql-client apache2 postgresql php 2>/dev/null
dpkg --set-selections < ./configs/apt_packages.list 2>/dev/null
apt-get dselect-upgrade -y 2>/dev/null

echo "[*] Накат эталонных конфигураций Apache и PostgreSQL..."
cp -r ./configs/apache2/* /etc/apache2/ 2>/dev/null
cp -r ./configs/postgresql/* /etc/postgresql/ 2>/dev/null

echo "[*] Развертывание программного ядра комплекса..."
mkdir -p /opt/ishimura
rsync -a ./core/ /opt/ishimura/
rm -rf /var/www/html/* && cp -r /opt/ishimura/web/* /var/www/html/

echo "[*] Реконструкция баз данных Arlechino..."
systemctl start postgresql
DB_NAME=\$(php -r "require '/opt/ishimura/web/config.php'; echo DB_NAME;")
DB_PASS=\$(php -r "require '/opt/ishimura/web/config.php'; echo DB_PASS;")

# Автоматическое создание пользователя и базы данных при развертывании на пустом сервере
sudo -u postgres psql -c "CREATE USER ishimura_admin WITH PASSWORD '\$DB_PASS';" 2>/dev/null
sudo -u postgres psql -c "CREATE DATABASE \$DB_NAME OWNER ishimura_admin;" 2>/dev/null

export PGPASSWORD="\$DB_PASS"
psql -h 127.0.0.1 -U ishimura_admin -d "\$DB_NAME" -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" 2>/dev/null
pg_restore -h 127.0.0.1 -U ishimura_admin -d "\$DB_NAME" -v ./arlechino_full.dump 2>/dev/null

echo "[*] Активация планировщика задач..."
crontab ./configs/system_crontab 2>/dev/null

echo "[*] Выравнивание прав доступа веб-сервера..."
chown -R www-data:www-data /opt/ishimura/ /var/www/html/
chmod -R 755 /opt/ishimura/ /var/www/html/

echo "[+] ОЖИВЛЕНИЕ ЗАВЕРШЕНО. Инициализация перезагрузки инфраструктуры..."
sleep 2 && reboot
DEPLOY_EOF

chmod +x "$TMP_DIR/deploy.sh"

# Шаг F: Упаковка образа в монолитный .tar.gz
tar -czf "$FINAL_TAR" -C "$TMP_DIR" . 2>/dev/null
rm -rf "$TMP_DIR"

# Ротация до 10 файлов (Общий лимит хранилища по ТЗ)
cd "$BACKUP_DIR"
CURRENT_COUNT=$(ls -1 *.tar.gz 2>/dev/null | wc -l)
if [ "$CURRENT_COUNT" -gt 10 ]; then
    ls -1tr *.tar.gz | head -n "$((CURRENT_COUNT - 10))" | xargs rm -f
fi

chown -R www-data:www-data /opt/ishimura/backups/
chmod -R 755 /opt/ishimura/backups/
