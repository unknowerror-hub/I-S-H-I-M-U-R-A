#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: MIFIKO [INTEGRITY & CODE CONTROL DAEMON]
# ==============================================================================

import os
import sys
import json
import re
import psycopg2

BASE_DIR = "/opt/ishimura"
WEB_CONFIG_PATH = os.path.join(BASE_DIR, "web/config.php")
DB_CONFIG_PATH = os.path.join(BASE_DIR, "modules/arlechino/config.json")

def load_db_credentials():
    with open(DB_CONFIG_PATH, 'r') as f:
        cfg = json.load(f)
    password = "ishimura_default_pass"
    if os.path.exists(WEB_CONFIG_PATH):
        with open(WEB_CONFIG_PATH, 'r') as f:
            content = f.read()
            match = re.search(r"define\s*\(\s*['\"]DB_PASS['\"]\s*,\s*['\"](.*?)['\"]\s*\);", content)
            if match: password = match.group(1)
    return {"host": "127.0.0.1", "port": cfg["listen_port"], "user": cfg["db_user"], "password": password, "dbname": cfg["db_name"]}

def init_mifiko_database():
    creds = load_db_credentials()
    try:
        conn = psycopg2.connect(**creds)
        cursor = conn.cursor()
        
        # 1. Таблица нарушений целостности файлов
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS mifiko_integrity (
                id SERIAL PRIMARY KEY,
                file_path TEXT NOT NULL,
                status VARCHAR(30) DEFAULT 'CHANGED', -- 'CHANGED', 'VERIFIED'
                diff_details TEXT,
                detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        """)
        
        # 2. Таблица состояния контейнеров и оптимизаций
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS mifiko_containers (
                id SERIAL PRIMARY KEY,
                container_name VARCHAR(100) NOT NULL,
                status VARCHAR(20) NOT NULL,
                optimization_tip TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        """)

        # 3. Мониторинг повышения прав учетных записей (ОС и СУБД)
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS mifiko_privileges (
                id SERIAL PRIMARY KEY,
                user_name VARCHAR(50) NOT NULL,
                event_desc TEXT NOT NULL,
                severity VARCHAR(20) DEFAULT 'WARNING',
                detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        """)
        
        # Инжектим тестовые аномалии для первоначальной проверки кнопок управления
        cursor.execute("""
            INSERT INTO mifiko_integrity (file_path, status, diff_details)
            VALUES ('/opt/ishimura/web/config.php', 'CHANGED', 'Обнаружено несанкционированное изменение констант DB_PASS в строке 14. Контрольная хэш-сумма SHA256 не совпадает с теневым бэкапом Ashka.')
            ON CONFLICT DO NOTHING;
        """)
        
        cursor.execute("""
            INSERT INTO mifiko_containers (container_name, status, optimization_tip)
            VALUES ('ishimura_scanner_node_1', 'OVERLOADED', 'Контейнер превысил лимит RAM на 18%. Рекомендуется добавить лимит --memory=\"1g\" и оптимизировать многопоточный пул сокетов Arachna.')
            ON CONFLICT DO NOTHING;
        """)

        cursor.execute("""
            INSERT INTO mifiko_privileges (user_name, event_desc, severity)
            VALUES ('www-data', 'Попытка вызова бинарного файла /usr/bin/sudo без явного tty-флага. Зафиксирован вектор Privilege Escalation.', 'CRITICAL')
            ON CONFLICT DO NOTHING;
        """)
        
        conn.commit()
        cursor.close()
        conn.close()
        print("[+] [MIFIKO KERNEL] Внутренние таблицы СУБД успешно созданы.")
    except Exception as e:
        print(f"[-] [MIFIKO KERNEL] Сбой инициализации: {e}")

if __name__ == "__main__":
    init_mifiko_database()
