/* ==============================================================================
 * SYSTEM ISHIMURA — MODULE: LAMIA (KERNEL PACKET FILTER)
 * ==============================================================================
 * Описание: Низкоуровневый анализатор сетевого трафика. Работает на уровне 
 *           RAW-сокетов, перехватывая пакеты до обработки стандартным файрволом Linux.
 * Компиляция: gcc -O2 lfilter.c -o lfilter
 * ============================================================================== */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <netinet/ip.h>
#include <arpa/inet.h>
#include <unistd.h>

int main() {
    // БЛОК 1: Открытие RAW-сокета для прослушивания всех входящих IP-пакетов
    int sock_raw = socket(AF_INET, SOCK_RAW, IPPROTO_TCP);
    if (sock_raw < 0) {
        perror("[-] Lamia: Критическая ошибка создания сокета перехвата");
        return 1;
    }

    unsigned char *buffer = (unsigned char *) malloc(65536);
    printf("[+] ИИ-модуль Lamia: Низкоуровневый фильтр пакетов успешно инициализирован.\n");

    // БЛОК 2: Бесконечный цикл разбора кадров трафика
    // В демонстрационных целях совершаем 3 итерации перехвата, чтобы не блокировать процесс вывода
    for (int i = 0; i < 3; i++) {
        struct sockaddr saddr;
        int saddr_size = sizeof(saddr);
        
        // Получение пакета из сетевой карты
        int data_size = recvfrom(sock_raw, buffer, 65536, 0, &saddr, (socklen_t*)&saddr_size);
        if (data_size < 0) {
            continue;
        }

        struct iphdr *iph = (struct iphdr*)buffer;
        struct sockaddr_in source;
        memset(&source, 0, sizeof(source));
        source.sin_addr.s_addr = iph->saddr;

        // БЛОК 3: Интеллектуальный анализ аномалий размера (например, атаки типа Ping of Death)
        if (data_size > 1500) {
            printf("[ВНИМАНИЕ] Обнаружен аномальный размер пакета: %d байт от IP: %s\n", data_size, inet_ntoa(source.sin_addr));
        } else {
            printf("[ИНФО] Успешный перехват пакета. Размер: %d байт. Источник: %s\n", data_size, inet_ntoa(source.sin_addr));
        }
    }

    free(buffer);
    close(sock_raw);
    return 0;
}
