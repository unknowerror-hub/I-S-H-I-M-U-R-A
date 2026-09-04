<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: TERROR [ACCORDION GROUPING SUITE]
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die("[-] Ошибка СУБД в модуле Terror: " . $e->getMessage());
}

// АСИНХРОННЫЙ АПИ-ОБРАБОТЧИК (AJAX СЛОЙ)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'get_shell_log') {
        $log_file = '/tmp/terror_shell_out.log';
        echo json_encode(["log" => file_exists($log_file) ? file_get_contents($log_file) : "[*] Ожидание подключения к Reverse Shell..."]);
        exit;
    }
    if ($_GET['action'] === 'send_cmd' && isset($_GET['cmd'])) {
        $fifo_pipe = '/tmp/terror_shell_in.fifo';
        if (file_exists($fifo_pipe)) {
            $fd = fopen($fifo_pipe, 'w'); fwrite($fd, trim($_GET['cmd']) . "\n"); fclose($fd);
            echo json_encode(["success" => true]);
        } else { echo json_encode(["success" => false, "error" => "FIFO канал отсутствует."]); }
        exit;
    }
    if ($_GET['action'] === 'run_exploit' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT file_path FROM terror_exploits WHERE id = :id;");
        $stmt->execute(['id' => $_GET['id']]); $path = $stmt->fetchColumn();
        if ($path && file_exists($path)) {
            exec("python3 " . escapeshellarg($path) . " > /dev/null 2>&1 &");
            echo json_encode(["success" => true]);
        } else { echo json_encode(["success" => false, "error" => "Файл скрипта не найден."]); }
        exit;
    }
    if ($_GET['action'] === 'download_file' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT file_path, cve_id FROM terror_exploits WHERE id = :id;");
        $stmt->execute(['id' => $_GET['id']]); $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res && file_exists($res['file_path'])) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="ishimura_' . $res['cve_id'] . '.py"');
            readfile($res['file_path']); exit;
        }
    }
}

// Извлечение и агрегация данных для группировки по IP
$stmt = $pdo->query("SELECT * FROM terror_exploits ORDER BY target_ip, cve_id ASC;");
$raw_exploits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped_terror = [];
foreach ($raw_exploits as $row) {
    $ip = $row['target_ip'];
    if (!isset($grouped_terror[$ip])) {
        $grouped_terror[$ip] = ['total_vectors' => 0, 'items' => []];
    }
    $grouped_terror[$ip]['total_vectors']++;
    $grouped_terror[$ip]['items'][] = $row;
}
?>

<style>
.terror-search-box { width: 100%; padding: 12px; background: #020203; border: 1px solid var(--panel-border); color: var(--neon-cyan); font-family: inherit; font-size: 13px; box-sizing: border-box; }
.terror-search-box:focus { outline: none; border-color: var(--neon-cyan); }
.terror-group-header { background: #111319; border: 1px solid var(--panel-border); padding: 15px; margin-top: 10px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
.terror-group-header:hover { border-color: var(--neon-red); background: rgba(255,0,85,0.01); }
.terror-badge { padding: 3px 8px; font-size: 10px; font-weight: bold; border-radius: 2px; margin-left: 10px; border: 1px solid var(--panel-border); }
.terror-group-body { display: none; background: #07080b; border: 1px solid var(--panel-border); border-top: none; padding: 15px; }
.terror-group-header.open + .terror-group-body { display: block !important; }
.t-chevron::before { content: '▶ '; display: inline-block; transition: transform 0.2s; color: var(--text-muted); }
.terror-group-header.open .t-chevron::before { transform: rotate(90deg); color: var(--neon-magenta); }
</style>

<div class="module-container">
    <h2 class="cyber-title">ATTACK SYNTHESIS MOD: TERROR // БОЕВОЙ СИНТЕЗАТОР</h2>

    <!-- КАНАЛ ЖИВОГО СУПЕРПОИСКА ХОСТОВ -->
    <div class="interactive-console border-neon-green" style="margin-bottom:30px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:20px;">
            <h3 style="margin:0; white-space:nowrap;">Фильтрация ударного контура</h3>
            <input type="text" id="terror_live_search" class="terror-search-box" onkeyup="filterTerrorHosts()" placeholder="⚡ Начните вводить IP-адрес цели для мгновенного поиска векторов...">
        </div>
    </div>

    <!-- КОНТЕЙНЕР СГРУППИРОВАННЫХ КАРТОЧЕК -->
    <div class="border-neon-blue" style="padding:25px; background:var(--panel-bg); margin-bottom:30px;">
        <h3>Реестр скомпилированных боевых векторов (Группировка по целям)</h3>
        <div id="terror_accordion_wrapper">
            <?php if(count($grouped_terror) > 0): ?>
                <?php foreach($grouped_terror as $host_ip => $data): ?>
                    <div class="terror-card-node" data-ip="<?php echo htmlspecialchars($host_ip); ?>" style="margin-bottom: 8px;">
                        
                        <div class="terror-group-header" onclick="this.classList.toggle('open')">
                            <div>
                                <span class="t-chevron"></span>
                                <strong style="color:var(--neon-cyan); font-size:14px; letter-spacing:1px;"><?php echo htmlspecialchars($host_ip); ?></strong>
                                <span class="terror-badge" style="color:var(--neon-yellow); border-color:var(--neon-yellow);"><?php echo $data['total_vectors']; ?> векторов готово</span>
                            </div>
                            <div>
                                <span class="terror-badge" style="color:var(--neon-green); border-color:var(--neon-green);">READY FOR ATTACK</span>
                            </div>
                        </div>

                        <div class="terror-group-body">
                            <table class="cyber-table" style="font-size:11px; margin-top:0; width:100%;">
                                <thead>
                                    <tr>
                                        <th style="width:130px;">Сигнатура (CVE)</th>
                                        <th style="width:90px;">Тип контура</th>
                                        <th>Техническое описание вектора доставки</th>
                                        <th style="width:170px; text-align:center;">Действие</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($data['items'] as $item): ?>
                                        <tr>
                                            <td style="color:var(--neon-yellow); font-weight:bold; font-size:12px;"><?php echo htmlspecialchars($item['cve_id']); ?></td>
                                            <td>
                                                <span style="color: <?php echo $item['exploit_type'] === 'GENERATED' ? 'var(--neon-green)' : 'var(--neon-magenta)'; ?>; font-weight:bold;">
                                                    <?php echo htmlspecialchars($item['exploit_type']); ?>
                                                </span>
                                            </td>
                                            <td style="color:#cbd5e1; white-space:normal; line-height:1.4;"><?php echo htmlspecialchars($item['description']); ?></td>
                                            <td style="text-align:center;">
                                                <button onclick="executeExploit(<?php echo $item['id']; ?>)" class="btn-export" style="color:var(--neon-green); border-color:var(--neon-green); margin-right:5px;">ЗАПУСК</button>
                                                <button onclick="downloadExploit(<?php echo $item['id']; ?>)" class="btn-export">СКАЧАТЬ</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; padding:20px;" class="text-muted">Реестр эксплоитов пуст. Запустите аудит сети в модуле Arachna.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ИНТЕРАКТИВНЫЙ ВЕБ-ТЕРМИНАЛ REVERSE SHELL СЕССИЙ -->
    <div class="interactive-console border-neon-green">
        <h3>Интерактивная консоль перехваченных сессий (Real-Time Reverse Shell)</h3>
        <div class="console-output">
            <pre id="terror_shell_box" class="output-box" style="height:220px; background:#020204; color:var(--neon-green); overflow-y:auto; font-size:11px; font-family:'Consolas', monospace; white-space:pre-wrap;">[*] Сессии управления отсутствуют. Ожидание входящих сокет-подключений...</pre>
        </div>
        <div style="margin-top:10px; display:flex; gap:10px;">
            <input type="text" id="terror_cmd_input" class="cyber-textarea" style="height:40px; width:85%; color:var(--neon-cyan); font-weight:bold;" placeholder="whoami && cat /etc/passwd" onkeydown="if(event.key === 'Enter') sendShellCommand()">
            <button type="button" onclick="sendShellCommand()" class="cyber-btn" style="width:15%; height:40px; font-size:11px;">SEND</button>
        </div>
    </div>
</div>

<script>
let shellInterval = null;

document.addEventListener("DOMContentLoaded", function() {
    shellInterval = setInterval(updateShellTerminalLog, 1000);
});

// ФУНКЦИЯ ДИНАМИЧЕСКОГО ЖИВОГО ФИЛЬТРА ХОСТОВ НА ЛЕТУ
function filterTerrorHosts() {
    const query = document.getElementById('terror_live_search').value.toLowerCase().trim();
    document.querySelectorAll('.terror-card-node').forEach(node => {
        const ip = node.getAttribute('data-ip').toLowerCase();
        node.style.display = ip.includes(query) ? 'block' : 'none';
    });
}

function updateShellTerminalLog() {
    fetch('modules/terror.php?action=get_shell_log')
        .then(res => res.json())
        .then(data => {
            const box = document.getElementById('terror_shell_box');
            if(data.log.trim() !== "") {
                box.innerHTML = data.log;
                box.scrollTop = box.scrollHeight;
            }
        });
}

function sendShellCommand() {
    const input = document.getElementById('terror_cmd_input');
    const cmd = input.value.trim();
    if(!cmd) return;
    
    fetch(`modules/terror.php?action=send_cmd&cmd=${encodeURIComponent(cmd)}`)
        .then(res => res.json())
        .then(data => {
            if(data.success) { input.value = ""; }
        });
}

function executeExploit(id) {
    if(!confirm('Запустить реальное тестирование безопасности эксплоитом?')) return;
    fetch(`modules/terror.php?action=run_exploit&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if(data.success) {
                alert('[+] Эксплоит запущен в фоновом режиме ядра. Следите за окном интерактивного шелла!');
            } else {
                alert('[-] Сбой запуска: ' + data.error);
            }
        });
}

function downloadExploit(id) {
    window.location.href = `modules/terror.php?action=download_file&id=${id}`;
}
</script>
