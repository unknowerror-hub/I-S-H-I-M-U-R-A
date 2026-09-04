<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: LAMIA
 * ==============================================================================
 * Описание: Панель ИИ-защиты. Отображение черных/белых списков, попыток
 *           деструктивного воздействия и логов RAW-фильтра.
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

$protection_log = "";

// Принудительный запуск синхронизации белых списков
if (isset($_POST['sync_defense'])) {
    $cmd_py = "sudo /usr/bin/python3 /opt/ishimura/modules/lamia/core.py 2>&1";
    $cmd_c = "sudo /opt/ishimura/modules/lamia/lfilter 2>&1";
    
    $protection_log .= shell_exec($cmd_py);
    $protection_log .= "\n=== Запуск RAW-фильтра пакетов ядра ===\n";
    $protection_log .= shell_exec($cmd_c);
}
?>

<div class="module-container">
    <h2 class="cyber-title">AI IPS DETECTOR MOD: LAMIA // ЩИТ ЯДРА</h2>

    <!-- ОТОБРАЖЕНИЕ СПИСКОВ И ФИЛЬТРОВ -->
    <div class="cyber-row">
        <!-- БЕЛЫЙ СПИСОК -->
        <div class="status-card border-neon-green" style="flex: 1;">
            <h3>Доверенный контур (Белый список)</h3>
            <ul style="list-style: none; padding: 0; font-size: 13px; color: var(--neon-green);">
                <li>✓ 127.0.0.1 (Локальный стек)</li>
                <li>✓ IP сервера Ishimura Core (Динамический)</li>
            </ul>
            <p style="font-size: 11px; color: #555;">Все внутренние IP-адреса модулей защищены от ложных срабатываний ИИ-алгоритмов блокировки.</p>
        </div>

        <!-- ЧЕРНЫЙ СПИСОК -->
        <div class="status-card border-neon-red" style="flex: 1;">
            <h3>Враждебный контур (Черный список)</h3>
            <p style="font-size: 13px; color: var(--neon-magenta);">[АКТИВНЫХ УГРОЗ СЕЙЧАС НЕТ]</p>
            <p style="font-size: 11px; color: #555;">ИИ осуществляет постоянный мониторинг деструктивной сетевой активности без жестких перманентных банов.</p>
        </div>
    </div>

    <!-- КОНСОЛЬ МОНИТОРИНГА И ЛОГОВ -->
    <div class="interactive-console border-neon-yellow">
        <h3>Интерактивная панель предотвращения вторжений (Real-Time RAW Analysis)</h3>
        <form method="POST" action="">
            <button type="submit" name="sync_defense" class="cyber-btn" style="width: 100%;">
                Запустить потоковый анализ кадра трафика сетевой карты
            </button>
        </form>

        <div class="console-output" style="margin-top: 20px;">
            <h4>Терминальный лог перехвата пакетов и ИИ-анализа:</h4>
            <pre class="output-box" style="color: var(--neon-yellow); min-height: 140px; max-height: 250px; overflow-y: auto;"><?php 
                echo !empty($protection_log) ? htmlspecialchars($protection_log) : "[*] Модуль Lamia находится в режиме ожидания сетевых пакетов на интерфейсе ядра..."; 
            ?></pre>
        </div>
    </div>
</div>
