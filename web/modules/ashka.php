<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: ASHKA
 * ==============================================================================
 * Описание: Интерфейс управления бэкапами. Запуск генерации теневых слепков,
 *           очистка хранилищ, отображение прогресса создания копий.
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

$ashka_log = "";
$progress_val = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['make_shadow'])) {
        $cmd = "sudo /usr/bin/python3 /opt/ishimura/modules/ashka/backup.py init 2>&1";
        $ashka_log .= shell_exec($cmd);
        $progress_val = 100;
    } elseif (isset($_POST['wipe_shadow'])) {
        $cmd = "sudo /usr/bin/python3 /opt/ishimura/modules/ashka/backup.py clear 2>&1";
        $ashka_log .= shell_exec($cmd);
        $progress_val = 0;
    }
}

// Загрузка количества отслеживаемых файлов из базы
try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_backups WHERE backup_type = 'SHADOW_SNAPSHOT'");
    $total_shadow_files = $stmt->fetchColumn();
} catch (Exception $e) {
    $total_shadow_files = 0;
}
?>

<div class="module-container">
    <h2 class="cyber-title">SHADOW BACKUP MOD: ASHKA // СЛЕПОК ПАМЯТИ</h2>

    <!-- ФУНКЦИОНАЛЬНЫЕ ПАНЕЛИ -->
    <div class="cyber-row">
        <div class="status-card border-neon-blue">
            <h3>Состояние теневого зеркала</h3>
            <p><strong>Защищено системных файлов:</strong> <span style="color:var(--neon-green);"><?php echo $total_shadow_files; ?> объектов</span></p>
            <p>Интервал автопроверки: каждые 10 минут</p>
            
            <form method="POST" action="" style="margin-top:15px; display:flex; gap:10px;">
                <button type="submit" name="make_shadow" class="cyber-btn btn-sm">Пересоздать теневой слепок</button>
                <button type="submit" name="wipe_shadow" class="cyber-btn btn-sm" style="color:var(--neon-magenta); border-color:var(--neon-magenta);">Удалить все слепки</button>
            </form>
        </div>

        <div class="status-card border-neon-purple">
            <h3>Целостность резервного контура</h3>
            <p>Директория слепков: <code style="color:var(--neon-cyan);">/opt/ishimura/.shadow_storage/</code></p>
            <p>Статус подсистемы контроля: <span class="status-indicator online">АКТИВНА</span></p>
        </div>
    </div>

    <!-- ТЕРМИНАЛ И ПРОГРЕСС -->
    <div class="interactive-console border-neon-green">
        <h3>Ход выполнения дисковых операций</h3>
        
        <div style="margin: 15px 0;">
            <label style="font-size:12px;">Прогресс-бар синхронизации разделов:</label>
            <div style="width: 100%; background: #000; height: 15px; border: 1px solid var(--neon-green); margin-top:5px;">
                <div style="width: <?php echo $progress_val; ?>%; background: var(--neon-green); height: 100%; transition: width 1s ease;"></div>
            </div>
        </div>

        <h4>Вывод подсистемы Ashka:</h4>
        <pre class="output-box" style="color:var(--neon-green); min-height:80px;"><?php 
            echo !empty($ashka_log) ? htmlspecialchars($ashka_log) : "[*] Модуль Ashka активен. Теневой деструктор и инкрементальный планировщик взведены."; 
        ?></pre>
    </div>
</div>
