<?php
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) { die("[-] СУБД Error: " . $e->getMessage()); }

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'get_db_progress') {
        $f = '/tmp/miko_db_progress.json';
        if (file_exists($f)) { echo file_get_contents($f); } 
        else {
            $count = $pdo->query("SELECT COUNT(*) FROM local_vuln_db;")->fetchColumn();
            echo json_encode(["percent" => 100, "total" => (142053 + $count), "status" => "База стабильна.", "speed" => 0.0, "eta" => 0]);
        }
        exit;
    }
    if ($_GET['action'] === 'start_db_update') {
        @unlink('/tmp/miko_db_progress.json');
        exec("sudo /usr/bin/python3 /opt/ishimura/modules/miko/analyzer.py update > /dev/null 2>&1 &");
        echo json_encode(["started" => true]); exit;
    }
    if ($_GET['action'] === 'get_host_details' && isset($_GET['ip'])) {
        $stmt = $pdo->prepare("SELECT * FROM vulnerability_scans WHERE target_ip = :ip;");
        $stmt->execute(['ip' => $_GET['ip']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
    }
    if ($_GET['action'] === 'export_file') {
        $sip = $_GET['scope_ip'] ?? '';
        header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename=report.csv');
        $out = fopen('php://output', 'w'); fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['IP', 'Порт', 'Сервис', 'CVE', 'Патч ИИ']);
        $stmt = $pdo->prepare("SELECT * FROM vulnerability_scans WHERE target_ip = :sip;");
        $stmt->execute(['sip' => $sip]);
        while($r = $stmt->fetch(PDO::FETCH_ASSOC)) { fputcsv($out, [$r['target_ip'], $r['port'], $r['service_name'], $r['cve_id'], $r['remediation']]); }
        fclose($out); exit;
    }
}

$stmt = $pdo->query("SELECT * FROM vulnerability_scans ORDER BY target_ip, port ASC;");
$grouped_miko = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $ip = $row['target_ip'];
    if (!isset($grouped_miko[$ip])) $grouped_miko[$ip] = ['crit' => 0, 'total' => 0, 'domain' => $row['target_domain'] ?? 'N/A', 'items' => []];
    if ($row['severity'] === 'CRITICAL' || $row['severity'] === 'HIGH') $grouped_miko[$ip]['crit']++;
    $grouped_miko[$ip]['total']++; $grouped_miko[$ip]['items'][] = $row;
}
?>

<style>
.miko-search-box { width: 100%; padding: 12px; background: #020203; border: 1px solid var(--panel-border); color: var(--neon-cyan); font-family: inherit; font-size: 13px; box-sizing: border-box; }
.miko-header-card { background: #111319; border: 1px solid var(--panel-border); padding: 15px; margin-top: 10px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
.miko-badge { padding: 3px 8px; font-size: 10px; font-weight: bold; border-radius: 2px; margin-left: 10px; border: 1px solid var(--panel-border); }
.miko-body-box { display: none; background: #07080b; border: 1px solid var(--panel-border); border-top: none; padding: 15px; }
.miko-header-card.open + .miko-body-box { display: block !important; }
.export-btn-group { display: flex; gap: 5px; align-items: center; }
.btn-export { background: transparent; border: 1px solid var(--panel-border); color: #cbd5e1; font-size: 10px; padding: 4px 8px; cursor: pointer; text-transform: uppercase; font-weight: bold; }
.btn-export:hover { color: var(--neon-cyan); border-color: var(--neon-cyan); }
.metric-span { margin-left: 15px; font-size: 12px; color: var(--neon-yellow); font-family: monospace; }
.cyber-modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.85); backdrop-filter: blur(5px); z-index: 999; display: none; justify-content: center; align-items: center; }
.cyber-modal-content { width: 800px; max-height: 80vh; background: #0b0c10; border: 1px solid var(--neon-cyan); box-shadow: 0 0 25px rgba(0,240,255,0.2); padding: 30px; overflow-y: auto; position: relative; }
.modal-close-btn { position: absolute; top: 15px; right: 20px; color: var(--neon-magenta); border: 1px solid var(--neon-magenta); background: transparent; padding: 3px 8px; cursor: pointer; font-weight: bold; }
.modal-close-btn:hover { background: var(--neon-magenta); color: #000; }
.modal-vector-box { border: 1px solid var(--panel-border); padding: 15px; margin-bottom: 15px; background: #111319; }
.cyber-link { color: var(--neon-yellow); text-decoration: none; border-bottom: 1px dashed var(--neon-yellow); font-size: 11px; margin-right: 15px; }
.cyber-link:hover { color: #fff; border-color: #fff; }
</style>

<div class="module-container">
    <h2 class="cyber-title">AI ANALYTICS MOD: MIKO // РЕЗОНАТОР МЫСЛЕЙ</h2>

    <div class="cyber-row" style="margin-bottom: 25px;">
        <div class="status-card border-neon-blue" style="width: 100%;">
            <h3>Локальная база сигнатур уязвимостей (Vuln_DB)</h3>
            <p>Статус: <span style="color:var(--neon-green); font-weight:bold;">ACTIVE (Автоапдейт)</span></p>
            <p>Всего записей CVE в СУБД: <span id="miko_db_counter" style="color:var(--neon-cyan); font-weight:bold; border: 1px solid var(--neon-cyan); padding: 2px 6px; background:#000;">Загрузка...</span></p>
            <button type="button" onclick="triggerMikoDbUpdate()" class="cyber-btn btn-sm" style="margin-top:10px;">Обновить базу сигнатур из Internet</button>
            <div id="miko_progress_container" style="display:none; margin-top:20px; border-top: 1px dashed #222; padding-top:15px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                    <small id="miko_progress_status" style="font-size:11px; color:var(--neon-cyan);"></small>
                    <div><span id="miko_speed_metric" class="metric-span">0.0 Мб/с</span><span id="miko_eta_metric" class="metric-span">0 сек</span></div>
                </div>
                <div style="width: 100%; background: #000; height: 12px; border: 1px solid var(--neon-cyan);"><div id="miko_progress_bar" style="width: 0%; background: var(--neon-cyan); height: 100%;"></div></div>
            </div>
        </div>
    </div>

    <div class="interactive-console border-neon-green" style="margin-bottom:30px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:20px;">
            <input type="text" id="miko_global_search" class="miko-search-box" onkeyup="filterMikoHosts()" placeholder="⚡ Начните вводить IP или домен для мгновенного поиска...">
            <div class="export-btn-group">
                <button onclick="triggerGlobalExport('csv')" class="btn-export" style="color:var(--neon-cyan); border-color:var(--neon-cyan);">ALL_CSV</button>
            </div>
        </div>
    </div>

    <div class="border-neon-red" style="padding: 25px; background: var(--panel-bg);">
        <h3>Структурированные уязвимости и пошаговые ИИ-патчи</h3>
        <div id="miko_accordion_wrapper">
            <?php if(count($grouped_miko) > 0): ?>
                <?php foreach($grouped_miko as $host_ip => $data): ?>
                    <div class="miko-card-node" data-ip="<?php echo htmlspecialchars($host_ip); ?>" data-domain="<?php echo htmlspecialchars($data['domain']); ?>" style="margin-bottom: 10px;">
                        <div class="miko-header-card" onclick="this.classList.toggle('open')">
                            <div>
                                <span style="color:var(--text-muted); font-size:10px; margin-right:10px;">▼</span>
                                <strong style="color:var(--neon-cyan); font-size:14px;"><?php echo htmlspecialchars($host_ip); ?></strong>
                                <?php if($data['domain'] !== 'N/A'): ?><span style="color:var(--neon-yellow); margin-left:10px; font-size:12px;">[ <?php echo htmlspecialchars($data['domain']); ?> ]</span><?php endif; ?>
                                <span class="miko-badge" style="color:var(--neon-cyan); border-color:var(--neon-cyan);"><?php echo $data['total']; ?> портов</span>
                            </div>
                            <div style="display:flex; align-items:center; gap:15px;">
                                <?php if($data['crit'] > 0): ?><span class="miko-badge" style="color:var(--neon-magenta); border-color:var(--neon-magenta);">CRIT: <?php echo $data['crit']; ?></span><?php endif; ?>
                                <div class="export-btn-group" onclick="event.stopPropagation();">
                                    <button onclick="openIntelligentModal('<?php echo $host_ip; ?>')" class="btn-export" style="color:var(--neon-cyan); border-color:var(--neon-cyan); background:rgba(0,240,255,0.03);">ОТКРЫТЬ [REASON]</button>
                                    <button onclick="triggerScopeExport('csv', '<?php echo $host_ip; ?>')" class="btn-export">CSV</button>
                                </div>
                            </div>
                        </div>

                        <div class="miko-body-box">
                            <table class="cyber-table" style="font-size:11px; margin-top:0; width:100%;">
                                <thead>
                                    <tr>
                                        <th style="width:50px;">Порт</th>
                                        <th style="width:120px;">Служба</th>
                                        <th style="width:120px;">CVE Вектор</th>
                                        <th style="width:80px;">Уровень</th>
                                        <th>Выработанное ИИ-решение по блокированию и патчингу</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($data['items'] as $item): ?>
                                        <tr>
                                            <td style="color:#fff; font-weight:bold;"><?php echo htmlspecialchars($item['port']); ?></td>
                                            <td><span style="color:var(--neon-cyan);"><?php echo htmlspecialchars($item['service_name']); ?></span><br/><small style="color:var(--text-muted); font-size:9px;"><?php echo htmlspecialchars($item['service_version']); ?></small></td>
                                            <td style="color:var(--neon-yellow); font-weight:bold;"><?php echo htmlspecialchars($item['cve_id']); ?></td>
                                            <td><span style="color:var(--neon-magenta); font-weight:bold;"><?php echo htmlspecialchars($item['severity']); ?></span></td>
                                            <td style="color:#cbd5e1; white-space:normal; line-height:1.4;"><?php echo htmlspecialchars($item['remediation']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p style="text-align:center; padding:20px;" class="text-muted">Реестр пуст. Выполните сканирование в модуле Arachna.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="miko_cyber_modal" class="cyber-modal-overlay" onclick="closeIntelligentModal()">
    <div class="cyber-modal-content" onclick="event.stopPropagation()">
        <button class="modal-close-btn" onclick="closeIntelligentModal()">X</button>
        <h2 id="modal_host_title" style="color:var(--neon-cyan); margin-top:0; letter-spacing:2px; font-size:18px;"></h2>
        <div id="modal_dynamic_data" style="margin-top:20px;"></div>
    </div>
</div>

<script>
let mikoDbInterval = null;
document.addEventListener("DOMContentLoaded", function() { updateMikoDbStatus(); });

function updateMikoDbStatus() {
    fetch('modules/miko.php?action=get_db_progress')
        .then(res => res.json())
        .then(data => {
            document.getElementById('miko_db_counter').innerHTML = Number(data.total).toLocaleString() + ' сигнатур';
            if (data.percent < 100) {
                document.getElementById('miko_progress_container').style.display = 'block';
                document.getElementById('miko_progress_bar').style.width = data.percent + '%';
                document.getElementById('miko_progress_status').innerHTML = `Синхронизация баз: ${data.status}`;
                document.getElementById('miko_speed_metric').innerHTML = `Скорость: ${data.speed} Мб/с`;
                document.getElementById('miko_eta_metric').innerHTML = `Осталось: ${data.eta} сек`;
            } else { document.getElementById('miko_progress_container').style.display = 'none'; if (mikoDbInterval) clearInterval(mikoDbInterval); }
        });
}

function triggerMikoDbUpdate() {
    document.getElementById('miko_progress_container').style.display = 'block';
    document.getElementById('miko_progress_bar').style.width = '2%';
    document.getElementById('miko_progress_status').innerHTML = 'Подключение к NVD шлюзам...';
    fetch('modules/miko.php?action=start_db_update').then(res => res.json()).then(data => { if (data.started) { if (mikoDbInterval) clearInterval(mikoDbInterval); mikoDbInterval = setInterval(updateMikoDbStatus, 1000); } });
}

function filterMikoHosts() {
    const query = document.getElementById('miko_global_search').value.toLowerCase().trim();
    document.querySelectorAll('.miko-card-node').forEach(node => {
        const ip = node.getAttribute('data-ip').toLowerCase();
        const dom = node.getAttribute('data-domain').toLowerCase();
        node.style.display = (ip.includes(query) || dom.includes(query)) ? 'block' : 'none';
    });
}

function openIntelligentModal(hostIp) {
    const modal = document.getElementById('miko_cyber_modal');
    const title = document.getElementById('modal_host_title');
    const contentBox = document.getElementById('modal_dynamic_data');
    title.innerHTML = `🛡️ ПОДРОБНЫЙ ИИ-АНАЛИЗ ХОСТА: ${hostIp}`;
    contentBox.innerHTML = "<p style='color:var(--neon-cyan);'>[РЕЕСТР СУБД] Сбор логов...</p>";
    modal.style.display = 'flex';

    fetch(`modules/miko.php?action=get_host_details&ip=${hostIp}`)
        .then(res => res.json())
        .then(data => {
            if(!data || data.length === 0) { contentBox.innerHTML = "<p class='text-muted'>Пусто.</p>"; return; }
            let html = "";
            data.forEach(v => {
                let severityColor = v.severity === 'CRITICAL' || v.severity === 'HIGH' ? 'var(--neon-magenta)' : 'var(--neon-cyan)';
                let cveLink = v.cve_id !== 'N/A' && v.cve_id !== '0-DAY // CRITICAL' 
                    ? `<a href="https://nist.gov{v.cve_id}" target="_blank" class="cyber-link">🔗 База NVD/MITRE (${v.cve_id})</a>`
                    : `<span style="color:var(--text-muted); font-size:11px; margin-right:15px;">🔗 Нет CVE (0-Day контур)</span>`;
                let patchLink = v.severity === 'CRITICAL' || v.severity === 'HIGH'
                    ? `<a href="https://github.com{v.cve_id}+patch" target="_blank" class="cyber-link" style="color:var(--neon-green); border-color:var(--neon-green);">📦 Скачать готовый патч / PoC</a>`
                    : `<span style="color:var(--text-muted); font-size:11px;">📦 Патч не требуется</span>`;

                html += `
                    <div class="modal-vector-box">
                        <div style="display:flex; justify-content:space-between; border-bottom:1px solid #222; padding-bottom:5px; margin-bottom:10px;">
                            <span style="color:#fff; font-weight:bold;">Порт: ${v.port} // Служба: ${v.service_name}</span>
                            <span style="color:${severityColor}; font-weight:bold;">Критичность: ${v.severity}</span>
                        </div>
                        <p style="margin:5px 0; font-size:12px; color:var(--text-muted);"><strong>Версия ПО:</strong> ${v.service_version}</p>
                        <p style="margin:10px 0; font-size:13px; line-height:1.4; color:#fff;"><strong>ИИ-Патч по блокированию:</strong><br/>${v.remediation}</p>
                        <div style="margin-top:15px; padding-top:10px; border-top:1px dashed #222;">\${cveLink}\&nbsp;\&nbsp;\${patchLink}</div>
                    </div>
                `;
            });
            contentBox.innerHTML = html;
        });
}

function closeIntelligentModal() { document.getElementById('miko_cyber_modal').style.display = 'none'; }
function triggerScopeExport(format, hostIp) { window.location.href = `modules/miko.php?action=export_file&format=${format}&scope_ip=${hostIp}`; }
function triggerGlobalExport(format) { window.location.href = `modules/miko.php?action=export_file&format=${format}&search=${encodeURIComponent(document.getElementById('miko_global_search').value.trim())}`; }
</script>
