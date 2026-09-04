#!/bin/bash
# ==============================================================================
# SYSTEM ISHIMURA — DEMON: ASHKA [FULL SNAPSHOT & ROTATION ENGINE]
# ==============================================================================

BACKUP_DIR="/opt/ishimura/backups"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
TMP_DIR="/tmp/ishimura_bk_$TIMESTAMP"
FINAL_TAR="$BACKUP_DIR/ishimura_snapshot_$TIMESTAMP.tar.gz"

mkdir -p "$TMP_DIR"

# Вытаскиваем параметры СУБД из конфига веб-панели
DB_NAME=$(php -r "require '/opt/ishimura/web/config.php'; echo DB_NAME;")
DB_PASS=$(php -r "require '/opt/ishimura/web/config.php'; echo DB_PASS;")

# Шаг A: Делаем полный SQL-дамп базы данных Arlechino (По ТЗ)
export PGPASSWORD="$DB_PASS"
pg_dump -h 127.0.0.1 -U ishimura_admin -d "$DB_NAME" -F c -b -v -f "$TMP_DIR/arlechino_db.dump" 2>/dev/null

# Шаг B: Копируем файлы всего комплекса /opt/ishimura/ (Исключая саму папку бэкапов)
mkdir -p "$TMP_DIR/files"
rsync -a --exclude='backups' /opt/ishimura/ "$TMP_DIR/files/" 2>/dev/null

# Шаг C: Упаковываем всё в один монолитный сжатый .tar.gz архив
tar -czf "$FINAL_TAR" -C "$TMP_DIR" . 2>/dev/null

# Удаляем временную рабочую папку
rm -rf "$TMP_DIR"

# Шаг D: ЖЕСТКАЯ РОТАЦИЯ — ОГРАНИЧЕНИЕ ДО 10 АРХИВОВ (ПО ТЗ)
# Считаем количество архивов, сортируем по дате, удаляем те, что выходят за лимит 10
cd "$BACKUP_DIR"
CURRENT_COUNT=$(ls -1 ishimura_snapshot_*.tar.gz 2>/dev/null | wc -l)

if [ "$CURRENT_COUNT" -gt 10 ]; then
    # Находим самые старые файлы, превышающие лимит, и удаляем их
    ls -1tr ishimura_snapshot_*.tar.gz | head -n "$((CURRENT_COUNT - 10))" | xargs rm -f
fi

# Прописываем права, чтобы веб-панель могла читать и скачивать файлы
chown -R www-data:www-data /opt/ishimura/backups/
chmod -R 755 /opt/ishimura/backups/
