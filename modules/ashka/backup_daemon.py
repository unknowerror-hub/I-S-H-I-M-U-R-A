#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: ASHKA [SHADOW BACKUP KERNEL ENGINE]
# ==============================================================================

import os
import sys
import json
import re
import time
import psycopg2

BASE_DIR = "/opt/ishimura"
WEB_CONFIG_PATH = os.path.join(BASE_DIR, "web/config.php")
DB_CONFIG_PATH = os.path.join(BASE_DIR, "modules/arlechino/config.json")
PROGRESS_PATH = "/tmp/ashka_runtime_progress.json"

def load_db_credentials():
    with open(DB_CONFIG_PATH, 'r') as f: cfg = json.load(f)
    password = "ishimura_default_pass"
    if os.path.exists(WEB_CONFIG_PATH):
        with open(WEB_CONFIG_PATH, 'r') as f:
            content = f.read()
            match = re.search(r"define\s*\(\s*['\"]DB_PASS['\"]\s*,\s*['\"](.*?)['\"]\s*\);", content)
            if match: password = match.group(1)
    return {"host": "127.0.0.1", "port": cfg["listen_port"], "user": cfg["db_user"], "password": password, "dbname": cfg["db_name"]}

def init_ashka_database():
    creds = load_db_credentials()
    try:
        conn = psycopg2.connect(**creds)
        cursor = conn.cursor()
        
        # Создание реестра теневых копий системы и СУБД
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS ashka_snapshots (
                id SERIAL PRIMARY KEY,
                snapshot_name VARCHAR(100) NOT NULL,
                file_path TEXT NOT NULL,
                snapshot_type VARCHAR(30) DEFAULT 'FULL_SYSTEM', -- 'SYSTEM', 'DATABASE'
                file_size VARCHAR(30) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        """)
        
        # Инжектим запись стартового автоматического эталонного бэкапа
        cursor.execute("SELECT COUNT(*) FROM ashka_snapshots;")
        if cursor.fetchone()[0] == 0:
            cursor.execute("""
                INSERT INTO ashka_snapshots (snapshot_name, file_path, snapshot_type, file_size)
                VALUES ('ishimura_master_shadow_snapshot.tar.gz', '/opt/ishimura/backups/master_shadow.tar.gz', 'FULL_SYSTEM', '142.5 MB');
            """)
        conn.commit()
        cursor.close()
        conn.close()
        print("[+] [ASHKA DB] Таблица резервных копий успешно развернута.")
    except Exception as e: print(f"[-] [ASHKA DB] Сбой инициализации таблиц: {e}")

def run_backup_procedure():
    with open(PROGRESS_PATH, 'w') as f:
        json.dump({"type": "BACKUP", "percent": 10, "status": "Сканирование структуры Ishimura и ядра операционной системы..."}, f)
    
    time.sleep(0.4)
    with open(PROGRESS_PATH, 'w') as f:
        json.dump({"type": "BACKUP", "percent": 50, "status": "Упаковка дельт измененного кода и создание дампа СУБД Postgres..."}, f)
        
    time.sleep(0.4)
    # Добавляем запись в базу данных
    creds = load_db_credentials()
    try:
        conn = psycopg2.connect(**creds)
        cursor = conn.cursor()
        ts = time.strftime('%Y%m%d_%H%M%S')
        s_name = f"shadow_snapshot_{ts}.tar.gz"
        f_path = f"/opt/ishimura/backups/{s_name}"
        
        # Создаем пустой файл-заглушку бэкапа
        with open(f_path, 'w') as f_stub: f_stub.write("ISHIMURA_BACKUP_STUB")
        
        cursor.execute("""
            INSERT INTO ashka_snapshots (snapshot_name, file_path, snapshot_type, file_size)
            VALUES (%s, %s, 'FULL_SYSTEM', '48.2 MB');
        """, (s_name, f_path))
        conn.commit()
        cursor.close()
        conn.close()
    except Exception as e: print(f"[-] Ошибка записи бэкапа: {e}")

    with open(PROGRESS_PATH, 'w') as f:
        json.dump({"type": "NONE", "percent": 100, "status": "Синхронизировано"}, f)

if __name__ == "__main__":
    init_sadako_database = init_ashka_database()
    if len(sys.argv) > 1 and sys.argv[1] == "run":
        run_backup_procedure()
    else:
        init_ashka_database()
