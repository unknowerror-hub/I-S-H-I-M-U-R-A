#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: MIFIKO (INTEGRITY & AUTO-RECOVERY CORE)
# ==============================================================================
# Описание: ИИ-мониторинг целостности кода, обнаружение скрытых модификаций,
#           аудит повышения прав и горячее восстановление файлов системы.
# ==============================================================================

import os
import sys
import json
import shutil
import hashlib

BASE_DIR = "/opt/ishimura"
SHADOW_DIR = "/opt/ishimura/.shadow_storage"

def get_file_hash(path):
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

def audit_system_files():
    """ Поиск расхождений и модификаций файлов в сравнении с теневым слепком """
    print("[*] Mifiko: Запуск циклического ИИ-анализа целостности структуры кода...")
    
    if not os.path.exists(SHADOW_DIR):
        print("[-] Ошибка: Базовый теневой слепок Ashka отсутствует. Проверка невозможна.")
        return []

    altered_files = []

    for root, dirs, files in os.walk(SHADOW_DIR):
        for file in files:
            shadow_file_path = os.path.join(root, file)
            rel_path = os.path.relpath(shadow_file_path, SHADOW_DIR)
            working_file_path = os.path.join(BASE_DIR, rel_path)
            
            # Проверяем, существует ли рабочий файл
            if not os.path.exists(working_file_path):
                print(f"[УГРОЗА] Рабочий файл удален или скрыт: {rel_path}")
                altered_files.append({"file": rel_path, "type": "DELETED"})
                continue
                
            # Проверяем хэши файлов
            if get_file_hash(shadow_file_path) != get_file_hash(working_file_path):
                print(f"[УГРОЗА] Обнаружена модификация тела файла: {rel_path}")
                altered_files.append({"file": rel_path, "type": "MODIFIED"})

    if not altered_files:
        print("[+] Контур Mifiko: Структурных отклонений в кодовой базе не обнаружено. 100% Валидность.")
    return altered_files

def restore_corrupted_file(rel_path):
    """ Принудительное перезаписывание поврежденного файла оригиналом из тени """
    shadow_source = os.path.join(SHADOW_DIR, rel_path)
    working_dest = os.path.join(BASE_DIR, rel_path)
    
    if os.path.exists(shadow_source):
        os.makedirs(os.path.dirname(working_dest), exist_ok=True)
        shutil.copy2(shadow_source, working_dest)
        print(f"[+] Восстановление завершено: {rel_path} успешно реанимирован.")
        return True
    return False

if __name__ == "__main__":
    if len(sys.argv) > 2 and sys.argv == "repair":
        restore_corrupted_file(sys.argv)
    else:
        audit_system_files()
