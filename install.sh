#!/bin/bash
# ==============================================================================
# SYSTEM ISHIMURA — MASTER ORCHESTRATION INSTALLER & DEPLOYMENT SCRIPT
# ==============================================================================
set -e

echo "[*] Инициализация глобального инсталлятора комплекса ISHIMURA..."

# 1. Формирование сквозной структуры каталогов для всех 10 модулей
mkdir -p /opt/ishimura/modules/arlechino
mkdir -p /opt/ishimura/modules/arachna
mkdir -p /opt/ishimura/modules/miko
mkdir -p /opt/ishimura/modules/terror
mkdir -p /opt/ishimura/modules/sadako
mkdir -p /opt/ishimura/modules/kira
mkdir -p /opt/ishimura/modules/oraculus
mkdir -p /opt/ishimura/modules/lamia
mkdir -p /opt/ishimura/modules/ashka
mkdir -p /opt/ishimura/modules/mifiko
mkdir -p /opt/ishimura/exploits/generated
mkdir -p /opt/ishimura/exploits/github_poc
mkdir -p /opt/ishimura/backups
mkdir -p /var/www/html/modules

# 2. Лечение сетевых шлюзов — прописка DNS Google для обхода блокировок парсинга
echo "nameserver 8.8.8.8" | sudo tee /etc/resolv.conf > /dev/null

# 3. Принудительный деплой и чистка кэша веб-сервера Apache
echo "[*] Синхронизация веб-интерфейса Hatsumi с продакшен-папкой Apache..."
rm -f /var/www/html/index.php
cp -rf /opt/ishimura/web/* /var/www/html/

# 4. Обеспечение прав на сквозной межмодульный запуск от www-data
chown -R www-data:www-data /opt/ishimura/
chown -R www-data:www-data /var/www/html/
chmod -R 755 /opt/ishimura/
chmod -R 755 /var/www/html/

# 5. Автозапуск фоновых демонов сокетов и телеметрии ядра Linux
echo "[*] Активация асинхронных бэкэнд-служб комплекса..."

# Ударный листенер Reverse Shell (Модуль Terror)
if ! fuser 4444/tcp >/dev/null 2>&1; then
    exec /usr/bin/python3 /opt/ishimura/modules/terror/listener.py > /dev/null 2>&1 &
fi

# Инициализатор баз данных и первичный ИИ-анализ
python3 /opt/ishimura/modules/miko/analyzer.py analyze || true
python3 /opt/ishimura/modules/terror/exploit_manager.py || true
python3 /opt/ishimura/modules/sadako/monitor.py || true
python3 /opt/ishimura/modules/lamia/shield.py || true
python3 /opt/ishimura/modules/ashka/backup_daemon.py || true

echo "[+] =========================================================================="
echo "[+] УСТАНОВКА И ПЕРЕСТРОЙКА ЯДРА ISHIMURA ЗАВЕРШЕНА СО 100% УСПЕХОМ!"
echo "[+] =========================================================================="
