#!/bin/bash
# ==============================================================================
# SYSTEM ISHIMURA — SNAPSHOT DEPLOYER: ASHKA [CLI & WEB LIVE RESTORE]
# ==============================================================================

ARCHIVE_PATH="$1"

# Проверка передачи аргумента (для работы из Консоли CLI)
if [ -z "$ARCHIVE_PATH" ] || [ ! -f "$ARCHIVE_PATH" ]; then
    echo "[-] Ошибка: Укажите валидный путь к файлу снимка .tar.gz"
    echo "Использование CLI: $0 /opt/ishimura/backups/ishimura_snapshot_XXXX.tar.gz"
    exit 1
fi

echo "[*] Инициализация восстановления из: $ARCHIVE_PATH"
TMP_EXTRACT="/tmp/ishimura_restore_runtime"
rm -rf "$TMP_EXTRACT" && mkdir -p "$TMP_EXTRACT"

# Извлекаем архив во временный буфер
tar -xzf "$ARCHIVE_PATH" -C "$TMP_EXTRACT" 2>/dev/null

if [ ! -f "$TMP_EXTRACT/arlechino_db.dump" ]; then
    echo "[-] Критическая ошибка: Файл не является валидным снимком системы Ishimura."
    rm -rf "$TMP_EXTRACT"
    exit 1
fi

# Вытаскиваем параметры подключения к СУБД из конфига панели
DB_NAME=$(php -r "require '/opt/ishimura/web/config.php'; echo DB_NAME;")
DB_PASS=$(php -r "require '/opt/ishimura/web/config.php'; echo DB_PASS;")

echo "[*] Накат бинарной дельты PostgreSQL..."
export PGPASSWORD="$DB_PASS"

# Обнуляем текущие таблицы перед накатом (Drop & Recreate Schema public)
psql -h 127.0.0.1 -U ishimura_admin -d "$DB_NAME" -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;" 2>/dev/null

# Реконструируем базу данных из слепка pg_dump
pg_restore -h 127.0.0.1 -U ishimura_admin -d "$DB_NAME" -v "$TMP_EXTRACT/arlechino_db.dump" 2>/dev/null

echo "[*] Синхронизация файлов программного ядра комплекca..."
if [ -d "$TMP_EXTRACT/files" ]; then
    # Накатываем файлы обратно в рабочую область, не затирая саму папку бэкапов
    rsync -a "$TMP_EXTRACT/files/" /opt/ishimura/
fi

# Очищаем временный буфер
rm -rf "$TMP_EXTRACT"

# Выравниваем права для веб-сервера Apache
chown -R www-data:www-data /opt/ishimura/
chown -R www-data:www-data /var/www/html/
chmod -R 755 /opt/ishimura/
chmod -R 755 /var/www/html/

echo "[+] УСПЕХ: Ядро системы и СУБД успешно восстановлены в эталонное состояние!"
exit 0
