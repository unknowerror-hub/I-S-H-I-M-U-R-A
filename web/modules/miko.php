<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: MIKO
 * ==============================================================================
 * Описание: Панель ИИ-анализа. Поиск по IP, структурированный вывод 
 *           рекомендаций и скачивание отчетов в CSV.
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

$search_ip = isset($_POST['search_ip']) ? trim($_POST['search_ip']) : '';
$terminal_out = "";

// Обработка запуска ИИ-анализатора
if (isset($_POST['trigger_miko'])) {
    $cmd = "sudo /usr/bin/python3 /opt/ishimura/modules/miko/analyzer.py analyze " . escapeshellarg($search_ip) . " 2>&1";
    $terminal_out .= shell_exec($cmd);
}

// Обработка генерации отчета
if (isset($_POST['trigger_export'])) {
    $cmd = "sudo /usr/bin/python3 /opt/ishimura/modules/miko/analyzer.py export " . escapeshellarg($search_ip) . " 2>&1";
    $terminal_out .= shell_exec($cmd);
}

// Загрузка результатов для таблицы
try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    if (!empty($search_ip)) {
        $stmt = $pdo->prepare("SELECT * FROM vulnerability_scans WHERE target_ip = :ip ORDER BY scan_time DESC");
        $stmt->execute(['ip' => $search_ip]);
    } else {
        $stmt = $pdo->query("SELECT * FROM vulnerability_scans ORDER BY scan_time DESC LIMIT 20");
    }
    $scans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $scans = [];
}
?>

<div class="module-container">
    <h2 class="cyber-title">AI ANALYTICS MOD: MIKO // РЕЗОНАТОР МЫСЛЕЙ</h2>

    <!-- ФИЛЬТР ПОИСКА -->
    <div class="interactive-console border-neon-blue" style="margin-bottom: 20px;">
        <h3>Поиск и фильтрация целей</h3>
        <form method="POST" action="">
            <div style="display: flex; gap: 15px;">
                <input type="text" name="search_ip" class="cyber-textarea" style="height: 45px; width:70%;" placeholder="Введите IP адрес хоста..." value="<?php echo htmlspecialchars($search_ip); ?>">
                <button type="submit" class="cyber-btn" style="height: 45px; width:30%;">ФИЛЬТРОВАТЬ</button>
            </div>
            <div style="margin-top: 15px; display: flex; gap: 10px;">
                <button type="submit" name="trigger_miko" class="cyber-btn btn-sm">Запустить ИИ-анализ</button>
                <button type="submit" name="trigger_export" class="cyber-btn btn-sm" style="color:var(--neon-yellow); border-color:var(--neon-yellow);">Сгенерировать CSV-отчет</button>
            </div>
        </form>
    </div>

    <!-- ТЕРМИНАЛЬНЫЙ ВЫВОД ХОДА ИИ -->
    <?php if(!empty($terminal_out)): ?>
    <div class="border-neon-purple" style="padding:15px; margin-bottom:20px; background:#000;">
        <h4 style="margin:0 0 10px 0; color:var(--neon-purple);">Вывод аналитического ядра Miko:</h4>
        <pre style="color:var(--text-color); margin:0; font-size:12px;"><?php echo htmlspecialchars($terminal_out); ?></pre>
    </div>
    <?php endif; ?>

    <!-- ТАБЛИЦА РЕЗУЛЬТАТОВ -->
    <div class="border-neon-green" style="padding: 20px;">
        <h3>Структурированные уязвимости и патчи</h3>
        <div style="overflow-x: auto;">
            <table class="cyber-table">
                <thead>
                    <tr>
                        <th>IP хоста</th>
                        <th>Порт</th>
                        <th>Сервис / Версия</th>
                        <th>Угроза (CVE)</th>
                        <th>Рекомендация ИИ по устранению (Патч)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($scans) > 0): ?>
                        <?php foreach($scans as $scan): ?>
                            <tr>
                                <td style="color:var(--neon-cyan);"><?php echo htmlspecialchars($scan['target_ip']); ?></td>
                                <td><?php echo htmlspecialchars($scan['port']); ?></td>
                                <td><strong><?php echo htmlspecialchars($scan['service_name']); ?></strong><br><small style="color:#666;"><?php echo htmlspecialchars($scan['service_version']); ?></small></td>
                                <td style="color:var(--neon-magenta);"><?php echo htmlspecialchars($scan['cve_id'] ?? 'АНАЛИЗ ТРЕБУЕТСЯ'); ?></td>
                                <td style="font-size:12px; color:#a0b0a0;"><?php echo htmlspecialchars($scan['reremediation'] ?? 'Запустите ИИ-анализатор для выработки решений...'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;" class="text-muted">Данные по целям отсутствуют в базе СУБД.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
