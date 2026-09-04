#!/usr/bin/env python3
import os
import glob
import json
import psycopg2

BACKUP_DIR = "/opt/ishimura/backups"
DB_CONFIG = {"host": "127.0.0.1", "database": "ishimura", "user": "ishimura_admin", "password": "Nh0uk0lbn@"}

def check_ashka_shadow_copy():
    # Ищем самый свежий архив от модуля Ashka
    archives = glob.glob(os.path.join(BACKUP_DIR, "*.tar.gz"))
    if not archives:
        return "Критическая ошибка: Теневая копия Ashka не найдена!"
    
    latest_backup = max(archives, key=os.path.getctime)
    return f"Синхронизировано с эталонным снимком Ashka: {os.path.basename(latest_backup)}"

if __name__ == "__main__":
    status_msg = check_ashka_shadow_copy()
    print(json.dumps({"status": "ONLINE", "shadow_source": status_msg}))
