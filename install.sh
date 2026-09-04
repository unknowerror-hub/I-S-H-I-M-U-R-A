#!/bin/bash
# ==============================================================================
# SYSTEM ISHIMURA — PRODUCTION INDEPENDENT 1-CLICK INSTALLER
# ==============================================================================
# Целевая ОС: Чистая Ubuntu 24 (с последующим снятием готового OVA/ISO образа)
# Запуск: sudo bash install.sh
# ==============================================================================

# Проверка прав root
if [ "$EUID" -ne 0 ]; then
  echo -e "\033[91m[-] Ошибка: Запустите установщик от имени суперпользователя (sudo).\033[0m"
  exit 1
fi

echo -e "\033[96m[+] ИНИЦИАЛИЗАЦИЯ УСТАНОВКИ ВЕКТОРНОГО КОМПЛЕКСА ISHIMURA...\033[0m"

# ШАГ 1: Запрос и настройка критических паролей
read -s -p "[ВВОД] Задайте пароль администратора к веб-интерфейсу Hatsumi: " WEB_ADMIN_PASS
echo ""
read -s -p "[ВВОД] Задайте мастер-пароль к базе данных PostgreSQL (Arlechino): " DB_MASTER_PASS
echo ""

# Экспортируем пароль БД в переменные окружения для скрипта инициализации
export ISHIMURA_DB_PASSWORD="$DB_MASTER_PASS"

# ШАГ 2: Обновление системы и установка зависимостей продакшена (БЕЗ DOCKER)
echo "[*] Обновление пакетного менеджера и развертывание веб-компонентов..."
apt-get update -y && apt-get upgrade -y
apt-get install -y postgresql postgresql-contrib apache2 php libapache2-mod-php php-pgsql python3 python3-pip python3-psycopg2 gcc make ufw

# ШАГ 3: Компиляция бинарных низкоуровневых С-модулей ядра
echo "[*] Компиляция RAW-движков сетевой карты (Arachna и Lamia)..."
gcc -O3 /opt/ishimura/modules/arachna/scanner.c -o /opt/ishimura/modules/arachna/scanner
gcc -O2 /opt/ishimura/modules/lamia/lfilter.c -o /opt/ishimura/modules/lamia/lfilter

# ШАГ 4: Запуск демона базы данных Arlechino для генерации таблиц и токенов
echo "[*] Конфигурирование локального экземпляра PostgreSQL и табличной структуры..."
systemctl start postgresql
systemctl enable postgresql

# Настройка пароля пользователя postgres в СУБД
sudo -u postgres psql -c "ALTER USER postgres WITH PASSWORD '$DB_MASTER_PASS';"

# Вызов Python скрипта инициализации структуры таблиц всех модулей
python3 /opt/ishimura/modules/arlechino/service.py

# ШАГ 5: Развертывание и шифрование веб-интерфейса Hatsumi
echo "[*] Перенос киберпанк веб-интерфейса в рабочую директорию веб-сервера..."
# Модифицируем глобальный конфигурационный файл, записывая туда введенный пароль БД
sed -i "s/ishimura_default_pass/$DB_MASTER_PASS/g" /opt/ishimura/web/config.php

# Копируем веб-файлы в директорию apache
rm -rf /var/www/html/*
cp -r /opt/ishimura/web/* /var/www/html/
chown -R www-data:www-data /var/www/html/
chmod -R 755 /var/www/html/

# Внесение захэшированного пароля веб-интерфейса в созданную СУБД (Обновление admin-пароля)
WEB_HASH=$(php -r "echo password_hash('$WEB_ADMIN_PASS', PASSWORD_BCRYPT);")
sudo -u postgres psql -d ishimura_db -c "INSERT INTO system_users (username, password_hash, is_default) VALUES ('admin', '$WEB_HASH', false) ON CONFLICT (username) DO UPDATE SET password_hash = '$WEB_HASH', is_default = false;"

# ШАГ 6: Настройка самозапуска всей системы после перезагрузки сервера (Cron)
echo "[*] Настройка планировщика автозапуска демонов Sadako, Ashka, Mifiko..."
# Заносим скрипты мониторинга, теневого бэкапа и контроля файлов в системный cron root
(crontab -l 2>/dev/null; echo "*/5 * * * * python3 /opt/ishimura/modules/mifiko/integrity.py") | crontab -
(crontab -l 2>/dev/null; echo "*/10 * * * * python3 /opt/ishimura/modules/ashka/backup.py init") | crontab -
(crontab -l 2>/dev/null; echo "* * * * * python3 /opt/ishimura/modules/sadako/monitor.py") | crontab -

# ШАГ 7: Создание первичного теневого слепка для неизменяемости системы
python3 /opt/ishimura/modules/ashka/backup.py init

# Перезапуск веб-сервера для применения конфигураций
systemctl restart apache2
systemctl enable apache2

# Сделай консоль Kuruma глобально доступной командой
ln -sf /opt/ishimura/console/kuruma.py /usr/local/bin/ishimura
chmod +x /opt/ishimura/console/kuruma.py

echo -e "\033[92m[+] УСТАНОВКА ЗАВЕРШЕНА УСПЕШНО!\033[0m"
echo -e "\033[92m[+] Веб-панель Hatsumi доступна по адресу: http://$(hostname -I | awk '{print $1}')/\033[0m"
echo -e "\033[92m[+] Консоль управления Kuruma доступна из любой точки по команде: ishimura\033[0m"
