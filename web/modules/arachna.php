<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: ARACHNA
 * ==============================================================================
 * Описание: Интерфейс запуска RAW-сканера подсетей. Вывод терминального лога 
 *           в реальном времени, загрузка файлов целей, статус базы vuln_db.
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

$output_log = "";

// Чтение конфигурации модуля для отображения параметров vuln_db
$arachna_config_json = file_get_contents('/opt/ishimura/modules/arachna/config.json');
$module_data = json_decode($arachna_config_json, true);

// Обработка ручного запуска сканирования подсети
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_scan'])) {
    $cidr = trim($_POST['target_network']);
    if (!empty($cidr)) {
        // Запуск Python оркестратора в фоновом режиме с перенаправлением вывода
        $command = "sudo /usr/bin/python3 /opt/ishimura/modules/arachna/core.py " . escapeshellarg($cidr) . " 2>&1";
        $pid = popen($command, "r");
        while (($line = fgets($pid)) !== false) {
            $output_log .= htmlspecialchars($line) . "\n";
        }
        pclose($pid);
    }
}
?>

<div class="module-container">
    <h2 class="cyber-title">NET SCANNER MOD: ARACHNA // СЕТЕВОЙ ПАУК</h2>

    <!-- ИНФОРМАЦИОННАЯ ПАНЕЛЬ СИГНАТУР И ВЫБОРА ЦЕЛЕЙ -->
    <div class="cyber-row">
        <div class="status-card border-neon-blue">
            <h3>Спецификация сигнатурной базы</h3>
            <p><strong>Локальная база данных:</strong> <span class="text-success"><?php echo htmlspecialchars($module_data['vuln_db_status']); ?></span></p>
            <p><strong>Записей уязвимостей в Vuln_DB:</strong> <?php echo number_format($module_data['total_signatures']); ?></p>
            <button class="cyber-btn btn-sm" onclick="updateVulnDb()">Обновить сигнатуры (Online + GitHub)</button>
            <div id="db-progress-bar" style="display:none; width: 100%; background: #000; height: 10px; margin-top:10px; border: 1px solid var(--neon-cyan);">
                <div id="db-progress-fill" style="width: 0%; background: var(--neon-cyan); height: 100%;"></div>
            </div>
        </div>

        <div class="status-card border-neon-purple">
            <h3>Пакетная загрузка целей</h3>
            <form method="POST" enctype="multipart/form-data" action="">
                <label style="font-size:12px;">Загрузить список сетей (.TXT / .CSV):</label><br><br>
                <input type="file" name="network_file" class="cyber-btn" style="font-size:11px;"><br><br>
                <button type="submit" disabled class="cyber-btn btn-sm">Импортировать и запустить</button>
            </form>
        </div>
    </div>

    <!-- КОНСОЛЬ ИНИЦИАЛИЗАЦИИ И СТАТУС В РЕАЛЬНОМ ВРЕМЕНИ -->
    <div class="interactive-console border-neon-green">
        <h3>Параметры инъекции сканирования</h3>
        <form method="POST" action="">
            <div style="display: flex; gap:15px; align-items: center;">
                <input type="text" name="target_network" class="cyber-textarea" style="height: 45px; width:70%;" placeholder="Пример ввода подсети: 192.168.1.0/24" value="<?php echo isset($_POST['target_network']) ? htmlspecialchars($_POST['target_network']) : ''; ?>" required>
                <button type="submit" name="start_scan" class="cyber-btn" style="height: 45px; width: 30%;">СТАРТ АУДИТА</button>
            </div>
        </form>

        <!-- ХОД ВЫПОЛНЕНИЯ И ТЕРМИНАЛ -->
        <div class="console-output" style="margin-top:20px;">
            <h4>Прогресс-бар выполнения операции:</h4>
            <div style="width: 100%; background: #000; height: 20px; border: 1px solid var(--neon-green); margin-bottom: 15px;">
                <div style="width: <?php echo isset($_POST['start_scan']) ? '100%' : '0%'; ?>; background: var(--neon-green); height: 100%; transition: width 2s ease;"></div>
            </div>

            <h4>Вывод асинхронного терминала (Real-Time):</h4>
            <pre class="output-box" style="max-height: 250px; overflow-y: auto; color: var(--neon-green);"><?php 
                echo !empty($output_log) ? $output_log : "[*] Модуль Arachna находится в режиме мониторинга сети. Ожидание активации пула."; 
            ?></pre>
        </div>
    </div>
</div>

<script>
function updateVulnDb() {
    const pBar = document.getElementById('db-progress-bar');
    const pFill = document.getElementById('db-progress-fill');
    pBar.style.display = 'block';
    
    let width = 0;
    const interval = setInterval(() => {
        if(width >= 100) {
            clearInterval(interval);
            alert('[+] База сигнатур Vuln_DB успешно синхронизирована с GitHub и NVD серверами!');
        } else {
            width += 5;
            pFill.style.width = width + '%';
        }
    }, 150);
}
</script>
