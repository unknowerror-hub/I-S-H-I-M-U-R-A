<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) { die("[-] СУБД Error: " . $e->getMessage()); }

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'get_progress') {
        $f = '/opt/ishimura/modules/arachna/arachna_progress.json';
        echo file_exists($f) ? file_get_contents($f) : json_encode(["percent" => 100, "status" => "Готов."]);
        exit;
    }
    if ($_GET['action'] === 'get_terminal_log') {
        $f = '/opt/ishimura/modules/arachna/arachna_terminal.log';
        echo json_encode(["log" => file_exists($f) ? file_get_contents($f) : "[*] Лог пуст."]);
        exit;
    }
    if ($_GET['action'] === 'start_scan' && isset($_POST['target'])) {
        @unlink('/opt/ishimura/modules/arachna/arachna_progress.json'); @unlink('/opt/ishimura/modules/arachna/arachna_terminal.log');
        $t = escapeshellarg(trim($_POST['target']));
        exec("nohup /usr/bin/python3 /opt/ishimura/modules/arachna/core.py $t && /usr/bin/python3 /opt/ishimura/modules/terror/exploit_manager.py > /dev/null 2>&1 &");
        echo json_encode(["started" => true]); exit;
    }
    if ($_GET['action'] === 'clear_history') {
        $pdo->exec("TRUNCATE TABLE vulnerability_scans RESTART IDENTITY CASCADE;");
        echo json_encode(["success" => true, "msg" => "Архив очищен."]); exit;
    }
}

$stmt = $pdo->query("SELECT id, target_ip, target_domain, port, service_name, service_version, severity, cve_id, to_char(scan_time, 'YYYY-MM-DD HH24:MI:SS') as formatted_time FROM vulnerability_scans ORDER BY scan_time DESC, port ASC;");
$raw_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped_history = [];
foreach ($raw_history as $row) {
    $ip = $row['target_ip'];
    if (!isset($grouped_history[$ip])) {
        $grouped_history[$ip] = ['domain' => $row['target_domain'] !== 'N/A' ? $row['target_domain'] : '', 'total_ports' => 0, 'max_severity' => 'INFO', 'items' => []];
    }
    $grouped_history[$ip]['total_ports']++;
    if ($row['severity'] === 'CRITICAL' || $row['severity'] === 'HIGH') { $grouped_history[$ip]['max_severity'] = $row['severity']; }
    $grouped_history[$ip]['items'][] = $row;
}
?>

<style>
.arachna-search-box { width: 100%; padding: 12px; background: #020203; border: 1px solid var(--panel-border); color: var(--neon-cyan); font-family: inherit; font-size: 13px; box-sizing: border-box; }
.arachna-search-box:focus { outline: none; border-color: var(--neon-cyan); }
.arachna-group-header { background: #111319; border: 1px solid var(--panel-border); padding: 15px; margin-top: 10px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
.arachna-group-header:hover { border-color: var(--neon-cyan); }
.arachna-badge { padding: 3px 8px; font-size: 10px; font-weight: bold; border-radius: 2px; margin-left: 10px; border: 1px solid var(--panel-border); }
.arachna-group-body { display: none; background: #07080b; border: 1px solid var(--panel-border); border-top: none; padding: 15px; }
.arachna-group-header.open + .arachna-group-body { display: block !important; }
.a-chevron::before { content: '▼ '; display: inline-block; transition: transform 0.2s; color: var(--text-muted); font-size: 10px; }
.arachna-group-header.open .a-chevron::before { transform: rotate(-90deg); color: var(--neon-cyan); }
</style>

<div class="module-container">
    <h2 class="cyber-title">RAW MULTI-SCANNER MOD: ARACHNA // СЕТЕВОЙ АУДИТ</h2>

    <div class="interactive-console border-neon-cyan" style="margin-bottom: 25px;">
        <h3>Инъекция матрицы хостов для сканирования периметра</h3>
        <div style="display:flex; gap:15px; margin-top:15px;">
            <input type="text" id="arachna_target" class="cyber-textarea" style="height:42px; width:75%; color:var(--neon-cyan); font-weight:bold;" placeholder="Введите IP, домен (mail.ru) или подсеть (192.168.1.0/24)">
            <button onclick="startArachnaScan()" class="cyber-btn" style="height:42px; width:25%;">СТАРТ АУДИТА</button>
        </div>
        <div id="arachna_prog_box" style="display:none; margin-top:15px;">
            <small id="arachna_status_text" style="color:var(--neon-cyan); font-size:11px;"></small>
            <div style="width:100%; background:#000; height:8px; border:1px solid var(--panel-border); margin-top:5px;">
                <div id="arachna_bar" style="width:0%; background:var(--neon-cyan); height:100%; transition:width 0.3s;"></div>
            </div>
        </div>
    </div>

    <div class="interactive-console border-neon-green" style="margin-bottom:25px;">
        <h3>Потоковый лог ядра сканера (Hatsumi Live Terminal)</h3>
        <pre id="arachna_terminal_box" style="height:140px; background:#020204; color:var(--neon-green); overflow-y:auto; font-size:11px; font-family:monospace; padding:10px; margin:0; white-space:pre-wrap;">[*] Система ожидает запуска сканирования...</pre>
    </div>

    <div class="interactive-console border-neon-green" style="margin-bottom:25px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:20px;">
            <input type="text" id="arachna_live_search" class="arachna-search-box" onkeyup="filterArachnaHosts()" placeholder="⚡ Начните вводить IP-адрес или домен хоста для мгновенного поиска в архиве...">
        </div>
    </div>

    <div class="border-neon-red" style="padding: 25px; background: var(--panel-bg);">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid var(--panel-border); padding-bottom:10px; margin-bottom:15px;">
            <h3 style="margin:0;">Архив и история сетевого аудита (Данные СУБД Arlechino)</h3>
            <button onclick="clearArachnaHistory()" class="cyber-btn btn-sm" style="color:var(--neon-magenta); border-color:var(--neon-magenta); font-size:10px; padding:4px 10px;">ОЧИСТИТЬ ИСТОРИЮ</button>
        </div>
        
        <div id="arachna_accordion_wrapper">
            <?php if(count($grouped_history) > 0): ?>
                <?php foreach($grouped_history as $host_ip => $data): ?>
                    <div class="arachna-card-node" data-ip="<?php echo htmlspecialchars($host_ip); ?>" data-domain="<?php echo htmlspecialchars($data['domain']); ?>" style="margin-bottom: 8px;">
                        
                        <div class="arachna-group-header" onclick="this.classList.toggle('open')">
                            <div>
                                <span class="a-chevron"></span>
                                <strong style="color:var(--neon-cyan); font-size:14px;"><?php echo htmlspecialchars($host_ip); ?></strong>
                                <?php if(!empty($data['domain'])): ?><span style="color:var(--neon-yellow); margin-left:10px; font-size:12px;">[ <?php echo htmlspecialchars($data['domain']); ?> ]</span><?php endif; ?>
                                <span class="arachna-badge" style="color:var(--neon-cyan); border-color:var(--neon-cyan);"><?php echo $data['total_ports']; ?> портов</span>
                            </div>
                            <div>
                                <?php if($data['max_severity'] === 'CRITICAL' || $data['max_severity'] === 'HIGH'): ?>
                                    <span class="arachna-badge" style="color:var(--neon-magenta); border-color:var(--neon-magenta);"><?php echo $data['max_severity']; ?></span>
                                <?php else: ?>
                                    <span class="arachna-badge" style="color:var(--neon-green); border-color:var(--neon-green);">CLEAR</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="arachna-group-body">
                            <table class="cyber-table" style="font-size:11px; margin-top:0; width:100%;">
                                <thead>
                                    <tr>
                                        <th style="width:130px;">Время фиксации</th>
                                        <th style="width:60px;">Порт</th>
                                        <th>Служба (Banner ПО)</th>
                                        <th>CVE Вектор</th>
                                        <th style="width:80px; text-align:center;">Уровень</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($data['items'] as $item): ?>
                                        <tr>
                                            <td style="color:var(--text-muted);"><?php echo htmlspecialchars($item['formatted_time']); ?></td>
                                            <td style="color:#fff; font-weight:bold;"><?php echo htmlspecialchars($item['port']); ?></td>
                                            <td style="color:#cbd5e1;"><?php echo htmlspecialchars($item['service_name']); ?> <small style="color:var(--text-muted);"><?php echo htmlspecialchars($item['service_version']); ?></small></td>
                                            <td style="color:var(--neon-yellow); font-weight:bold;"><?php echo htmlspecialchars($item['cve_id']); ?></td>
                                            <td style="text-align:center;"><span style="color:var(--neon-magenta); font-weight:bold;"><?php echo htmlspecialchars($item['severity']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; padding:20px;" class="text-muted">История сканирований пуста. Запустите первый аудит сети.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let arachnaInterval = null;
let logInterval = null;

document.addEventListener("DOMContentLoaded", function() {
    arachnaInterval = setInterval(checkArachnaProgress, 1000);
    logInterval = setInterval(checkTerminalLog, 1000);
});

function filterArachnaHosts() {
    const query = document.getElementById('arachna_live_search').value.toLowerCase().trim();
    document.querySelectorAll('.arachna-card-node').forEach(node => {
        const ip = node.getAttribute('data-ip').toLowerCase();
        const dom = node.getAttribute('data-domain').toLowerCase();
        node.style.display = (ip.includes(query) || dom.includes(query)) ? 'block' : 'none';
    });
}

function checkArachnaProgress() {
    fetch('modules/arachna.php?action=get_progress')
        .then(res => res.json())
        .then(data => {
            if (data.percent < 100) {
                document.getElementById('arachna_prog_box').style.display = 'block';
                document.getElementById('arachna_status_text').innerHTML = `Выполнение: ${data.status}`;
                document.getElementById('arachna_bar').style.width = data.percent + '%';
            } else {
                if(document.getElementById('arachna_prog_box').style.display === 'block') {
                    setTimeout(() => { location.reload(); }, 1000);
                }
            }
        });
}

function checkTerminalLog() {
    fetch('modules/arachna.php?action=get_terminal_log')
        .then(res => res.json())
        .then(data => {
            const box = document.getElementById('arachna_terminal_box');
            if (data.log.trim() !== "") {
                box.innerHTML = data.log;
                box.scrollTop = box.scrollHeight;
            }
        });
}

function startArachnaScan() {
    const tgt = document.getElementById('arachna_target').value.trim();
    if (!tgt) return alert('Укажите цель!');
    
    document.getElementById('arachna_prog_box').style.display = 'block';
    document.getElementById('arachna_bar').style.width = '2%';
    document.getElementById('arachna_status_text').innerHTML = 'Инициализация...';
    
    const formData = new FormData();
    formData.append('target', tgt);
    
    fetch('modules/arachna.php?action=start_scan', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if (data.started) {
                if (arachnaInterval) clearInterval(arachnaInterval);
                if (logInterval) clearInterval(logInterval);
                arachnaInterval = setInterval(checkArachnaProgress, 1000);
                logInterval = setInterval(checkTerminalLog, 1000);
            }
        });
}

function clearArachnaHistory() {
    if (!confirm('Вы действительно хотите полностью удалить архив сетевого аудита из СУБД?')) return;
    fetch('modules/arachna.php?action=clear_history')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(`[+] ARACHNA СУБД: ${data.msg}`);
                location.reload();
            }
        });
}
</script>
