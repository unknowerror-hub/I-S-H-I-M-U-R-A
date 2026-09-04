<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — MODULE: LAMIA [IPS FIREWALL CORE ENGINE]
 * ==============================================================================
 */
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once '/opt/ishimura/web/config.php';

try {
    $pdo = new PDO("pgsql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) { die("[-] СУБД Error: " . $e->getMessage()); }

// АВТОМАТИЧЕСКАЯ ГЕНЕРАЦИЯ ТАБЛИЦ ПРИ УСТАНОВКЕ СИСТЕМЫ ИЗ ОПИСАНИЯ ФУНКЦИЙ (ПО ТЗ)
$pdo->exec("
CREATE TABLE IF NOT EXISTS public.lamia_rules (
    id SERIAL PRIMARY KEY,
    target_ip VARCHAR(50) UNIQUE NOT NULL,
    rule_mode VARCHAR(10) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS public.lamia_attack_logs (
    id SERIAL PRIMARY KEY,
    attacker_ip VARCHAR(50) NOT NULL,
    attack_type VARCHAR(100) NOT NULL,
    signature_payload TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Локальный хост 127.0.0.1 занесен в белый список по умолчанию (По ТЗ)
INSERT INTO public.lamia_rules (target_ip, rule_mode, description) 
VALUES ('127.0.0.1', 'WHITE', 'Локальный хост управления кластером') 
ON CONFLICT (target_ip) DO NOTHING;
");

// АСИНХРОННЫЙ АПИ-ОБРАБОТЧИК КАНАЛА ФИЛЬТРАЦИИ (ПО ТЗ)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action'] === 'add_rule' && isset($_GET['ip']) && isset($_GET['mode']) && isset($_GET['desc'])) {
        try {
            $stmt = $pdo->prepare("INSERT INTO public.lamia_rules (target_ip, rule_mode, description) VALUES (?, ?, ?) ON CONFLICT (target_ip) DO UPDATE SET rule_mode = EXCLUDED.rule_mode, description = EXCLUDED.description;");
            $stmt->execute([trim($_GET['ip']), trim($_GET['mode']), trim($_GET['desc'])]);
            echo json_encode(["success" => true, "msg" => "Правило фильтрации IPS успешно активировано для узла [" . $_GET['ip'] . "]."]);
        } catch (Exception $e) { echo json_encode(["success" => false, "error" => $e->getMessage()]); }
        exit;
    }
}

// Загрузка списков правил и журнала перехваченных атак из СУБД
$rules = $pdo->query("SELECT * FROM public.lamia_rules ORDER BY id DESC;")->fetchAll(PDO::FETCH_ASSOC);
$logs = $pdo->query("SELECT * FROM public.lamia_attack_logs ORDER BY id DESC LIMIT 50;")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.lamia-card { background: var(--panel-bg); border: 1px solid var(--panel-border); padding: 20px; box-sizing: border-box; margin-bottom: 25px; }
.lamia-grid { display: grid; grid-template-columns: 320px 1fr; gap: 20px; }
.lamia-input { width: 100%; padding: 10px; background: #020203; border: 1px solid var(--panel-border); color: var(--neon-cyan); font-family: inherit; font-size: 13px; box-sizing: border-box; margin-bottom: 12px; }
.lamia-input:focus { outline: none; border-color: var(--neon-cyan); }
.lamia-select { width: 100%; padding: 10px; background: #020203; border: 1px solid var(--panel-border); color: var(--neon-magenta); font-family: inherit; font-size: 13px; box-sizing: border-box; margin-bottom: 12px; }
.lamia-table { width: 100%; border-collapse: collapse; font-size: 11px; margin-top: 10px; }
.lamia-table th, .lamia-table td { border: 1px solid var(--panel-border); padding: 8px; text-align: left; }
.lamia-table th { background: #111319; color: var(--neon-cyan); }
</style>

<div class="module-container">
    <h2 class="cyber-title">PROACTIVE IPS SCREEN CORE: LAMIA // МЕЖСЕТЕВОЙ ЭКРАН</h2>

    <div class="lamia-grid">
        <!-- ФОРМА УПРАВЛЕНИЯ ПРАВИЛАМИ ФИЛЬТРАЦИИ ПО ТЗ -->
        <div class="lamia-card border-neon-magenta" style="height: fit-content;">
            <h3 style="margin-top: 0;">Ударный IPS-фильтр</h3>
            <form id="lamia_rule_form" onsubmit="submitLamiaRule(event)">
                <label style="font-size: 11px; color: var(--text-muted);">IPv4-Адрес узла:</label>
                <input type="text" id="lamia_ip" class="lamia-input" placeholder="Пример: 185.180.201.5" required>
                
                <label style="font-size: 11px; color: var(--text-muted);">Режим фильтрации (По ТЗ):</label>
                <select id="lamia_mode" class="lamia-select">
                    <option value="WHITE">WHITE (Белый список)</option>
                    <option value="BLACK">BLACK (Аварийный бан)</option>
                </select>
                
                <label style="font-size: 11px; color: var(--text-muted);">Назначение правила:</label>
                <input type="text" id="lamia_desc" class="lamia-input" placeholder="Пример: Сервер-воркер кластера" required>
                
                <button type="submit" class="cyber-btn" style="width: 100%; height: 38px; font-size: 11px; border-color: var(--neon-magenta); color: var(--neon-magenta);">АКТИВИРОВАТЬ ПРАВИЛО</button>
            </form>
        </div>

        <div style="display: flex; flex-direction: column; gap: 20px;">
            <!-- СПИСОК АКТИВНЫХ ПРАВИЛ СУБД -->
            <div class="lamia-card border-neon-blue" style="padding: 15px;">
                <h3 style="margin-top: 0; font-size: 14px;">Реестр активных правил eBPF/XDP шлюза</h3>
                <div style="max-height: 200px; overflow-y: auto;">
                    <table class="lamia-table">
                        <thead>
                            <tr>
                                <th style="width: 110px;">Целевой IP</th>
                                <th style="width: 80px;">Режим</th>
                                <th>Назначение правила фильтрации</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($rules as $r): ?>
                                <tr>
                                    <td style="font-family: monospace; font-weight: bold; color: var(--neon-cyan);"><?php echo htmlspecialchars($r['target_ip']); ?></td>
                                    <td>
                                        <span style="font-weight: bold; color: <?php echo $r['rule_mode'] === 'WHITE' ? 'var(--neon-green)' : 'var(--neon-magenta)'; ?>;">
                                            <?php echo htmlspecialchars($r['rule_mode']); ?>
                                        </span>
                                    </td>
                                    <td style="color: #cbd5e1;"><?php echo htmlspecialchars($r['description']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ЖУРНАЛ ИНСПЕКЦИИ СИГНАТУР И АТАК ПО ТЗ -->
            <div class="lamia-card border-neon-green" style="padding: 15px;">
                <h3 style="margin-top: 0; font-size: 14px;">Журнал инспекции RAW-сокетов (Перехваченные атаки)</h3>
                <div style="max-height: 220px; overflow-y: auto;">
                    <table class="lamia-table">
                        <thead>
                            <tr>
                                <th style="width: 110px;">Источник атаки</th>
                                <th style="width: 130px;">Тип воздействия</th>
                                <th>Пойманная структура вредоносной сигнатуры (Payload)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($logs) > 0): ?>
                                <?php foreach($logs as $l): ?>
                                    <tr>
                                        <td style="color: var(--neon-magenta); font-family: monospace;"><?php echo htmlspecialchars($l['attacker_ip']); ?></td>
                                        <td style="color: var(--neon-yellow); font-weight: bold;"><?php echo htmlspecialchars($l['attack_type']); ?></td>
                                        <td style="color: #cbd5e1; font-family: monospace; background: #020204; font-size: 10px; word-break: break-all;"><?php echo htmlspecialchars($l['signature_payload']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center;" class="text-muted">Аномальная сетевая активность и RCE-пейлоады в RAW-потоке сетевой карты не обнаружены. Спецификация eBPF щита активна.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// МГНОВЕННОЕ АКТИВИРОВАНИЕ ПРАВИЛ IPS ФИЛЬТРА НА ЛЕТУ ПО AJAX (ПО ТЗ)
function submitLamiaRule(event) {
    event.preventDefault();
    
    var ipInput = document.getElementById('lamia_ip');
    var modeSelect = document.getElementById('lamia_mode');
    var descInput = document.getElementById('lamia_desc');
    
    var ip = ipInput.value.trim();
    var mode = modeSelect.value;
    var desc = descInput.value.trim();
    
    if (!ip || !desc) return;
    
    fetch('modules/lamia.php?action=add_rule&ip=' + encodeURIComponent(ip) + '&mode=' + encodeURIComponent(mode) + '&desc=' + encodeURIComponent(desc))
        .then(function(res) { return res.json(); })
        .then(function(data) {
            if (data.success) {
                alert('[+] LAMIA IPS SYSTEM: ' + data.msg);
                location.reload(); // Перегружаем модуль для отрисовки свежей строки в eBPF-реестре
            } else {
                alert('[-] Сбой активации правила: ' + data.error);
            }
        })
        .catch(function() {
            alert('[-] Ошибка связи с API-модулем Lamia при добавлении правила.');
        });
}
</script>
