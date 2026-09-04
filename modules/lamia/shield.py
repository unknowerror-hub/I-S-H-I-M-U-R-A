#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: LAMIA [IPS KERNEL TRAFFIC INSPECTOR]
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

def init_lamia_database():
    """ Автоматическое создание таблиц логирования атак и списков по ТЗ """
    creds = load_db_credentials()
    try:
        conn = psycopg2.connect(**creds)
        cursor = conn.cursor()
        
        # 1. Журнал обнаруженных вторжений и попыток повреждения модулей
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS lamia_attacks (
                id SERIAL PRIMARY KEY,
                source_ip VARCHAR(45) NOT NULL,
                target_module VARCHAR(50) NOT NULL,
                attack_type VARCHAR(50) NOT NULL, -- 'RCE', 'MALWARE', 'INTEGRITY_VIOLATION'
                payload_signature TEXT,
                detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        """)
        
        # 2. Таблица белых и черных списков IP
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS lamia_lists (
                ip_address VARCHAR(45) PRIMARY KEY,
                list_type VARCHAR(10) NOT NULL CHECK (list_type IN ('WHITE', 'BLACK')),
                description TEXT
            );
        """)
        
        # Автоматическое внесение IP всей локальной системы в БЕЛЫЙ список по умолчанию
        cursor.execute("""
            INSERT INTO lamia_lists (ip_address, list_type, description)
            VALUES ('127.0.0.1', 'WHITE', 'Локальный мастер-интерфейс кластера Ishimura')
            ON CONFLICT (ip_address) DO NOTHING;
        """)
        
        conn.commit()
        cursor.close()
        conn.close()
        print("[+] [LAMIA KERNEL] Таблицы защиты и списков успешно развернуты в СУБД.")
    except Exception as e:
        print(f"[-] [LAMIA KERNEL] Ошибка инициализации таблиц СУБД: {e}")

if __name__ == "__main__":
    init_lamia_database()
