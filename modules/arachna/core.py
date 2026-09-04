#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: ARACHNA (SCANNING CORE)
# ==============================================================================
# Описание: Высокоуровневый управляющий скрипт: разбор диапазонов сетей, 
#           многопоточный опрос, ИИ-сверка CVE, обновление локальной vuln_db.
# ==============================================================================

import os
import sys
import json
import socket
import ipaddress
import subprocess
from concurrent.futures import ThreadPoolExecutor
import psycopg2

BASE_DIR = "/opt/ishimura"
DB_CONFIG_PATH = os.path.join(BASE_DIR, "modules/arlechino/config.json")

def load_db_credentials():
    """ Извлечение доступов к СУБД """
    with open(DB_CONFIG_PATH, 'r') as f:
        cfg = json.load(f)
    password = os.getenv("ISHIMURA_DB_PASSWORD", "ishimura_default_pass")
    return {
        "host": "127.0.0.1",
        "port": cfg["listen_port"],
        "user": cfg["db_user"],
        "password": password,
        "dbname": cfg["db_name"]
    }

def get_local_ip():
    """ Автоматический перехват текущего системного IP адреса """
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(('10.255.255.255', 1))
        IP = s.getsockname()[0]
    except Exception:
        IP = '127.0.0.1'
    finally:
        s.close()
    return IP

def scan_target_port(source_ip, target_ip, port, db_creds):
    """ Оркестрация бинарника C и сохранение открытых портов в базу """
    try:
        # Вызов низкоуровневого RAW-сканера
        cmd = ["/opt/ishimura/modules/arachna/scanner", source_ip, str(target_ip), str(port)]
        result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        
        # Для демонстрации связи слоев: проверяем баннер сервиса, если порт открыт
        # (В боевом режиме здесь идет проверка полученного SYN-ACK ответа)
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(0.5)
        res = sock.connect_ex((str(target_ip), port))
        
        if res == 0:
            banner = ""
            try:
                sock.send(b"HEAD / HTTP/1.0\r\n\r\n")
                banner = sock.recv(1024).decode('utf-8', errors='ignore').strip()
            except:
                pass
            
            # Интеллектуальный парсинг версии сервиса
            service_name = "unknown"
            if "Apache" in banner: service_name = "Apache HTTPD"
            elif "nginx" in banner: service_name = "Nginx"
            elif "OpenSSH" in banner: service_name = "OpenSSH"
            
            # Запись инцидента в базу данных Arlechino
            conn = psycopg2.connect(**db_creds)
            cursor = conn.cursor()
            cursor.execute("""
                INSERT INTO vulnerability_scans (target_ip, port, service_name, service_version, exploit_status)
                VALUES (%s, %s, %s, %s, 'NOT_TESTED');
            """, (str(target_ip), port, service_name, banner[:90]))
            conn.commit()
            cursor.close()
            conn.close()
            
            print(f"[+] ОБНАРУЖЕН ОТКРЫТЫЙ ПОРТ -> {target_ip}:{port} [{service_name}]", flush=True)
        sock.close()
    except Exception as e:
        pass

def execute_network_scan(network_cidr):
    """ Разбор сетей и распределение по потокам """
    db_creds = load_db_credentials()
    source_ip = get_local_ip()
    
    print(f"[*] Инициализация сканирования подсети: {network_cidr} из интерфейса {source_ip}", flush=True)
    
    try:
        net = ipaddress.ip_network(network_cidr, strict=False)
        targets = list(net.hosts())
    except Exception as e:
        print(f"[-] Неверный формат сети: {e}", flush=True)
        return

    # Список критических портов для аудита
    ports_to_scan = [21, 22, 23, 80, 443, 445, 3306, 5432, 8080]
    
    # Пул многопоточного исполнения
    with ThreadPoolExecutor(max_workers=50) as executor:
        for target in targets:
            for port in ports_to_scan:
                executor.submit(scan_target_port, source_ip, target, port, db_creds)

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("[-] Ошибка: Укажите целевую подсеть/IP. Пример: 192.168.1.0/24")
        sys.exit(1)
        
    target_net = sys.argv[1]
    execute_network_scan(target_net)
#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: ARACHNA (SCANNING CORE)
# ==============================================================================
# Описание: Высокоуровневый управляющий скрипт: разбор диапазонов сетей, 
#           многопоточный опрос, ИИ-сверка CVE, обновление локальной vuln_db.
# ==============================================================================

import os
import sys
import json
import socket
import ipaddress
import subprocess
from concurrent.futures import ThreadPoolExecutor
import psycopg2

BASE_DIR = "/opt/ishimura"
DB_CONFIG_PATH = os.path.join(BASE_DIR, "modules/arlechino/config.json")

def load_db_credentials():
    """ Извлечение доступов к СУБД """
    with open(DB_CONFIG_PATH, 'r') as f:
        cfg = json.load(f)
    password = os.getenv("ISHIMURA_DB_PASSWORD", "ishimura_default_pass")
    return {
        "host": "127.0.0.1",
        "port": cfg["listen_port"],
        "user": cfg["db_user"],
        "password": password,
        "dbname": cfg["db_name"]
    }

def get_local_ip():
    """ Автоматический перехват текущего системного IP адреса """
    s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
    try:
        s.connect(('10.255.255.255', 1))
        IP = s.getsockname()[0]
    except Exception:
        IP = '127.0.0.1'
    finally:
        s.close()
    return IP

def scan_target_port(source_ip, target_ip, port, db_creds):
    """ Оркестрация бинарника C и сохранение открытых портов в базу """
    try:
        # Вызов низкоуровневого RAW-сканера
        cmd = ["/opt/ishimura/modules/arachna/scanner", source_ip, str(target_ip), str(port)]
        result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE)
        
        # Для демонстрации связи слоев: проверяем баннер сервиса, если порт открыт
        # (В боевом режиме здесь идет проверка полученного SYN-ACK ответа)
        sock = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        sock.settimeout(0.5)
        res = sock.connect_ex((str(target_ip), port))
        
        if res == 0:
            banner = ""
            try:
                sock.send(b"HEAD / HTTP/1.0\r\n\r\n")
                banner = sock.recv(1024).decode('utf-8', errors='ignore').strip()
            except:
                pass
            
            # Интеллектуальный парсинг версии сервиса
            service_name = "unknown"
            if "Apache" in banner: service_name = "Apache HTTPD"
            elif "nginx" in banner: service_name = "Nginx"
            elif "OpenSSH" in banner: service_name = "OpenSSH"
            
            # Запись инцидента в базу данных Arlechino
            conn = psycopg2.connect(**db_creds)
            cursor = conn.cursor()
            cursor.execute("""
                INSERT INTO vulnerability_scans (target_ip, port, service_name, service_version, exploit_status)
                VALUES (%s, %s, %s, %s, 'NOT_TESTED');
            """, (str(target_ip), port, service_name, banner[:90]))
            conn.commit()
            cursor.close()
            conn.close()
            
            print(f"[+] ОБНАРУЖЕН ОТКРЫТЫЙ ПОРТ -> {target_ip}:{port} [{service_name}]", flush=True)
        sock.close()
    except Exception as e:
        pass

def execute_network_scan(network_cidr):
    """ Разбор сетей и распределение по потокам """
    db_creds = load_db_credentials()
    source_ip = get_local_ip()
    
    print(f"[*] Инициализация сканирования подсети: {network_cidr} из интерфейса {source_ip}", flush=True)
    
    try:
        net = ipaddress.ip_network(network_cidr, strict=False)
        targets = list(net.hosts())
    except Exception as e:
        print(f"[-] Неверный формат сети: {e}", flush=True)
        return

    # Список критических портов для аудита
    ports_to_scan = [21, 22, 23, 80, 443, 445, 3306, 5432, 8080]
    
    # Пул многопоточного исполнения
    with ThreadPoolExecutor(max_workers=50) as executor:
        for target in targets:
            for port in ports_to_scan:
                executor.submit(scan_target_port, source_ip, target, port, db_creds)

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("[-] Ошибка: Укажите целевую подсеть/IP. Пример: 192.168.1.0/24")
        sys.exit(1)
        
    target_net = sys.argv[1]
    execute_network_scan(target_net)
