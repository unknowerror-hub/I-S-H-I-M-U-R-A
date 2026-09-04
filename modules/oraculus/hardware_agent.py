#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: ORACULUS [HARDWARE INVENTORY & IP ENGINE]
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

def init_oraculus_database():
    """ Автоматическое создание таблиц оборудования и интерфейсов на основе ТЗ """
    creds = load_db_credentials()
    try:
        conn = psycopg2.connect(**creds)
        cursor = conn.cursor()
        
        # 1. Таблица физического оборудования сервера
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS oraculus_hardware (
                id SERIAL PRIMARY KEY,
                device_type VARCHAR(50) NOT NULL,
                device_model TEXT NOT NULL,
                device_spec TEXT,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        """)
        
        # 2. Таблица сетевых интерфейсов и IP адресов всей системы
        cursor.execute("""
            CREATE TABLE IF NOT EXISTS oraculus_network (
                iface_name VARCHAR(50) PRIMARY KEY,
                mac_address VARCHAR(17) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                netmask VARCHAR(45) NOT NULL,
                link_status VARCHAR(10) DEFAULT 'DOWN'
            );
        """)
        conn.commit()
        cursor.close()
        conn.close()
        print("[+] [ORACULUS DB] Схемы таблиц успешно развернуты в PostgreSQL.")
    except Exception as e:
        print(f"[-] [ORACULUS DB] Ошибка развертки базы: {e}")

def collect_hardware_specs():
    """ Низкоуровневый автоматический сбор спецификаций без внешних утилит """
    cpu_model = "Unknown Intel/AMD Processor"
    try:
        with open('/proc/cpuinfo', 'r') as f:
            for line in f:
                if "model name" in line:
                    cpu_model = line.split(':')[1].strip()
                    break
    except: pass

    # Считывание сетевых интерфейсов из Sysfs
    interfaces = []
    try:
        iface_dir = '/sys/class/net/'
        for iface in os.listdir(iface_dir):
            if iface == 'lo': continue # Пропускаем локальную петлю
            
            # Чтение MAC адреса
            mac = "00:00:00:00:00:00"
            try:
                with open(os.path.join(iface_dir, iface, 'address'), 'r') as f:
                    mac = f.read().strip()
            except: pass
            
            # Чтение состояния линка
            status = "DOWN"
            try:
                with open(os.path.join(iface_dir, iface, 'operstate'), 'r') as f:
                    status = f.read().strip().upper()
            except: pass
            
            interfaces.append((iface, mac, status))
    except: pass

    # Запись собранных данных в PostgreSQL
    creds = load_db_credentials()
    try:
        conn = psycopg2.connect(**creds)
        cursor = conn.cursor()
        
        # Заносим CPU и базовую материнскую плату
        cursor.execute("""
            INSERT INTO oraculus_hardware (device_type, device_model, device_spec)
            VALUES ('CPU', %s, 'Прямой Sysfs-парсинг ядер процессора')
            ON CONFLICT DO NOTHING;
        """, (cpu_model,))
        
        # Заносим/Обновляем сетевые карты
        for name, mac, state in interfaces:
            # Пытаемся получить текущий IP интерфейса штатными средствами сокета
            import socket, fcntl, struct
            ip, mask = "0.0.0.0", "255.255.255.0"
            try:
                s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
                ip = socket.inet_ntoa(fcntl.ioctl(s.fileno(), 0x8915, struct.pack('256s', name[:15].encode('utf-8')))[20:24])
            except: pass
            
            cursor.execute("""
                INSERT INTO oraculus_network (iface_name, mac_address, ip_address, netmask, link_status)
                VALUES (%s, %s, %s, %s, %s)
                ON CONFLICT (iface_name) DO UPDATE SET link_status = %s, ip_address = %s;
            """, (name, mac, ip, mask, state, state, ip))
            
        conn.commit()
        cursor.close()
        conn.close()
        print("[+] [ORACULUS AGENT] Спецификации оборудования зафиксированы.")
    except Exception as e:
        print(f"[-] [ORACULUS AGENT] Ошибка записи в СУБД: {e}")

if __name__ == "__main__":
    init_oraculus_database()
    collect_hardware_specs()
