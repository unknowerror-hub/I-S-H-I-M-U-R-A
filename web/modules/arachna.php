<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: ARACHNA [STABLE TERMINAL & DOMAIN DISPLAY]
 * ==============================================================================
 */
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die("[-] Ошибка СУБД: " . $e->getMessage());
}

// АПИ-КОНТРОЛЛЕР ДЛЯ AJAX ОПРОСОВ
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'get_progress') {
        $f = '/tmp/arachna_progress.json';
        echo file_exists($f) ? file_get_contents($f) : json_encode(["percent" => 0, "status" => "Ожидание..."]);
        exit;
    }
    if ($_GET['action'] === 'get_terminal_log') {
        $f = '/tmp/arachna_terminal.log';
        echo json_encode(["log" => file_exists($f) ? file_get_contents($f) : "[*] Старт..."]);
        exit;
    }
    if ($_GET['action'] === 'clear') {
        $pdo->exec("TRUNCATE TABLE vulnerability_scans RESTART IDENTITY;");
        @unlink('/tmp/arachna_terminal.log'); @unlink('/tmp/arachna_progress.json');
        echo json_encode(["success" => true]); exit;
    }
    if ($_GET['action'] === 'start' && isset($_GET['target'])) {
        @unlink('/tmp/arachna_terminal.log'); @unlink('/tmp/arachna_progress.json');
        $cmd = "sudo /usr/bin/python3 /opt/ishimura/modules/arachna/core.py " . escapeshellarg(trim($_GET['target'])) . " &";
        exec($cmd); echo json_encode(["started" => true]); exit;
    }
}

// Извлечение и агрегация данных для аккордеона
$stmt = $pdo->query("SELECT * FROM vulnerability_scans ORDER BY target_ip, port ASC;");
$raw_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grouped_hosts = [];
foreach ($raw_records as $row) {
    $ip = $row['target_ip'];
    if (!isset($grouped_hosts[$ip])) {
        $grouped_hosts[$ip] = ['crit' => 0, 'total' => 0, 'domain' => $row['target_domain'] ?? 'N/A', 'items' => []];
    }
    if ($row['severity'] === 'CRITICAL' || $row['severity'] === 'HIGH') $grouped_hosts[$ip]['crit']++;
    $grouped_hosts[$ip]['total']++; $grouped_hosts[$ip]['items'][] = $row;
}
?>

<style>
.cyber-search-input { width: 100%; padding: 12px; background: #020203; border: 1px solid var(--panel-border); color: var(--neon-cyan); font-family: inherit; font-size: 13px; box-sizing: border-box; }
.host-group-header { background: #111319; border: 1px solid var(--panel-border); padding: 15px; margin-top: 10px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
.host-group-header:hover { border-color: var(--neon-cyan); }
.host-badge { padding: 3px 8px; font-size: 10px; font-weight: bold; border-radius: 2px; margin-left: 10px; border: 1px solid var(--panel-border); }
.badge-danger { background: rgba(255,0,85,0.1); color: var(--neon-magenta); border-color: var(--neon-magenta); }
.badge-info { background: rgba(0,240,255,0.05); color: var(--neon-cyan); border-color: var(--neon-cyan); }
.host-group-body { display: none; background: #07080b; border: 1px solid var(--panel-border); border-top: none; padding: 15px; }
.host-group-header.open + .host-group-body { display: block; }
.chevron-icon::before { content: '▶ '; display: inline-block; transition: transform 0.2s; color: var(--text-muted); }
.host-group-header.open .chevron-icon::before { transform: rotate(90deg); color: var(--neon-cyan); }
.domain-span { color: var(--neon-yellow); margin-left: 10px; font-size: 12px; border-bottom: 1px dashed rgba(24cee0a, 0.3); }
</style>

<div class="module-container">
    <h2 class="cyber-title">NET SCANNER MOD: ARACHNA // СЕТЕВОЙ ПАУК</h2>

    <div class="interactive-console border-neon-green" style="margin-bottom:30px;">
        <h3>Параметры инъекции сканирования</h3>
        <div style="display: flex; gap:15px; align-items: center;">
            <input type="text" id="target_input" class="cyber-textarea" style="height: 45px; width:70%; color: var(--neon-cyan); font-weight:bold;" placeholder="Введите домен хоста (mail.ru), IP или CIDR маску...">
            <button type="button" onclick="runAsyncScan()" class="cyber-btn" style="height: 45px; width: 30%;">СТАРТ АУДИТА</button>
        </div>
        <div style="margin-top:20px;">
            <h4 id="progress_status">Статус: Модуль готов к приему команд.</h4>
            <div style="width: 100%; background: #000; height: 14px; border: 1px solid var(--neon-green); margin-bottom: 15px;">
                <div id="progress_bar_fill" style="width: 0%; background: var(--neon-green); height: 100%;"></div>
            </div>
            <pre id="terminal_box" class="output-box" style="height: 180px; overflow-y: auto; color: var(--neon-green); font-size:11px;">[*] Ожидание активации сканирования...</pre>
        </div>
    </div>

    <div class="border-neon-red" style="padding: 25px; background: var(--panel-bg);">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; border-bottom: 1px solid var(--panel-border); padding-bottom:10px; gap: 20px;">
            <h3 style="margin:0; white-space:nowrap;">Реестр результатов</h3>
            <div style="flex-grow:1; max-width:400px;">
                <input type="text" id="cyber_ip_search" class="cyber-search-input" onkeyup="filterHostsByIP()" placeholder="⚡ Живой поиск по IP или домену цели...">
            </div>
            <button type="button" onclick="clearScanHistory()" class="cyber-btn btn-sm" style="color:var(--neon-magenta); border-color:var(--neon-magenta);">Очистить</button>
        </div>

        <div id="hosts_accordion_container">
            <?php if(count($grouped_hosts) > 0): ?>
                <?php foreach($grouped_hosts as $host_ip => $data): ?>
                    <div class="host-card-wrapper" data-ip="<?php echo htmlspecialchars($host_ip); ?>" data-domain="<?php echo htmlspecialchars($data['domain']); ?>">
                        <div class="host-group-header" onclick="toggleAccordion(this)">
                            <div>
                                <span class="chevron-icon"></span>
                                <strong style="color:var(--neon-cyan); font-size:14px;"><?php echo htmlspecialchars($host_ip); ?></strong>
                                <?php if($data['domain'] !== 'N/A'): ?>
                                    <span class="domain-span">[ <?php echo htmlspecialchars($data['domain']); ?> ]</span>
                                <?php endif; ?>
                                <span class="host-badge badge-info"><?php echo $data['total']; ?> портов</span>
                            </div>
                            <div>
                                <?php if($data['crit'] > 0): ?>
                                    <span class="host-badge badge-danger">⚠️ УГРОЗ: <?php echo $data['crit']; ?></span>
                                <?php else: ?>
                                    <span class="host-badge" style="color:var(--neon-green); border-color:var(--neon-green);">CLEAR</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="host-group-body">
                            <table class="cyber-table" style="font-size:11px; margin-top:0;">
                                <thead>
                                    <tr>
                                        <th style="width:60px;">Порт</th>
                                        <th style="width:130px;">Сервис</th>
                                        <th style="width:130px;">Сигнатура</th>
                                        <th style="width:90px;">Уровень</th>
                                        <th>Описание аномалии</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($data['items'] as $item): ?>
                                        <tr>
                                            <td style="color:#fff; font-weight:bold;"><?php echo htmlspecialchars($item['port']); ?></td>
                                            <td><span style="color:var(--neon-cyan); font-weight:bold;"><?php echo htmlspecialchars($item['service_name']); ?></span></td>
                                            <td style="color:var(--neon-yellow); font-weight:bold;"><?php echo htmlspecialchars($item['cve_id']); ?></td>
                                            <td><strong><?php echo htmlspecialchars($item['severity']); ?></strong></td>
                                            <td style="color:#cbd5e1;"><?php echo htmlspecialchars($item['description']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; padding:20px;" class="text-muted">История пуста.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let progressInterval = null;

function toggleAccordion(header) {
    header.classList.toggle('open');
}

function filterHostsByIP() {
    const query = document.getElementById('cyber_ip_search').value.toLowerCase().trim();
    document.querySelectorAll('.host-card-wrapper').forEach(card => {
        const ip = card.getAttribute('data-ip').toLowerCase();
        const dom = card.getAttribute('data-domain').toLowerCase();
        card.style.display = (ip.includes(query) || dom.includes(query)) ? 'block' : 'none';
    });
}

function runAsyncScan() {
    const target = document.getElementById('target_input').value.trim();
    if (!target) return alert('Укажите цель!');
    document.getElementById('terminal_box').innerHTML = "[*] Запуск фонового процесса...";
    document.getElementById('progress_bar_fill').style.width = '0%';
    
    fetch(`modules/arachna.php?action=start&target=${encodeURIComponent(target)}`)
        .then(res => res.json())
        .then(data => {
            if(data.started) {
                if (progressInterval) clearInterval(progressInterval);
                progressInterval = setInterval(updateInterfaceState, 1000);
            }
        });
}

function updateInterfaceState() {
    fetch('modules/arachna.php?action=get_progress')
        .then(res => res.json())
        .then(data => {
            document.getElementById('progress_bar_fill').style.width = data.percent + '%';
            document.getElementById('progress_status').innerHTML = `Статус: ${data.status} (${data.percent}%)`;
            if(data.percent >= 100) {
                clearInterval(progressInterval);
                console.log("[+] Сканирование завершено. Лог зафиксирован.");
                // Вместо сброса страницы мягко уведомляем оператора, история обновится при следующем клике или рефреше
                document.getElementById('progress_status').innerHTML = `⚡ АУДИТ ЗАВЕРШЕН. Подробный лог зафиксирован ниже.`;
            }
        });

    fetch('modules/arachna.php?action=get_terminal_log')
        .then(res => res.json())
        .then(data => {
            const box = document.getElementById('terminal_box');
            box.innerHTML = data.log;
            box.scrollTop = box.scrollHeight;
        });
}

function clearScanHistory() {
    if(!confirm('Очистить историю?')) return;
    fetch('modules/arachna.php?action=clear').then(() => { location.reload(); });
}
</script>
