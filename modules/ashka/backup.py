#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: ASHKA (SHADOW COPY & BACKUP ENGINE)
# ==============================================================================
# Описание: Боевой модуль теневого копирования ядра системы, генерации
#           архивов восстановления и инкрементального сканирования каталогов.
# ==============================================================================

import os
import sys
import json
import shutil
import hashlib
import psycopg2

BASE_DIR = "/opt/ishimura"
SHADOW_DIR = "/opt/ishimura/.shadow_storage"
BACKUP_DIR = "/opt/ishimura/backups"
DB_CONFIG_PATH = os.path.join(BASE_DIR, "modules/arlechino/config.json")

def load_db_creds():
    with open(DB_CONFIG_PATH, 'r') as f:
        cfg = json.load(f)
    password = os.getenv("ISHIMURA_DB_PASSWORD", "ishimura_default_pass")
    return {"host": "127.0.0.1", "port": cfg["listen_port"], "user": cfg["db_user"], "password": password, "dbname": cfg["db_name"]}

def get_file_hash(path):
    """ Вычисление SHA-256 хэша файла для контроля целостности """
    hasher = hashlib.sha256()
    try:
        with open(path, 'rb') as f:
            buf = f.read(65536)
            while len(buf) > 0:
                hasher.update(buf)
                buf = f.read(65536)
        return hasher.hexdigest()
    except:
        return ""

def create_initial_shadow_copy():
    """ Единоразовое создание скрытого неизменяемого слепка всех файлов """
    if not os.path.exists(SHADOW_DIR):
        os.makedirs(SHADOW_DIR, exist_ok=True)
        
    print("[*] Ashka: Инициализация первичного теневого слепка системы Ishimura...")
    
    creds = load_db_creds()
    conn = psycopg2.connect(**creds)
    cursor = conn.cursor()

    for root, dirs, files in os.walk(BASE_DIR):
        # Исключаем служебные директории бэкапов и скрытые папки
        if ".shadow_storage" in root or "backups" in root or "exports" in root:
            continue
            
        for file in files:
            full_path = os.path.join(root, file)
            rel_path = os.path.relpath(full_path, BASE_DIR)
            shadow_dest = os.path.join(SHADOW_DIR, rel_path)
            
            os.makedirs(os.path.dirname(shadow_dest), exist_ok=True)
            if not os.path.exists(shadow_dest):
                shutil.copy2(full_path, shadow_dest)
                f_hash = get_file_hash(full_path)
                
                # Логируем слепок в базу Arlechino
                cursor.execute("""
                    INSERT INTO system_backups (backup_type, file_path, file_hash)
                    VALUES ('SHADOW_SNAPSHOT', %s, %s);
                """, (rel_path, f_hash))
                
    conn.commit()
    cursor.close()
    conn.close()
    print("[+] Первичная теневая копия успешно зафиксирована в СУБД.")

def clear_all_shadows():
    """ Полная очистка теневого хранилища для создания нового слепка """
    if os.path.exists(SHADOW_DIR):
        shutil.rmtree(SHADOW_DIR)
    
    creds = load_db_creds()
    conn = psycopg2.connect(**creds)
    cursor = conn.cursor()
    cursor.execute("DELETE FROM system_backups WHERE backup_type = 'SHADOW_SNAPSHOT';")
    conn.commit()
    cursor.close()
    conn.close()
    print("[+] Теневой репозиторий полностью очищен по директиве администратора.")

if __name__ == "__main__":
    if len(sys.argv) > 1:
        action = sys.argv
        if action == "init":
            create_initial_shadow_copy()
        elif action == "clear":
            clear_all_shadows()
    else:
        create_initial_shadow_copy()
