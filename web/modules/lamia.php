<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — MODULE: LAMIA [REVISED FIXED KERNEL INTERFACE]
 * ==============================================================================
 */
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) { die("[-] СУБД Error: " . $e->getMessage()); }

if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'add_rule' && isset($_POST['ip'], $_POST['type'], $_POST['desc'])) {
        $ip = trim($_POST['ip']); $type = strtoupper(trim($_POST['type'])); $desc = trim($_POST['desc']);
        if (filter_var($ip, FILTER_VALIDATE_IP) && in_array($type, ['WHITE', 'BLACK'])) {
            $stmt = $pdo->prepare("INSERT INTO lamia_lists (ip_address, list_type, description) VALUES (:ip, :type, :desc) ON CONFLICT (ip_address) DO UPDATE SET list_type = :type, description = :desc;");
            $stmt->execute(['ip' => $ip, 'type' => $type, 'desc' => $desc]);
            echo json_encode(["success" => true, "msg" => "IP успешно добавлен."]);
        } else { echo json_encode(["success" => false, "error" => "Неверный формат."]); }
        exit;
    }
    if ($_GET['action'] === 'delete_rule' && isset($_GET['ip'])) {
        $stmt = $pdo->prepare("DELETE FROM lamia_lists WHERE ip_address = :ip;");
        $stmt->execute(['ip' => $_GET['ip']]);
        echo json_encode(["success" => true, "msg" => "Правило удалено."]); exit;
    }
    if ($_GET['action'] === 'clear_logs') {
        $pdo->exec("TRUNCATE TABLE lamia_attacks RESTART IDENTITY;");
        echo json_encode(["success" => true, "msg" => "Журнал очищен."]); exit;
    }
}

$attacks = $pdo->query("SELECT * FROM lamia_attacks ORDER BY detected_at DESC LIMIT 50;")->fetchAll(PDO::FETCH_ASSOC);
$lists = $pdo->query("SELECT * FROM lamia_lists ORDER BY list_type DESC, ip_address ASC;")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.lamia-grid { display: flex; gap: 20px; margin-bottom: 25px; }
.lamia-card { background: var(--panel-bg); border: 1px solid var(--panel-border); padding: 25px; box-sizing: border-box; flex: 1; }
.lamia-select { background: #020203; border: 1px solid var(--panel-border); color: var(--neon-cyan); font-family: inherit; font-size: 13px; height: 42px; padding: 0 10px; box-sizing: border-box; }
.list-badge { padding: 2px 6px; font-size: 10px; font-weight: bold; border-radius: 2px; text-transform: uppercase; }
.badge-white { background: rgba(0,240,255,0.1); color: var(--neon-cyan); border: 1px solid var(--neon-cyan); }
.badge-black { background: rgba(255,0,85,0.1); color: var(--neon-magenta); border: 1px solid var(--neon-magenta); }
.delete-rule-btn { background: transparent; border: 1px solid var(--neon-magenta); color: var(--neon-magenta); padding: 2px 6px; font-size: 10px; cursor: pointer; font-weight: bold; }
</style>

<div class="module-container">
    <h2 class="cyber-title">KERNEL FIREWALL MOD: LAMIA // ИИ-КОНТУР ЗАЩИТЫ</h2>

    <div class="status-card border-neon-green" style="margin-bottom: 25px;">
        <h3>Состояние контура защиты на уровне сетевого адаптера</h3>
        <p><strong>Режим инспекции:</strong> <span style="color:var(--neon-green); font-weight:bold;">NATIVE RAW_SOCKET LAYER</span></p>
    </div>

    <div class="border-neon-red" style="padding: 25px; background: var(--panel-bg); margin-bottom: 25px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px; border-bottom:1px solid var(--panel-border); padding-bottom:10px;">
            <h3 style="margin:0;">Журнал контроля целостности модулей</h3>
            <button onclick="clearLamiaLogs()" class="cyber-btn btn-sm" style="color:var(--neon-magenta); border-color:var(--neon-magenta);">Очистить лог</button>
        </div>
        <div style="overflow-x: auto;">
            <table class="cyber-table" style="font-size:11px;">
                <thead>
                    <tr>
                        <th>Временная метка</th>
                        <th>Источник (IP)</th>
                        <th>Модуль</th>
                        <th>Тип воздействия</th>
                        <th>Payload</th>
                    </tr>
                </thead>
                <tbody id="lamia_attacks_table">
                    <?php if(count($attacks) > 0): ?>
                        <?php foreach($attacks as $atk): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($atk['detected_at']); ?></td>
                                <td style="color:var(--neon-cyan); font-weight:bold;"><?php echo htmlspecialchars($atk['source_ip']); ?></td>
                                <td><?php echo htmlspecialchars($atk['target_module']); ?></td>
                                <td style="color:var(--neon-magenta); font-weight:bold;"><?php echo htmlspecialchars($atk['attack_type']); ?></td>
                                <td style="color:#cbd5e1;"><?php echo htmlspecialchars($atk['payload_signature']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr id="no_attacks_row"><td colspan="5" style="text-align:center;" class="text-muted">⚡ Вторжений и попыток повреждения модулей не зафиксировано. Perimeter CLEAR.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="lamia-grid">
        <div class="lamia-card border-neon-blue">
            <h3>Инъекция правил в контур</h3>
            <div style="display:flex; flex-direction:column; gap:12px; margin-top:15px;">
                <div style="display:flex; gap:10px;">
                    <input type="text" id="lamia_ip_input" class="cyber-textarea" style="height:42px; width:70%; color:var(--neon-cyan); font-weight:bold;" placeholder="Введите IPv4...">
                    <select id="lamia_type_select" class="lamia-select" style="width:30%;">
                        <option value="WHITE">WHITE</option>
                        <option value="BLACK">BLACK</option>
                    </select>
                </div>
                <input type="text" id="lamia_desc_input" class="cyber-textarea" style="height:42px;" placeholder="Описание правила...">
                <button type="button" onclick="injectFilterRule()" class="cyber-btn" style="height:42px; border-color:var(--neon-blue); color:var(--neon-blue);">АКТИВИРОВАТЬ</button>
            </div>
        </div>

        <div class="lamia-card border-neon-purple">
            <h3>Глобальный реестр списков (WHITE / BLACK IP)</h3>
            <div style="max-height: 200px; overflow-y: auto; margin-top:15px;">
                <table class="cyber-table" style="font-size:11px; margin-top:0;">
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Контур</th>
                            <th>Описание</th>
                            <th style="width:40px; text-align:center;">X</th>
                        </tr>
                    </thead>
                    <tbody id="lamia_rules_table">
                        <?php foreach ($lists as $row): ?>
                            <tr id="rule_row_<?php echo str_replace('.', '_', $row['ip_address']); ?>">
                                <td style="color:var(--neon-cyan); font-weight:bold;"><?php echo htmlspecialchars($row['ip_address']); ?></td>
                                <td><span class="list-badge <?php echo ($row['list_type'] === 'WHITE') ? 'badge-white' : 'badge-black'; ?>"><?php echo htmlspecialchars($row['list_type']); ?></span></td>
                                <td style="color:#cbd5e1;"><?php echo htmlspecialchars($row['description']); ?></td>
                                <td style="text-align:center;"><button onclick="removeFilterRule('<?php echo $row['ip_address']; ?>')" class="delete-rule-btn">X</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function injectFilterRule() {
    const ip = document.getElementById('lamia_ip_input').value.trim();
    const type = document.getElementById('lamia_type_select').value;
    const desc = document.getElementById('lamia_desc_input').value.trim();
    if (!ip) return alert('Укажите IP!');

    const formData = new FormData();
    formData.append('ip', ip);
    formData.append('type', type);
    formData.append('desc', desc ? desc : 'Инъекция');

    fetch('modules/lamia.php?action=add_rule', { method: 'POST', body: formData })
    .then(res => res.json()).then(data => { if (data.success) { location.reload(); } });
}

function removeFilterRule(ipAddress) {
    if (!confirm('Удалить?')) return;
    fetch(`modules/lamia.php?action=delete_rule&ip=${encodeURIComponent(ipAddress)}`)
    .then(res => res.json()).then(data => { if (data.success) { location.reload(); } });
}

function clearLamiaLogs() {
    if (!confirm('Очистить лог?')) return;
    fetch('modules/lamia.php?action=clear_logs').then(res => res.json()).then(data => { if (data.success) { location.reload(); } });
}
</script>
