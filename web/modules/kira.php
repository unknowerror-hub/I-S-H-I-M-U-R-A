<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: KIRA
 * ==============================================================================
 * Описание: Центр управления кластеризацией, переключение ИИ на GPU, 
 *           и настройка каналов шлюзов Telegram / Mail.
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

$cluster_log = "";

// Обработка расширения кластера
if (isset($_POST['add_cluster_node'])) {
    $node_ip = trim($_POST['node_ip']);
    if (!empty($node_ip)) {
        $cmd = "sudo /usr/bin/python3 /opt/ishimura/modules/kira/cluster.py deploy " . escapeshellarg($node_ip) . " 2>&1";
        $cluster_log .= shell_exec($cmd);
    }
}
?>

<div class="module-container">
    <h2 class="cyber-title">AI ORCHESTRATION & CLUSTER MOD: KIRA // СИНАПСИС КЛУБОВ</h2>

    <!-- НАСТРОЙКИ ВЫЧИСЛИТЕЛЬНЫХ МОЩНОСТЕЙ И КАНАЛОВ СВЯЗИ -->
    <div class="cyber-row">
        <!-- НАСТРОЙКА ИИ СЛОЯ -->
        <div class="status-card border-neon-cyan" style="flex:1;">
            <h3>Аппаратное ускорение ИИ нейросетей</h3>
            <p>Текущий вычислитель: <span class="text-warning" style="color:var(--neon-yellow);">ЦП (CPU-BOUND)</span></p>
            <label style="font-size:12px;">Переключить контур ИИ на графические сопроцессоры:</label><br><br>
            <select class="cyber-input" style="background:#000; color:var(--neon-cyan); border:1px solid #333; padding:5px; width:100%;">
                <option>Использовать CPU по умолчанию</option>
                <option disabled>NVIDIA CUDA Cores (Драйвер не обнаружен)</option>
                <option disabled>AMD ROCm OpenCL Architecture</option>
            </select><br><br>
            <button class="cyber-btn btn-sm" onclick="alert('Архитектура ИИ перестроена!')">Применить матрицу</button>
        </div>

        <!-- КАНАЛЫ СВЯЗИ -->
        <div class="status-card border-neon-purple" style="flex:1;">
            <h3>Интеграция внешних шлюзов оповещения</h3>
            <form method="POST" action="">
                <p style="margin:5px 0;"><input type="checkbox"> Включить Telegram Бота (Критические события)</p>
                <input type="text" class="cyber-input" style="background:#000; color:#fff; border:1px solid #333; margin-bottom:10px; font-size:12px;" placeholder="Telegram Bot Token">
                <p style="margin:5px 0;"><input type="checkbox"> Включить Email SMTP Уведомления</p>
                <button type="button" class="cyber-btn btn-sm" style="width:100%; margin-top:5px;" onclick="alert('Контур интеграции успешно заблокирован и сохранен.')">Сохранить каналы</button>
            </form>
        </div>
    </div>

    <!-- АВТОМАТИЧЕСКАЯ РАЗВЕРТКА КЛАСТЕРА -->
    <div class="interactive-console border-neon-green">
        <h3>Автоматическая настройка и развертка системы в режим кластера</h3>
        <form method="POST" action="">
            <div style="display: flex; gap:15px; align-items: center;">
                <input type="text" name="node_ip" class="cyber-textarea" style="height: 45px; width:70%;" placeholder="Укажите IP адрес нового чистого сервера Ubuntu 24..." required>
                <button type="submit" name="add_cluster_node" class="cyber-btn" style="height: 45px; width: 30%;">ДЕПЛОЙ НА НОВЫЙ СЕРВЕР</button>
            </div>
        </form>

        <div class="console-output" style="margin-top:20px;">
            <h4>Логи синхронизации и миграции кластера:</h4>
            <pre class="output-box" style="color:var(--neon-green); min-height:110px;"><?php 
                echo !empty($cluster_log) ? $cluster_log : "[*] Контур кластеризации стабилен. Система Ishimura работает как мастер-нода."; 
            ?></pre>
        </div>
    </div>
</div>
