#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: ORACULUS (HARDWARE & NETWORK CONFIGURATOR)
# ==============================================================================
# Описание: Боевой модуль автоматического сканирования оборудования сервера,
#           сетевых карт и динамического изменения IP-параметров системы.
# ==============================================================================

import os
import sys
import json
import socket

BASE_DIR = "/opt/ishimura"
CONFIG_PATH = os.path.join(BASE_DIR, "modules/oraculus/config.json")

def get_cpu_model():
    """ Получение коммерческого наименования процессора из /proc/cpuinfo """
    if os.path.exists('/proc/cpuinfo'):
        with open('/proc/cpuinfo', 'r') as f:
            for line in f:
                if "model name" in line:
                    return line.split(':', 1)[1].strip()
    return "Unknown Central Processor"

def get_network_interfaces():
    """ Сканирование доступных физических и виртуальных сетевых интерфейсов """
    interfaces = {}
    net_dir = '/sys/class/net/'
    if os.path.exists(net_dir):
        for iface in os.listdir(net_dir):
            # Пропускаем loopback интерфейс
            if iface == 'lo':
                continue
            
            # Чтение MAC-адреса
            mac_path = os.path.join(net_dir, iface, 'address')
            mac = "00:00:00:00:00:00"
            if os.path.exists(mac_path):
                with open(mac_path, 'r') as f:
                    mac = f.read().strip()
            
            # Чтение состояния линка (up/down)
            oper_path = os.path.join(net_dir, iface, 'operstate')
            state = "unknown"
            if os.path.exists(oper_path):
                with open(oper_path, 'r') as f:
                    state = f.read().strip()

            interfaces[iface] = {
                "mac": mac,
                "state": state.upper()
            }
    return interfaces

def collect_system_specs():
    """ Сборка полной спецификации оборудования в JSON-формат """
    specs = {
        "cpu": get_cpu_model(),
        "interfaces": get_network_interfaces()
    }
    print(json.dumps(specs, indent=2))
    return specs

if __name__ == "__main__":
    collect_system_specs()
