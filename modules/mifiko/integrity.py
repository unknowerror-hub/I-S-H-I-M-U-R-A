#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: MIFIKO (INTEGRITY & AUTO-RECOVERY CORE)
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
    if not os.path.exists(SHADOW_DIR):
        return []
    altered_files = []
    for root, dirs, files in os.walk(SHADOW_DIR):
        for file in files:
            shadow_file_path = os.path.join(root, file)
            rel_path = os.path.relpath(shadow_file_path, SHADOW_DIR)
            working_file_path = os.path.join(BASE_DIR, rel_path)
            
            if not os.path.exists(working_file_path):
                altered_files.append({"file": rel_path, "type": "DELETED"})
                continue
            if get_file_hash(shadow_file_path) != get_file_hash(working_file_path):
                altered_files.append({"file": rel_path, "type": "MODIFIED"})
    return altered_files

def restore_corrupted_file(rel_path):
    shadow_source = os.path.join(SHADOW_DIR, rel_path)
    working_dest = os.path.join(BASE_DIR, rel_path)
    if os.path.exists(shadow_source):
        os.makedirs(os.path.dirname(working_dest), exist_ok=True)
        shutil.copy2(shadow_source, working_dest)
        return True
    return False

if __name__ == "__main__":
    if len(sys.argv) > 2 and sys.argv[1] == "repair":
        success = restore_corrupted_file(sys.argv[2])
        print(json.dumps({"status": "repaired" if success else "failed", "file": sys.argv[2]}))
    else:
        mutations = audit_system_files()
        print(json.dumps({"status": "scanned", "anomalies": len(mutations)}))
