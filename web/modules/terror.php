<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: TERROR
 * ==============================================================================
 * Описание: Ударный терминал. Синтез боевых .py скриптов, линки GitHub 
 *           и симуляция захвата интерактивной консоли (Reverse Shell).
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

$action_log = "";
$active_shell = false;

// Обработка сборки эксплоита
if (isset($_POST['build_exploit'])) {
    $t_ip = trim($_POST['exploit_ip']);
    $t_cve = trim($_POST['exploit_cve']);
    
    if(!empty($t_ip) && !empty($t_cve)) {
        $cmd = "sudo /usr/bin/python3 /opt/ishimura/modules/terror/exploit_manager.py " . escapeshellarg($t_ip) . " " . escapeshellarg($t_cve) . " 2>&1";
        $action_log .= shell_exec($cmd);
        $active_shell = true; // Активируем терминал сессии
    }
}
?>

<div class="module-container">
    <h2 class="cyber-title">ATTACK SYNTHESIS MOD: TERROR // БОЕВОЙ СИНТЕЗАТОР</h2>

    <div class="cyber-row">
        <!-- БЛОК СИНТЕЗА -->
        <div class="status-card border-neon-red" style="flex:1;">
            <h3>Параметры компиляции вектора атаки</h3>
            <form method="POST" action="">
                <div style="margin-bottom:10px;">
                    <label>IP Целевой системы:</label>
                    <input type="text" name="exploit_ip" class="cyber-input" style="background:#000; color:var(--neon-cyan); border:1px solid #333;" placeholder="192.168.1.50" required>
                </div>
                <div style="margin-bottom:15px;">
                    <label>Вектор CVE / База:</label>
                    <input type="text" name="exploit_cve" class="cyber-input" style="background:#000; color:var(--neon-cyan); border:1px solid #333;" placeholder="CVE-2024-3847" required>
                </div>
                <button type="submit" name="build_exploit" class="cyber-btn" style="width:100%;">СГЕНЕРИРОВАТЬ И НАЙТИ POC</button>
            </form>
        </div>

        <!-- ОПИСАНИЕ СТАТУСА -->
        <div class="status-card border-neon-purple" style="flex:1;">
            <h3>Статус ударного ядра</h3>
            <p>Директория хранения .py: <code style="color:var(--neon-cyan);">/opt/ishimura/exploits/</code></p>
            <p>Активных сессий перехвата: <span style="color:var(--neon-green);"><?php echo $active_shell ? '1 ОБНАРУЖЕНА' : '0 СЕССИЙ'; ?></span></p>
            <p style="font-size:11px; color:#555;">Внимание! Скрипты генерируются в реальном исполнении и готовы к ручному запуску из каталога.</p>
        </div>
    </div>

    <!-- ИНТЕРАКТИВНАЯ СЕССИЯ (REVERSE SHELL) -->
    <div class="interactive-console border-neon-green">
        <h3>Интерактивная консоль перехваченных сессий (Reverse Shell)</h3>
        
        <div class="console-output">
            <div class="output-box" style="background:#020205; color:var(--neon-green); height:220px; overflow-y:auto;">
                <?php if(!empty($action_log)): ?>
                    <?php echo nl2br(htmlspecialchars($action_log)); ?>
                    <br>
                    <span style="color:#fff;">[+] ОТКРЫТА ИНТЕРАКТИВНАЯ СЕССИЯ С ПОРАЖЕННОЙ ЦЕЛЬЮ. ВВЕДИТЕ КОМАНДУ:</span><br>
                    <span style="color:var(--neon-cyan);">root@target_compromised:~#</span> _
                <?php else: ?>
                    <span style="color:#444;">[Ожидание активации атаки. Сессии управления отсутствуют]</span>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if($active_shell): ?>
        <div style="margin-top:10px; display:flex;">
            <input type="text" class="cyber-textarea" style="height:40px; width:85%; color:var(--neon-cyan);" placeholder="whoami && cat /etc/passwd">
            <button class="cyber-btn" style="width:15%; height:40px;" onclick="alert('Команда отправлена на целевой хост через сокет!')">SEND</button>
        </div>
        <?php endif; ?>
    </div>
</div>
