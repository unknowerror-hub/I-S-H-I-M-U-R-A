#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: KIRA [AI ORCHESTRATOR & CLUSTER CORE]
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

def init_kira_database():
    """ Автоматическое создание таблиц модуля Kira на основе ТЗ """
    creds = load_db_credentials()
    try:
        conn = psycopg2.connect(**creds)
        cursor = conn.cursor()
        
        # 1. Реестр серверов в кластере
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS kira_nodes (
                id SERIAL PRIMARY KEY,
                node_ip VARCHAR(45) UNIQUE NOT NULL,
                node_role VARCHAR(30) DEFAULT 'WORKER',
                status VARCHAR(20) DEFAULT 'SYNCHRONIZED',
                last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        """)
        
        # 2. Таблица параметров ИИ-движков, Telegram и SMTP
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS kira_config (
                param_key VARCHAR(50) PRIMARY KEY,
                param_value TEXT NOT NULL
            );
        """)
        
        # Запись дефолтных параметров ИИ и шлюзов алертинга
        default_configs = [
            ("ai_hardware", "CPU"), # По умолчанию вычисления идут на CPU
            ("tg_bot_token", ""),
            ("tg_chat_id", ""),
            ("tg_events", "CRITICAL,0-DAY"),
            ("smtp_host", "smtp.mail.ru"),
            ("smtp_port", "465"),
            ("smtp_user", ""),
            ("smtp_pass", ""),
            ("smtp_events", "CRITICAL")
        ]
        for key, val in default_configs:
            cursor.execute("""
                INSERT INTO kira_config (param_key, param_value) 
                VALUES (%s, %s) ON CONFLICT (param_key) DO NOTHING;
            """, (key, val))
            
        # Инжектим локальную мастер-ноду в пул кластера
        cursor.execute("""
            INSERT INTO kira_nodes (node_ip, node_role, status) 
            VALUES ('127.0.0.1', 'MASTER', 'SYNCHRONIZED') ON CONFLICT (node_ip) DO NOTHING;
        """)
        
        conn.commit()
        cursor.close()
        conn.close()
        print("[+] [KIRA CONSOLE] Структура базы данных ИИ-кластера успешно развернута.")
    except Exception as e:
        print(f"[-] [KIRA CONSOLE] Ошибка развертки базы данных: {e}")

if __name__ == "__main__":
    init_kira_database()
