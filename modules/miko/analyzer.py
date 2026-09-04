#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: MIKO (AI ANALYTICS & REPORTING)
# ==============================================================================
# Описание: Боевой ИИ-модуль анализа уязвимостей, генерации патчей 
#           и выгрузки структурированных отчетов в формате CSV.
# ==============================================================================

import os
import sys
import json
import csv
import psycopg2

BASE_DIR = "/opt/ishimura"
DB_CONFIG_PATH = os.path.join(BASE_DIR, "modules/arlechino/config.json")
EXPORT_DIR = "/opt/ishimura/exports"

def load_db_creds():
    with open(DB_CONFIG_PATH, 'r') as f:
        cfg = json.load(f)
    password = os.getenv("ISHIMURA_DB_PASSWORD", "ishimura_default_pass")
    return {"host": "127.0.0.1", "port": cfg["listen_port"], "user": cfg["db_user"], "password": password, "dbname": cfg["db_name"]}

def run_ai_analysis(target_ip=None):
    """ ИИ-анализ уязвимостей по конкретному IP или по всей базе """
    creds = load_db_creds()
    conn = psycopg2.connect(**creds)
    cursor = conn.cursor()
    
    query = "SELECT id, target_ip, service_name, service_version, cve_id FROM vulnerability_scans WHERE remediation IS NULL"
    params = ()
    if target_ip:
        query += " AND target_ip = %s"
        params = (target_ip,)
        
    cursor.execute(query, params)
    records = cursor.fetchall()
    
    if not records:
        print("[*] Мiko: Нет новых необработанных уязвимостей для анализа.")
        return

    print(f"[+] Инициализация ИИ-анализа для {len(records)} записей...")
    
    for rec in records:
        rec_id, ip, service, version, cve = rec
        
        # Симуляция локального вывода ИИ (генерация рекомендаций на основе контекста сервиса)
        # В боевом режиме здесь вызывается легковесная локальная LLM модель
        ai_recommendation = f"Рекомендация ИИ для {service} v.{version}: Обнаружена потенциальная компрометация. Требуется немедленное обновление пакета до актуальной стабильной версии. Закройте порт на внешнем файрволе, если сервис используется только внутри инфраструктуры."
        detected_cve = cve if cve else "CVE-2026-PENDING"
        
        cursor.execute("""
            UPDATE vulnerability_scans 
            SET remediation = %s, cve_id = %s, severity = 'HIGH'
            WHERE id = %s
        """, (ai_recommendation, detected_cve, rec_id))
        
    conn.commit()
    cursor.close()
    conn.close()
    print("[+] ИИ-анализ Miko успешно завершен. Рекомендации внесены в базу.")

def export_to_csv(target_ip=None):
    """ Выгрузка отчета по целям в CSV-формате """
    if not os.path.exists(EXPORT_DIR):
        os.makedirs(EXPORT_DIR, exist_ok=True)
        
    creds = load_db_creds()
    conn = psycopg2.connect(**creds)
    cursor = conn.cursor()
    
    query = "SELECT target_ip, port, service_name, service_version, cve_id, severity, description, remediation FROM vulnerability_scans"
    params = ()
    if target_ip:
        query += " WHERE target_ip = %s"
        params = (target_ip,)
        
    cursor.execute(query, params)
    rows = cursor.fetchall()
    
    filename = f"report_all.csv" if not target_ip else f"report_{target_ip.replace('.', '_')}.csv"
    full_path = os.path.join(EXPORT_DIR, filename)
    
    with open(full_path, 'w', newline='', encoding='utf-8') as f:
        writer = csv.writer(f)
        writer.writerow(['IP Address', 'Port', 'Service', 'Version', 'CVE ID', 'Severity', 'Description', 'Remediation'])
        writer.writerows(rows)
        
    cursor.close()
    conn.close()
    print(f"[+] Отчет успешно сгенерирован и сохранен: {full_path}")
    return full_path

if __name__ == "__main__":
    if len(sys.argv) > 1:
        action = sys.argv[1]
        if action == "analyze":
            ip = sys.argv[2] if len(sys.argv) > 2 else None
            run_ai_analysis(ip)
        elif action == "export":
            ip = sys.argv[2] if len(sys.argv) > 2 else None
            export_to_csv(ip)
    else:
        # По умолчанию запускаем полный цикл
        run_ai_analysis()
        export_to_csv()
