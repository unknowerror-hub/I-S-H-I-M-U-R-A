/* ==============================================================================
 * SYSTEM ISHIMURA — MODULE: ARACHNA (RAW-SOCKET ENGINE)
 * ==============================================================================
 * Описание: Высокопроизводительный SYN-сканер на уровне сетевой карты.
 *           Обходит стандартный стек ОС, формируя заголовки пакетов вручную.
 * Компиляция: gcc -O3 scanner.c -o scanner
 * ============================================================================== */

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include <sys/socket.h>
#include <netinet/ip.h>
#include <netinet/tcp.h>
#include <arpa/inet.h>
#include <unistd.h>

/* Псевдо-заголовок для расчета контрольной суммы TCP */
struct pseudo_header {
    u_int32_t source_address;
    u_int32_t dest_address;
    u_int8_t placeholder;
    u_int8_t protocol;
    u_int16_t tcp_length;
};

/* Функция подсчета контрольной суммы (Интернет-контрольная сумма) */
unsigned short csum(unsigned short *ptr, int nbytes) {
    register long sum = 0;
    unsigned short oddbyte;
    register short answer;

    while (nbytes > 1) {
        sum += *ptr++;
        nbytes -= 2;
    }
    if (nbytes == 1) {
        oddbyte = 0;
        *((u_char*)&oddbyte) = *(u_char*)ptr;
        sum += oddbyte;
    }
    sum = (sum >> 16) + (sum & 0xffff);
    sum += (sum >> 16);
    answer = (short)~sum;
    return answer;
}

int main(int argc, char *argv[]) {
    if (argc < 4) {
        printf("[-] Использование: %s <Source_IP> <Target_IP> <Port>\n", argv[0]);
        return 1;
    }

    char *source_ip = argv[1];
    char *target_ip = argv[2];
    int target_port = atoi(argv[3]);

    // БЛОК 1: Создание RAW-сокета с флагом IP_HDRINCL (заголовки создаем сами)
    int s = socket(PF_INET, SOCK_RAW, IPPROTO_TCP);
    if (s < 0) {
        perror("[-] Критическая ошибка: Не удалось инициализировать RAW-сокет");
        return 1;
    }

    int one = 1;
    const int *val = &one;
    if (setsockopt(s, IPPROTO_IP, IP_HDRINCL, val, sizeof(one)) < 0) {
        perror("[-] Ошибка установки опции IP_HDRINCL");
        close(s);
        return 1;
    }

    // Буфер под пакет (IP-заголовок + TCP-заголовок)
    char datagram[4096];
    memset(datagram, 0, 4096);

    struct iphdr *iph = (struct iphdr *) datagram;
    struct tcphdr *tcph = (struct tcphdr *) (datagram + sizeof(struct ip));
    struct sockaddr_in sin;
    struct pseudo_header psh;

    sin.sin_family = AF_INET;
    sin.sin_port = htons(target_port);
    sin.sin_addr.s_addr = inet_addr(target_ip);

    // БЛОК 2: Конструирование IP-заголовка
    iph->ihl = 5;
    iph->version = 4;
    iph->tos = 0;
    iph->tot_len = sizeof(struct ip) + sizeof(struct tcphdr);
    iph->id = htons(54321);
    iph->frag_off = 0;
    iph->ttl = 255;
    iph->protocol = IPPROTO_TCP;
    iph->check = 0;
    iph->saddr = inet_addr(source_ip);
    iph->daddr = sin.sin_addr.s_addr;
    iph->check = csum((unsigned short *) datagram, iph->tot_len >> 1);

    // БЛОК 3: Конструирование TCP SYN-заголовка
    tcph->source = htons(12345); // Исходный порт
    tcph->dest = htons(target_port);
    tcph->seq = htonl(11050249);
    tcph->ack_seq = 0;
    tcph->res1 = 0;
    tcph->doff = 5;
    tcph->fin = 0;
    tcph->syn = 1; // Установка флага SYN для инициализации соединения
    tcph->rst = 0;
    tcph->psh = 0;
    tcph->ack = 0;
    tcph->urg = 0;
    tcph->window = htons(14600);
    tcph->check = 0;
    tcph->urg_ptr = 0;

    // Расчет контрольной суммы TCP с псевдо-заголовком
    psh.source_address = inet_addr(source_ip);
    psh.dest_address = sin.sin_addr.s_addr;
    psh.placeholder = 0;
    psh.protocol = IPPROTO_TCP;
    psh.tcp_length = htons(sizeof(struct tcphdr));

    int psize = sizeof(struct pseudo_header) + sizeof(struct tcphdr);
    char *pseudogram = malloc(psize);
    memcpy(pseudogram, (char*) &psh, sizeof(struct pseudo_header));
    memcpy(pseudogram + sizeof(struct pseudo_header), tcph, sizeof(struct tcphdr));

    tcph->check = csum((unsigned short*) pseudogram, psize);
    free(pseudogram);

    // БЛОК 4: Асинхронная отправка пакета напрямую на уровень сетевой карты
    if (sendto(s, datagram, iph->tot_len, 0, (struct sockaddr *) &sin, sizeof(sin)) < 0) {
        perror("[-] Ошибка отправки пакета");
        close(s);
        return 1;
    }

    close(s);
    return 0;
}
