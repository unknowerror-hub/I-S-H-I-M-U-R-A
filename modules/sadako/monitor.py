#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: SADAKO (SYSTEM METRICS DEMON)
# ==============================================================================
# Описание: Боевой легковесный монитор ресурсов. Считывает загрузку CPU, 
#           памяти, диска и сетевой карты напрямую из ядра Linux (/proc).
# ==============================================================================

import os
import sys
import json
import time
import psycopg2

BASE_DIR = "/opt/ishimura"
DB_CONFIG_PATH = os.path.join(BASE_DIR, "modules/arlechino/config.json")

def load_db_creds():
    with open(DB_CONFIG_PATH, 'r') as f:
        cfg = json.load(f)
    password = os.getenv("ISHIMURA_DB_PASSWORD", "ishimura_default_pass")
    return {"host": "127.0.0.1", "port": cfg["listen_port"], "user": cfg["db_user"], "password": password, "dbname": cfg["db_name"]}

def get_cpu_usage():
    """ Чтение полной загрузки CPU из /proc/stat """
    with open('/proc/stat', 'r') as f:
        line = f.readline().split()
    user, nice, system, idle = map(int, line[1:5])
    total = user + nice + system + idle
    return round(100.0 * (user + system) / total, 1)

def get_ram_usage():
    """ Чтение утилизации RAM и Swap из /proc/meminfo """
    meminfo = {}
    with open('/proc/meminfo', 'r') as f:
        for line in f:
            parts = line.split()
            meminfo[parts[0].replace(':', '')] = int(parts[1])
            
    total_ram = meminfo.get('MemTotal', 1)
    free_ram = meminfo.get('MemFree', 0) + meminfo.get('Buffers', 0) + meminfo.get('Cached', 0)
    used_ram = total_ram - free_ram
    
    total_swap = meminfo.get('SwapTotal', 1)
    free_swap = meminfo.get('SwapFree', 0)
    used_swap = total_swap - free_swap
    
    return {
        "ram_p": round((used_ram / total_ram) * 100, 1),
        "swap_p": round((used_swap / total_swap) * 100, 1) if total_swap > 0 else 0
    }

def get_disk_usage():
    """ Чтение заполнения диска корневой директории через os.statvfs """
    st = os.statvfs('/')
    total = st.f_blocks * st.f_frsize
    free = st.f_bfree * st.f_frsize
    used = total - free
    return round((used / total) * 100, 1)

def run_monitor_cycle():
    """ Единичный запуск сбора данных и логирование их в общую базу """
    creds = load_db_creds()
    cpu = get_cpu_usage()
    mem = get_ram_usage()
    disk = get_disk_usage()
    
    # Сетевые прерывания (косвенный показатель загрузки ядра сетевой карты)
    net_irq = 0
    if os.path.exists('/proc/interrupts'):
        with open('/proc/interrupts', 'r') as f:
            net_irq = sum(1 for line in f if 'eth' in line or 'enp' in line)

    try:
        conn = psycopg2.connect(**creds)
        cursor = conn.cursor()
        
        # Запись состояния в логи Lamia / Sadako для отображения
        payload = f"CPU:{cpu}%, RAM:{mem['ram_p']}%, SWAP:{mem['swap_p']}%, DISK:{disk}%, NET_KERNEL_LOAD:{net_irq}"
        cursor.execute("""
            INSERT INTO security_events (event_type, source_ip, payload_sample, action_taken)
            VALUES ('METRICS_MONITOR', '127.0.0.1', %s, 'LOGGED');
        """, (payload,))
        
        # Обновление статуса самого модуля Sadako в реестре модулей
        cursor.execute("""
            INSERT INTO system_modules (module_name, ip_address, port, status, last_ping)
            VALUES ('Sadako', '127.0.0.1', 0, 'ONLINE', CURRENT_TIMESTAMP)
            ON CONFLICT (module_name) DO UPDATE SET status = 'ONLINE', last_ping = CURRENT_TIMESTAMP;
        """, ())
        
        conn.commit()
        cursor.close()
        conn.close()
        print(f"[+] Sadako: Метрики зафиксированы -> {payload}")
    except Exception as e:
        print(f"[-] Ошибка модуля Sadako при отправке в СУБД: {e}", file=sys.stderr)

if __name__ == "__main__":
    # Демон запускает одну итерацию. Полноценный цикл завязан на системный cron или systemd
    run_monitor_cycle()
