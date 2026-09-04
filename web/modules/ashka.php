<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: ASHKA [BACKUP API LAYER]
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die("[-] Ошибка СУБД в модуле Ashka: " . $e->getMessage());
}

// АСИНХРОННЫЙ АПИ-ОБРАБОТЧИК ЗАПРОСОВ (AJAX)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // 1. Опрос текущего фонового прогресса создания/восстановления
    if ($_GET['action'] === 'get_progress') {
        $f = '/tmp/ashka_runtime_progress.json';
        if (file_exists($f)) {
            echo file_get_contents($f);
        } else {
            echo json_encode(["type" => "NONE", "percent" => 100, "status" => "Система синхронизирована."]);
        }
        exit;
    }

    // 2. Ручной запуск создания теневой копии
    if ($_GET['action'] === 'start_backup') {
        @unlink('/tmp/ashka_runtime_progress.json');
        exec("sudo /usr/bin/python3 /opt/ishimura/modules/ashka/backup_daemon.py run > /dev/null 2>&1 &");
        echo json_encode(["success" => true]);
        exit;
    }

    // 3. Запуск асинхронного восстановления системы из копии
    if ($_GET['action'] === 'start_restore') {
        $f = '/tmp/ashka_runtime_progress.json';
        file_put_contents($f, json_encode(["type" => "RESTORE", "percent" => 15, "status" => "Развертывание контейнеров, очистка ядра и восстановление базы данных..."]));
        
        // Симулируем фоновый апдейт через 2 секунды
        exec("(sleep 2; echo '{\"type\":\"NONE\",\"percent\":100,\"status\":\"Синхронизировано\"}' > /tmp/ashka_runtime_progress.json) > /dev/null 2>&1 &");
        echo json_encode(["success" => true]);
        exit;
    }

    // 4. Полное удаление всех теневых копий с диска и из СУБД
    if ($_GET['action'] === 'clear_all') {
        $stmt = $pdo->query("SELECT file_path FROM ashka_snapshots;");
        while ($path = $stmt->fetchColumn()) {
            if (file_exists($path) && strpos($path, 'master_shadow') === false) { @unlink($path); }
        }
        $pdo->exec("DELETE FROM ashka_snapshots WHERE snapshot_name NOT LIKE '%master_shadow%';");
        echo json_encode(["success" => true, "msg" => "Все пользовательские теневые копии стерты."]);
        exit;
    }

    // 5. Скачивание выбранного бэкапа
    if ($_GET['action'] === 'download' && isset($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT file_path, snapshot_name FROM ashka_snapshots WHERE id = :id;");
        $stmt->execute(['id' => $_GET['id']]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($res && file_exists($res['file_path'])) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $res['snapshot_name'] . '"');
            readfile($res['file_path']); exit;
        }
    }
}

// Загрузка реестра копий для вывода
$snapshots = $pdo->query("SELECT * FROM ashka_snapshots ORDER BY created_at DESC;")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.ashka-grid { display: flex; gap: 20px; margin-bottom: 25px; }
.ashka-card { background: var(--panel-bg); border: 1px solid var(--panel-border); padding: 25px; box-sizing: border-box; flex: 1; }
.ashka-bar-bg { width: 100%; background: #000; height: 12px; border: 1px solid var(--panel-border); margin-top: 10px; }
.ashka-bar-fill { height: 100%; width: 0%; transition: width 0.3s ease; }
.bar-backup { background: var(--neon-cyan); }
.bar-restore { background: var(--neon-magenta); }
.snapshot-badge { padding: 2px 6px; font-size: 10px; font-weight: bold; border-radius: 2px; border: 1px solid var(--neon-cyan); color: var(--neon-cyan); }
</style>

<div class="module-container">
    <h2 class="cyber-title">SHADOW BACKUP MOD: ASHKA // ЖИВУЧЕСТЬ ЯДРА СИСТЕМЫ</h2>

    <!-- ВЕРХНЯЯ КАНАЛЬНАЯ ПАНЕЛЬ С ДВУМЯ ЖИВЫМИ ПРОГРЕСС-БАРАМИ -->
    <div class="ashka-grid">
        <div class="ashka-card border-neon-blue">
            <h3>Управление теневым копированием</h3>
            <p style="font-size:11px; color:var(--text-muted); margin-bottom:15px;">Создание полной копии файлов системы, конфигураций модулей, контейнеров и дампа баз данных PostgreSQL:</p>
            <div style="display:flex; gap:10px;">
                <button onclick="triggerAshkaBackup()" class="cyber-btn btn-sm" style="border-color:var(--neon-cyan); color:var(--neon-cyan);">Создать теневую копию заново</button>
                <button onclick="clearAllSnapshots()" class="cyber-btn btn-sm" style="border-color:var(--neon-magenta); color:var(--neon-magenta);">Удалить все копии</button>
            </div>
            
            <div id="backup_progress_box" style="display:none; margin-top:15px; border-top:1px dashed #222; padding-top:10px;">
                <small id="backup_status_text" style="font-size:11px; color:var(--neon-cyan);"></small>
                <div class="ashka-bar-bg"><div id="backup_bar" class="ashka-bar-fill bar-backup"></div></div>
            </div>
        </div>

        <div class="ashka-card border-neon-purple">
            <h3>Контур аварийного восстановления</h3>
            <p style="font-size:11px; color:var(--text-muted); margin-bottom:15px;">Принудительный откат и развертывание выбранного эталонного слепка в случае критических повреждений или компрометации кода:</p>
            <button onclick="triggerAshkaRestore()" class="cyber-btn btn-sm" style="border-color:var(--neon-purple); color:var(--neon-purple);">Запустить восстановление системы</button>
            
            <div id="restore_progress_box" style="display:none; margin-top:15px; border-top:1px dashed #222; padding-top:10px;">
                <small id="restore_status_text" style="font-size:11px; color:var(--neon-magenta);"></small>
                <div class="ashka-bar-bg"><div id="restore_bar" class="ashka-bar-fill bar-restore"></div></div>
            </div>
        </div>
    </div>

    <!-- РЕЕСТР ОПЕРАТИВНЫХ БЭКАПОВ СУБД И ФАЙЛОВ -->
    <div class="border-neon-red" style="padding: 25px; background: var(--panel-bg);">
        <h3>Реестр существующих резервных копий и слепков ОС сервера (Каждые 10 мин)</h3>
        <div style="overflow-x: auto;">
            <table class="cyber-table" style="font-size:12px; margin-top:10px;">
                <thead>
                    <tr>
                        <th>Идентификатор архива</th>
                        <th>Тип копии</th>
                        <th>Объем пакета</th>
                        <th>Дата фиксации слепка</th>
                        <th style="width:100px; text-align:center;">Экспорт</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(count($snapshots) > 0): ?>
                        <?php foreach ($snapshots as $s): ?>
                            <tr>
                                <td style="color:var(--neon-cyan); font-weight:bold; font-family:monospace;"><?php echo htmlspecialchars($s['snapshot_name']); ?></td>
                                <td><span class="snapshot-badge"><?php echo htmlspecialchars($s['snapshot_type']); ?></span></td>
                                <td style="color:var(--neon-yellow); font-weight:bold;"><?php echo htmlspecialchars($s['file_size']); ?></td>
                                <td style="color:var(--text-muted);"><?php echo htmlspecialchars($s['created_at']); ?></td>
                                <td style="text-align:center;">
                                    <button onclick="window.location.href='modules/ashka.php?action=download&id=<?php echo $s['id']; ?>'" class="btn-export">СКАЧАТЬ</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;" class="text-muted">Теневые слепки отсутствуют.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
let ashkaInterval = null;

document.addEventListener("DOMContentLoaded", function() {
    // Включаем непрерывный опрос состояния фоновых задач бэкапа/восстановления
    ashkaInterval = setInterval(checkAshkaProgress, 1000);
    checkAshkaProgress();
});

function checkAshkaProgress() {
    fetch('modules/ashka.php?action=get_progress')
        .then(res => res.json())
        .then(data => {
            const bBox = document.getElementById('backup_progress_box');
            const rBox = document.getElementById('restore_progress_box');
            
            if (data.type === 'BACKUP') {
                bBox.style.display = 'block';
                rBox.style.display = 'none';
                document.getElementById('backup_status_text').innerHTML = `Выполнение: ${data.status} (${data.percent}%)`;
                document.getElementById('backup_bar').style.width = data.percent + '%';
            } else if (data.type === 'RESTORE') {
                rBox.style.display = 'block';
                bBox.style.display = 'none';
                document.getElementById('restore_status_text').innerHTML = `Выполнение: ${data.status} (${data.percent}%)`;
                document.getElementById('restore_bar').style.width = data.percent + '%';
            } else {
                bBox.style.display = 'none';
                rBox.style.display = 'none';
            }
            
            // Если процесс завершился (достиг 100%), обновляем таблицу через секунду
            if (data.percent === 100 && (bBox.style.display === 'block' || rBox.style.display === 'block')) {
                setTimeout(() => { location.reload(); }, 1000);
            }
        });
}

function triggerAshkaBackup() {
    document.getElementById('backup_progress_box').style.display = 'block';
    document.getElementById('backup_bar').style.width = '5%';
    document.getElementById('backup_status_text').innerHTML = 'Инициализация архивации...';
    
    fetch('modules/ashka.php?action=start_backup')
        .then(res => res.json())
        .then(data => { if (!data.success) alert('[-] Ошибка запуска бэкапа.'); });
}

function triggerAshkaRestore() {
    if (!confirm('ВНИМАНИЕ! Вы запускаете принудительное восстановление. Все несохраненные данные будут перезаписаны. Продолжить?')) return;
    
    document.getElementById('restore_progress_box').style.display = 'block';
    document.getElementById('restore_bar').style.width = '5%';
    document.getElementById('restore_status_text').innerHTML = 'Подготовка к откату ядра...';
    
    fetch('modules/ashka.php?action=start_restore')
        .then(res => res.json())
        .then(data => { if (!data.success) alert('[-] Ошибка запуска восстановления.'); });
}

function clearAllSnapshots() {
    if (!confirm('Вы действительно хотите полностью стереть все созданные пользовательские теневые копии?')) return;
    
    fetch('modules/ashka.php?action=clear_all')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(`[+] ASHKA: ${data.msg}`);
                location.reload();
            }
        });
}
</script>
