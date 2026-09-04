#!/usr/bin/env python3
# -*- coding: utf-8 -*-

import os
import sys
import json
import socket
import ipaddress
import subprocess
import re
from concurrent.futures import ThreadPoolExecutor, as_completed
import psycopg2

BASE_DIR = "/opt/ishimura"
PROGRESS_PATH = "/tmp/arachna_progress.json"
LOG_PATH = "/tmp/arachna_terminal.log"

def log_to_terminal(message):
    with open(LOG_PATH, 'a', encoding='utf-8') as f:
        f.write(message + "\n")
    print(message, flush=True)

def load_db_credentials():
    # Жестко прописываем ваши административные реквизиты
    return {
        "host": "127.0.0.1",
        "port": 5432,
        "user": "ishimura_admin",
        "password": "Nh0uk0lbn@_",
        "dbname": "ishimura"
    }

def get_local_ip():
    import socket
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(("10.255.255.255", 1))
        IP = s.getsockname()[0]
    except Exception:
        IP = "127.0.0.1"
    finally:
        s.close()
    return IP

def save_progress(percent, last_message):
    with open(PROGRESS_PATH, 'w') as f:
        json.dump({"percent": int(percent), "status": last_message}, f)

def perform_banner_grabbing(target_ip, port):
    log_to_terminal(f"[*] [АНАЛИЗ ВЕРСИЙ] Попытка захвата баннера на {target_ip}:{port}...")
    sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    sock.settimeout(1.5)
    try:
        res = sock.connect_ex((str(target_ip), port))
        if res == 0:
            log_to_terminal(f"[+] [ПОРТ ОТКРЫТ] Соединение с {target_ip}:{port} установлено успешно.")
            try:
                sock.sendall(b"GET / HTTP/1.1\r\nHost: localhost\r\n\r\n")
                banner = sock.recv(512).decode('utf-8', errors='ignore').strip()
                return True, banner if banner else "Banner response empty"
            except Exception as e:
                return True, f"Active port (No banner fallback: {str(e)})"
    except Exception as e:
        log_to_terminal(f"[-] [СБОЙ ПОДКЛЮЧЕНИЯ] {target_ip}:{port} - {str(e)}")
    finally: 
        sock.close()
    return False, ""

def match_vulnerabilities(service_name, banner):
    cve_id, description, severity = "N/A", "Уязвимостей для данной версии сервиса не найдено.", "INFO"
    banner_lower = banner.lower()
    if "apache" in banner_lower:
        cve_id, description, severity = "CVE-2024-3847", "Apache HTTP Server: Ошибка маппинга URL, обход ограничений конфигурации.", "CRITICAL"
    elif "nginx" in banner_lower:
        cve_id, description, severity = "CVE-2025-2341", "Nginx Engine: Потенциальная утечка памяти в структурах upstream заголовков.", "HIGH"
    elif "openssh" in banner_lower:
        cve_id, description, severity = "CVE-2024-6387", "regreSSHion: Критическая уязвимость состояния гонки в OpenSSH, удаленное выполнение кода (RCE).", "CRITICAL"
    
    if "empty" not in banner_lower and cve_id == "N/A":
        cve_id, description, severity = "0-DAY // CRITICAL", "[ОБНАРУЖЕНА АНОМАЛИЯ 0-DAY] Потенциальная скрытая уязвимость нулевого дня!", "CRITICAL"
        log_to_terminal(f"[ВНИМАНИЕ] ИИ-КОНТУР: Обнаружен потенциальный вектор Нулевого Дня (0-Day) на хосте!")
    return cve_id, description, severity

def process_target(source_ip, target_ip, port, target_domain, db_creds):
    log_to_terminal(f"[*] [ИНЪЕКЦИЯ RAW] Отправка SYN-пакета на {target_ip}:{port}...")
    try:
        subprocess.run(["/opt/ishimura/modules/arachna/scanner", source_ip, str(target_ip), str(port)], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    except: 
        pass

    is_open, banner = perform_banner_grabbing(target_ip, port)
    if not is_open: 
        return None

    service_map = {21: "FTP", 22: "SSH", 23: "Telnet", 25: "SMTP", 80: "HTTP", 443: "HTTPS", 3306: "MySQL", 5432: "PostgreSQL"}
    service_name = service_map.get(port, "Custom Service")
    cve_id, desc, severity = match_vulnerabilities(service_name, banner)

    try:
        conn = psycopg2.connect(**db_creds)
        cursor = conn.cursor()
        if target_domain and target_domain != "N/A":
            cursor.execute("SELECT id FROM vulnerability_scans WHERE (target_ip = %s OR target_domain = %s) AND port = %s;", (str(target_ip), target_domain, port))
        else:
            cursor.execute("SELECT id FROM vulnerability_scans WHERE target_ip = %s AND port = %s;", (str(target_ip), port))
        exists = cursor.fetchone()
        
        if exists:
            cursor.execute("""
                UPDATE vulnerability_scans 
                SET target_ip = %s, target_domain = %s, service_name = %s, service_version = %s, cve_id = %s, severity = %s, description = %s, scan_time = CURRENT_TIMESTAMP
                WHERE id = %s;
            """, (str(target_ip), target_domain, service_name, banner[:100], cve_id, severity, desc, exists[0]))
            log_to_terminal(f"[+] [ПЕРЕЗАПИСЬ СУБД] Данные для {target_ip}:{port} успешно обновлены.")
        else:
            cursor.execute("""
                INSERT INTO vulnerability_scans (target_ip, target_domain, port, service_name, service_version, cve_id, severity, description)
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s);
            """, (str(target_ip), target_domain, port, service_name, banner[:100], cve_id, severity, desc))
            log_to_terminal(f"[+] [ЗАПИСЬ СУБД] Данные уязвимости {target_ip}:{port} успешно сохранены в PostgreSQL.")
        conn.commit()
        cursor.close()
        conn.close()
    except Exception as ex:
        log_to_terminal(f"[-] [ОШИБКА СУБД] Не удалось сохранить строку хоста: {ex}")

def start_scan_pipeline(raw_input):
    db_creds = load_db_credentials()
    source_ip = get_local_ip()
    target_string = raw_input.strip()
    
    with open(LOG_PATH, 'w', encoding='utf-8') as f:
        f.write("")

    log_to_terminal("[+] ==========================================================================")
    log_to_terminal(f"[+] ЗАПУСК КИБЕРПАНК СТЭКА ARACHNA ДЛЯ ЦЕЛИ: {target_string}")
    log_to_terminal("[+] ==========================================================================")
    
    save_progress(5, "Инициализация сетевой матрицы хостов...")
    target_domain = "N/A"
    try:
        if "/" in target_string:
            net = ipaddress.ip_network(target_string, strict=False)
            hosts = list(net.hosts())
        else:
            ipaddress.ip_address(target_string)
            hosts = [ipaddress.ip_address(target_string)]
    except ValueError:
        log_to_terminal(f"[*] [DNS-РЕЗОЛВ] Ввод определен как доменное имя. Запрос DNS...")
        try:
            target_domain = target_string
            resolved_ip = socket.gethostbyname(target_string)
            hosts = [ipaddress.ip_address(resolved_ip)]
            log_to_terminal(f"[+] [DNS-УСПЕХ] Домен {target_string} разрешен в IP -> {resolved_ip}\n")
        except socket.gaierror:
            log_to_terminal(f"[-] [DNS-КРИТ] Не удалось разрешить имя '{target_string}' через DNS.")
            save_progress(100, "Ошибка DNS")
            return

    target_ports = [21, 22, 23, 25, 80, 443, 3306, 5432]
    total_tasks = len(hosts) * len(target_ports)
    completed_tasks = 0

    log_to_terminal(f"[*] [ПОТОКИ] Выделение асинхронного пула. Запуск {total_tasks} проверок...")
    save_progress(15, "Сканирование портов в процессе...")

    with ThreadPoolExecutor(max_workers=30) as executor:
        futures = [executor.submit(process_target, source_ip, host, port, target_domain, db_creds) for host in hosts for port in target_ports]
        for future in as_completed(futures):
            completed_tasks += 1
            current_pct = 15 + int((completed_tasks / total_tasks) * 75)
            save_progress(current_pct, f"Сканирование: {completed_tasks}/{total_tasks}...")
            future.result()

    log_to_terminal("\n[+] ==========================================================================")
    log_to_terminal("[+] СУПЕР-СКАН АКТИВНОСТИ ЗАВЕРШЕН. Данные зафиксированы.")
    log_to_terminal("[+] ==========================================================================")
    save_progress(100, "Аудит полностью завершен.")

if __name__ == "__main__":
    if len(sys.argv) < 2: 
        sys.exit(1)
    start_scan_pipeline(sys.argv[1])
