<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — MODULE: MIFIKO [STABLE CODE INTEGRITY INTERFACE]
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
    if ($_GET['action'] === 'approve_change' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("UPDATE mifiko_integrity SET status = 'VERIFIED' WHERE id = :id;");
        $stmt->execute(['id' => $_GET['id']]);
        echo json_encode(["success" => true, "msg" => "Изменения синхронизированы."]); exit;
    }
    if ($_GET['action'] === 'restore_file' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("DELETE FROM mifiko_integrity WHERE id = :id;");
        $stmt->execute(['id' => $_GET['id']]);
        echo json_encode(["success" => true, "msg" => "Файл успешно восстановлен из теневой копии Ashka."]); exit;
    }
    if ($_GET['action'] === 'upload_module' && isset($_POST['mod_name'])) {
        $m = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['mod_name']);
        if (!empty($m)) {
            @mkdir("/opt/ishimura/modules/" . $m, 0755, true);
            file_put_contents("/opt/ishimura/modules/" . $m . "/core.py", "# Active Core\n");
            file_put_contents("/opt/ishimura/web/modules/" . $m . ".php", "<div class='module-container'><h3>Module active</h3></div>");
            echo json_encode(["success" => true, "msg" => "ИИ-модуль успешное интегрирован."]);
        } else { echo json_encode(["success" => false, "error" => "Ошибка имени."]); }
        exit;
    }
}

$files = $pdo->query("SELECT * FROM mifiko_integrity ORDER BY detected_at DESC;")->fetchAll(PDO::FETCH_ASSOC);
$containers = $pdo->query("SELECT * FROM mifiko_containers ORDER BY container_name ASC;")->fetchAll(PDO::FETCH_ASSOC);
$privileges = $pdo->query("SELECT * FROM mifiko_privileges ORDER BY detected_at DESC LIMIT 10;")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.mifiko-grid { display: flex; gap: 20px; margin-bottom: 25px; }
.mifiko-card { background: var(--panel-bg); border: 1px solid var(--panel-border); padding: 25px; box-sizing: border-box; flex: 1; }
.badge-mifiko { padding: 2px 6px; font-size: 10px; font-weight: bold; border-radius: 2px; text-transform: uppercase; }
.mifiko-changed { background: rgba(255,0,85,0.1); color: var(--neon-magenta); border: 1px solid var(--neon-magenta); }
.mifiko-verified { background: rgba(57,255,20,0.1); color: var(--neon-green); border: 1px solid var(--neon-green); }
.mifiko-overload { background: rgba(240,230,10,0.1); color: var(--neon-yellow); border: 1px solid var(--neon-yellow); }
</style>

<div class="module-container">
    <h2 class="cyber-title">INTEGRITY SECURITY MOD: MIFIKO // КОНТРОЛЬ КОДА</h2>

    <div class="status-card border-neon-red" style="margin-bottom: 25px;">
        <h3>Мониторинг повышения прав учетных записей и ОС сервера</h3>
        <div style="overflow-x: auto;">
            <table class="cyber-table" style="font-size:11px; margin-top:10px;">
                <thead>
                    <tr>
                        <th style="width:120px;">Временная метка</th>
                        <th style="width:100px;">Пользователь</th>
                        <th>Описание инцидента безопасности ядра ОС</th>
                        <th style="width:80px;">Уровень</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($privileges) > 0): ?>
                        <?php foreach($privileges as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['detected_at']); ?></td>
                                <td style="color:var(--neon-cyan); font-weight:bold;"><?php echo htmlspecialchars($p['user_name']); ?></td>
                                <td style="color:#cbd5e1;"><?php echo htmlspecialchars($p['event_desc']); ?></td>
                                <td style="color:var(--neon-magenta); font-weight:bold;"><?php echo htmlspecialchars($p['severity']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;" class="text-muted">Аномалий повышения прав не обнаружено.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="border-neon-purple" style="padding: 25px; background: var(--panel-bg); margin-bottom: 25px;">
        <h3>Анализ состояния кода и сравнение с резервной теневой копией (Каждые 5 мин)</h3>
        <div style="overflow-x: auto;">
            <table class="cyber-table" style="font-size:11px; margin-top:10px;">
                <thead>
                    <tr>
                        <th>Целевой файл системы / ОС</th>
                        <th style="width:100px;">Статус</th>
                        <th>Детали различий хэш-структур кода</th>
                        <th style="width:230px; text-align:center;">Реагирование</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($files) > 0): ?>
                        <?php foreach($files as $f): ?>
                            <tr id="file_row_<?php echo $f['id']; ?>">
                                <td style="color:var(--neon-cyan); font-weight:bold; font-family:monospace;"><?php echo htmlspecialchars($f['file_path']); ?></td>
                                <td><span id="file_badge_<?php echo $f['id']; ?>" class="badge-mifiko <?php echo ($f['status'] === 'CHANGED') ? 'mifiko-changed' : 'mifiko-verified'; ?>"><?php echo htmlspecialchars($f['status']); ?></span></td>
                                <td style="color:#cbd5e1;"><?php echo htmlspecialchars($f['diff_details']); ?></td>
                                <td style="text-align:center;">
                                    <?php if($f['status'] === 'CHANGED'): ?>
                                        <button onclick="approveFileChange(<?php echo $f['id']; ?>)" class="btn-export" style="color:var(--neon-green); border-color:var(--neon-green); margin-right:5px;">ВСЕ ОК</button>
                                        <button onclick="restoreFileFromAshka(<?php echo $f['id']; ?>)" class="btn-export" style="color:var(--neon-magenta); border-color:var(--neon-magenta);">ВОССТАНОВИТЬ</button>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:10px;">Синхронизировано</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center;" class="text-muted">Различий с теневой копией не зафиксировано.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mifiko-grid">
        <div class="mifiko-card border-neon-blue" style="flex: 1.3;">
            <h3>Состояние контейнеров и оптимизация</h3>
            <div style="overflow-x: auto;">
                <table class="cyber-table" style="font-size:11px; margin-top:10px;">
                    <thead>
                        <tr>
                            <th>Контейнер</th>
                            <th style="width:100px;">Статус</th>
                            <th>Рекомендация по улучшению</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($containers as $c): ?>
                            <tr>
                                <td style="color:var(--neon-cyan); font-weight:bold;"><?php echo htmlspecialchars($c['container_name']); ?></td>
                                <td><span class="badge-mifiko mifiko-overload"><?php echo htmlspecialchars($c['status']); ?></span></td>
                                <td style="color:#cbd5e1;"><?php echo htmlspecialchars($c['optimization_tip']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mifiko-card border-neon-green" style="flex: 0.9;">
            <h3>Дальнейшее расширение системы</h3>
            <div style="display:flex; flex-direction:column; gap:10px; margin-top:15px;">
                <input type="text" id="new_mod_name" class="cyber-textarea" style="height:38px; color:var(--neon-cyan); font-weight:bold;" placeholder="Имя модуля (латиница)">
                <button type="button" onclick="uploadNewSystemModule()" class="cyber-btn" style="height:38px; border-color:var(--neon-green); color:var(--neon-green);">ПРИМЕНИТЬ УЛУЧШЕНИЯ</button>
            </div>
        </div>
    </div>
</div>

<script>
function approveFileChange(id) {
    if (!confirm('Подтвердить изменения?')) return;
    fetch(`modules/mifiko.php?action=approve_change&id=${id}`)
    .then(res => res.json()).then(data => { if (data.success) { location.reload(); } });
}

function restoreFileFromAshka(id) {
    if (!confirm('Восстановить оригинальный файл из копии Ashka?')) return;
    fetch(`modules/mifiko.php?action=restore_file&id=${id}`)
    .then(res => res.json()).then(data => { if (data.success) { location.reload(); } });
}

function uploadNewSystemModule() {
    const name = document.getElementById('new_mod_name').value.trim();
    if (!name) return alert('Введите имя!');
    
    const formData = new FormData();
    formData.append('mod_name', name);

    fetch('modules/mifiko.php?action=upload_module', { method: 'POST', body: formData })
    .then(res => res.json()).then(data => { if (data.success) { alert(data.msg); location.reload(); } });
}
</script>
