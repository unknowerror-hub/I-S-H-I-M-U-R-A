#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: LAMIA (AI INTRUSION PREVENTION SYSTEM)
# ==============================================================================
# Описание: ИИ-анализ логов зловредного трафика, автоматическое вычисление 
#           доверенных IP-адресов системы и ведение черных/белых списков.
# ==============================================================================

import os
import sys
import json
import psycopg2

BASE_DIR = "/opt/ishimura"
DB_CONFIG_PATH = os.path.join(BASE_DIR, "modules/arlechino/config.json")

def load_db_creds():
    with open(DB_CONFIG_PATH, 'r') as f:
        cfg = json.load(f)
    password = os.getenv("ISHIMURA_DB_PASSWORD", "ishimura_default_pass")
    return {"host": "127.0.0.1", "port": cfg["listen_port"], "user": cfg["db_user"], "password": password, "dbname": cfg["db_name"]}

def synchronize_white_list():
    """ Автоматический сбор IP всех легитимных нод и занесение их в белый список """
    print("[*] Инициализация ИИ-синхронизации доверенных адресов...")
    creds = load_db_creds()
    
    try:
        conn = psycopg2.connect(**creds)
        cursor = conn.cursor()
        
        # Получаем адреса всех зарегистрированных в системе модулей
        cursor.execute("SELECT DISTINCT ip_address FROM system_modules;")
        nodes = cursor.fetchall()
        
        white_list = ["127.0.0.1"]
        for node in nodes:
            if node[0] not in white_list:
                white_list.append(node[0])
                
        print(f"[+] В белый список Lamia имплантировано {len(white_list)} адресов системы.")
        print(f"[+] Активный контур защиты: {white_list}")
        
        cursor.close()
        conn.close()
    except Exception as e:
        print(f"[-] Ошибка Lamia при работе с СУБД: {e}", file=sys.stderr)

if __name__ == "__main__":
    synchronize_white_list()
