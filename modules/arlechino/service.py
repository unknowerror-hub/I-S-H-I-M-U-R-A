#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: ARLECHINO (DATABASE CORE)
# ==============================================================================
# Описание: Демон автоматической инициализации таблиц, мониторинга СУБД,
#           генерации ключей авторизации и управления кластеризацией.
# ==============================================================================

import os
import sys
import json
import time
import secrets
import psycopg2
from psycopg2 import extensions

# Пути к глобальным файлам системы
BASE_DIR = "/opt/ishimura"
TOKEN_FILE = os.path.join(BASE_DIR, "system_token.key")
CONFIG_FILE = os.path.join(BASE_DIR, "modules/arlechino/config.json")

def generate_system_token():
    """ Автоматическая генерация защищенного 64-символьного токена для модулей """
    if not os.path.exists(TOKEN_FILE):
        print("[+] Токен системы отсутствует. Генерация нового ключа...")
        token = secrets.token_hex(32)
        with open(TOKEN_FILE, "w") as f:
            f.write(token)
        os.chmod(TOKEN_FILE, 0o600) # Права доступа только для root
        print(f"[+] Безопасный токен сгенерирован и сохранен в {TOKEN_FILE}")
    else:
        print("[*] Системный токен авторизации успешно загружен.")

def load_config():
    """ Загрузка конфигурационных параметров модуля """
    with open(CONFIG_FILE, 'r') as f:
        return json.load(f)

def get_db_connection(config, db_name="postgres"):
    """ Установка прямого соединения с СУБД под пользователем postgres для администрирования """
    # Пароль считывается из переменных окружения, которые прописывает установщик
    db_password = os.getenv("ISHIMURA_DB_PASSWORD", "ishimura_default_pass")
    return psycopg2.connect(
        host="127.0.0.1",
        port=config["listen_port"],
        user="postgres",
        password=db_password,
        database=db_name
    )

def initialize_database():
    """ Автоматическое создание целевой БД, пользователя и таблиц всех модулей системы """
    config = load_config()
    db_password = os.getenv("ISHIMURA_DB_PASSWORD", "ishimura_default_pass")
    
    conn = get_db_connection(config, "postgres")
    conn.set_isolation_level(extensions.ISOLATION_LEVEL_AUTOCOMMIT)
    cursor = conn.cursor()
    
    # БЛОК 1: Создание роли и базы данных системы Ishimura
    try:
        cursor.execute(f"CREATE USER {config['db_user']} WITH PASSWORD '{db_password}';")
        print(f"[+] Пользователь {config['db_user']} успешно создан.")
    except psycopg2.errors.DuplicateObject:
        print(f"[*] Пользователь {config['db_user']} уже существует.")
        
    try:
        cursor.execute(f"CREATE DATABASE {config['db_name']} OWNER {config['db_user']};")
        print(f"[+] База данных {config['db_name']} успешно создана.")
    except psycopg2.errors.DuplicateDatabase:
        print(f"[*] База данных {config['db_name']} уже существует.")
        
    cursor.close()
    conn.close()

    # БЛОК 2: Создание боевых таблиц системы под управлением созданной БД
    conn = psycopg2.connect(host="127.0.0.1", port=config["listen_port"], user=config["db_user"], password=db_password, database=config["db_name"])
    cursor = conn.cursor()
    
    # Таблица аудита и статуса модулей (Hatsumi / Sadako / Mifiko)
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS system_modules (
            id SERIAL PRIMARY KEY,
            module_name VARCHAR(50) UNIQUE NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            port INT NOT NULL,
            status VARCHAR(20) DEFAULT 'OFFLINE',
            last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    """)

    # Таблица результатов сканирования и уязвимостей (Arachna / Miko / Terror)
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS vulnerability_scans (
            id SERIAL PRIMARY KEY,
            target_ip VARCHAR(45) NOT NULL,
            port INT NOT NULL,
            service_name VARCHAR(50),
            service_version VARCHAR(100),
            os_info VARCHAR(150),
            cve_id VARCHAR(30),
            severity VARCHAR(20),
            description TEXT,
            remediation TEXT,
            exploit_status VARCHAR(30) DEFAULT 'NOT_TESTED',
            exploit_path TEXT,
            scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    """)

    # Таблица локальной базы сигнатур vuln_db (Arachna / Miko)
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS local_vuln_db (
            id SERIAL PRIMARY KEY,
            cve_id VARCHAR(30) UNIQUE NOT NULL,
            title TEXT NOT NULL,
            description TEXT,
            cvss_score NUMERIC(3,1),
            solution TEXT,
            references_links TEXT
        );
    """)

    # Таблица логов ИИ-защиты ядра (Lamia)
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS security_events (
            id SERIAL PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            source_ip VARCHAR(45) NOT NULL,
            payload_sample TEXT,
            action_taken VARCHAR(30) NOT NULL,
            event_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    """)

    # Таблица бэкапов и теневых слепков файлов (Ashka / Mifiko)
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS system_backups (
            id SERIAL PRIMARY KEY,
            backup_type VARCHAR(30) NOT NULL,
            file_path TEXT NOT NULL,
            file_hash VARCHAR(64) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    """)

    conn.commit()
    cursor.close()
    conn.close()
    print("[+] Все таблицы боевых модулей ISHIMURA успешно инициализированы.")

def check_db_status():
    """ Сбор метрик СУБД для вывода в веб-панель (размер, количество подключений) """
    try:
        config = load_config()
        conn = get_db_connection(config, config["db_name"])
        cursor = conn.cursor()
        
        # Получение размера БД
        cursor.execute(f"SELECT pg_size_pretty(pg_database_size('{config['db_name']}'));")
        db_size = cursor.fetchone()[0]
        
        # Получение активных подключений
        cursor.execute("SELECT count(*) FROM pg_stat_activity;")
        active_connections = cursor.fetchone()[0]
        
        cursor.close()
        conn.close()
        return {"status": "ONLINE", "size": db_size, "connections": active_connections}
    except Exception as e:
        return {"status": "OFFLINE", "error": str(e)}

if __name__ == "__main__":
    print("[*] Запуск подсистемы инициализации Arlechino...")
    generate_system_token()
    try:
        initialize_database()
    except Exception as ex:
        print(f"[-] Критическая ошибка при развертывании таблиц БД: {ex}", file=sys.stderr)
