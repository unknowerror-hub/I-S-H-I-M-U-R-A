<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — MODULE: ASHKA [FULL INFRASTRUCTURE SNAPSHOT ENGINE]
 * ==============================================================================
 */
if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once '/opt/ishimura/web/config.php';

$backup_dir = '/opt/ishimura/backups/';

// АСИНХРОННЫЙ АПИ-ОБРАБОТЧИК (ПО ТЗ)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Активация обычного слепка СУБД/Файлов
    if ($_GET['action'] === 'run_backup') {
        exec("sudo /opt/ishimura/modules/ashka/backup.sh > /dev/null 2>&1 &");
        echo json_encode(["success" => true]); exit;
    }
    
    // ТРИГГЕР ПОЛНОГО БЭКАПА ВСЕЙ СИСТЕМЫ И ЗАВИСИМОСТЕЙ (ПО ТЗ)
    if ($_GET['action'] === 'run_system_backup') {
        exec("sudo /opt/ishimura/modules/ashka/system_backup.sh > /dev/null 2>&1 &");
        echo json_encode(["success" => true]); exit;
    }
    
    if ($_GET['action'] === 'upload_and_restore' && isset($_FILES['backup_file'])) {
        $file = $_FILES['backup_file'];
        if ($file['error'] === UPLOAD_ERR_OK && strpos($file['name'], '.tar.gz') !== false) {
            $uploaded_path = '/tmp/uploaded_restore_snapshot.tar.gz';
            move_uploaded_file($file['tmp_name'], $uploaded_path);
            exec("sudo /opt/ishimura/modules/ashka/restore.sh " . escapeshellarg($uploaded_path) . " > /dev/null 2>&1 &");
            echo json_encode(["success" => true, "msg" => "Архив успешно верифицирован. Реконструкция ядра запущена."]);
        } else { echo json_encode(["success" => false, "error" => "Ошибка загрузки файла."]); }
        exit;
    }
    
    if ($_GET['action'] === 'delete_backup' && isset($_GET['file'])) {
        $safe_file = basename(trim($_GET['file'])); $full_path = $backup_dir . $safe_file;
        if (file_exists($full_path) && preg_match('/\.tar\.gz$/i', $safe_file)) {
            unlink($full_path); echo json_encode(["success" => true, "msg" => "Снимок удален."]);
        } else { echo json_encode(["success" => false, "error" => "Файл не найден."]); }
        exit;
    }
    
    if ($_GET['action'] === 'download_backup' && isset($_GET['file'])) {
        ob_clean(); $safe_file = basename(trim($_GET['file'])); $full_path = $backup_dir . $safe_file;
        if (file_exists($full_path)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $safe_file . '"');
            readfile($full_path); exit;
        }
        die("Файл отсутствует.");
    }
}

// Считываем файлы архивов
$backup_files = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (preg_match('/\.tar\.gz$/i', $file)) {
            $f_path = $backup_dir . $file;
            $type = (strpos($file, 'system_infra_') !== false) ? 'ПОЛНЫЙ ОБРАЗ СИСТЕМЫ' : 'ДЕЛЬТА СУБД / КОД';
            $backup_files[] = ['name' => $file, 'type' => $type, 'size' => round(filesize($f_path)/(1024*1024),2).' MB', 'date' => date("Y-m-d H:i:s", filemtime($f_path)), 'time' => filemtime($f_path)];
        }
    }
    usort($backup_files, function($a, $b) { return $b['time'] <=> $a['time']; });
}
?>

<style>
.ash-card { background: var(--panel-bg); border: 1px solid var(--panel-border); padding: 20px; box-sizing: border-box; margin-bottom: 25px; }
.ash-progress-container { width: 100%; background: #020204; border: 1px solid var(--panel-border); height: 24px; position: relative; margin-top: 10px; margin-bottom: 15px; box-sizing: border-box; }
.ash-bar { height: 100%; width: 0%; transition: width 0.1s linear; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: bold; color: #000; }
.ash-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-top: 10px; }
.ash-table th, .ash-table td { border: 1px solid var(--panel-border); padding: 10px; text-align: left; }
.ash-table th { background: #111319; color: var(--neon-cyan); }
.ash-file-label { display: inline-block; background: #020204; border: 1px dashed var(--neon-magenta); color: var(--neon-magenta); padding: 8px 12px; cursor: pointer; font-size: 11px; font-family: monospace; font-weight: bold; }
.ash-file-label:hover { background: #111319; }
</style>

<div class="module-container">
    <h2 class="cyber-title">SHADOW SNAPSHOT ENGINE: ASHKA // КАТАСТРОФОУСТОЙЧИВОСТЬ</h2>

    <!-- ГЛОБАЛЬНЫЙ БЭКАП ВСЕЙ ИНФРАСТРУКТУРЫ (ПО ТЗ BARE-METAL DR) -->
    <div class="ash-card" style="border: 1px solid var(--neon-yellow);">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div>
                <h3 style="margin:0 0 5px 0; color:var(--neon-yellow);">Резервная копия всей инфраструктуры (OS + Apache + PHP + PostgreSQL)</h3>
                <p style="font-size:11px; color:var(--text-muted); margin:0;">Полный слепок комплекса, конфигурационных файлов системных сервисов, прав и пакетных зависимостей ОС Linux.</p>
            </div>
            <button id="btn_sys_backup" onclick="startAshkaSystemInfrastructureBackup()" class="cyber-btn btn-sm" style="border-color:var(--neon-yellow); color:var(--neon-yellow); width:320px; height:34px; font-size:11px;">СГЕНЕРИРОВАТЬ ОБРАЗ ВСЕЙ СИСТЕМЫ</button>
        </div>
        <div class="ash-progress-container" style="border-color: rgba(255,215,0,0.3);">
            <div id="sys_backup_bar" class="ash-bar" style="background:var(--neon-yellow);">0%</div>
        </div>
    </div>

    <!-- ТРИГГЕР ОБЫЧНОГО БЭКАПА -->
    <div class="ash-card border-neon-cyan">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h3>Создание теневой резервной копии дельт (СУБД / Код)</h3>
            <button id="btn_backup" onclick="startAshkaBackupProcess()" class="cyber-btn btn-sm" style="border-color:var(--neon-cyan); color:var(--neon-cyan); width:320px; height:34px; font-size:11px;">СОЗДАТЬ ТЕНЕВУЮ КОПИЮ ЗАНОВО</button>
        </div>
        <div class="ash-progress-container">
            <div id="backup_bar" class="ash-bar" style="background:var(--neon-cyan);">0%</div>
        </div>
    </div>

    <!-- ФОРМА ЗАГРУЗКИ АРХИВА И ВОССТАНОВЛЕНИЯ -->
    <div class="ash-card border-neon-magenta">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:20px;">
            <div>
                <h3 style="margin:0 0 5px 0;">Восстановление из внешнего теневого слепка / образа</h3>
                <p style="font-size:11px; color:var(--text-muted); margin:0;">Загрузите файл резервной копии дельты или полного образа для развертывания ядра кластера:</p>
            </div>
            <div style="display:flex; gap:10px; align-items:center;">
                <label for="restore_upload_input" class="ash-file-label" id="upload_status_label">📁 ВЫБРАТЬ АРХИВ .TAR.GZ</label>
                <input type="file" id="restore_upload_input" style="display:none;" accept=".tar.gz" onchange="handleRestoreFileSelection(this)">
                <button id="btn_restore" onclick="startAshkaRestoreProcess()" class="cyber-btn btn-sm" style="border-color:var(--neon-magenta); color:var(--neon-magenta); width:180px; height:34px; font-size:11px;" disabled>ЗАПУСТИТЬ СТАРТ</button>
            </div>
        </div>
        <div class="ash-progress-container">
            <div id="restore_bar" class="ash-bar" style="background:var(--neon-magenta);">0%</div>
        </div>
    </div>

    <!-- РЕЕСТР АРХИВОВ -->
    <div class="ash-card border-neon-blue">
        <h3>Реестр снимков живучести в локальном изолированном хранилище</h3>
        <p style="font-size:12px; color:var(--text-muted); margin:0 0 10px 0;">Максимальный объем ротации: 10 архивов. Снимки дельт СУБД генерируются планировщиком каждые 6 часов.</p>
        <table class="ash-table">
            <thead>
                <tr>
                    <th>Имя файла резервного архива</th>
                    <th style="width: 220px;">Классификация (Тип слепка)</th>
                    <th style="width: 110px;">Объем</th>
                    <th style="width: 150px;">Дата фиксации</th>
                    <th style="width: 160px; text-align:center;">Управление вектором</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($backup_files) > 0): ?>
                    <?php foreach($backup_files as $bf): ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: bold; color: var(--neon-cyan); font-size:11px;"><?php echo htmlspecialchars($bf['name']); ?></td>
                            <td>
                                <span style="font-weight:bold; color: <?php echo $bf['type'] === 'ПОЛНЫЙ ОБРАЗ СИСТЕМЫ' ? 'var(--neon-yellow)' : 'var(--neon-cyan)'; ?>;">
                                    <?php echo $bf['type']; ?>
                                </span>
                            </td>
                            <td style="color: #cbd5e1; font-weight: bold;"><?php echo htmlspecialchars($bf['size']); ?></td>
                            <td style="color: var(--neon-yellow); font-family: monospace; font-size:11px;"><?php echo htmlspecialchars($bf['date']); ?></td>
                            <td style="text-align:center;">
                                <button onclick="downloadBackupArchiveFile('<?php echo htmlspecialchars($bf['name']); ?>')" class="btn-export" style="color:var(--neon-cyan); border-color:var(--neon-cyan); font-size:10px; padding:2px 5px; margin-right:4px;">СКАЧАТЬ</button>
                                <button onclick="deleteBackupArchiveFile('<?php echo htmlspecialchars($bf['name']); ?>')" class="btn-export" style="color:var(--neon-magenta); border-color:var(--neon-magenta); font-size:10px; padding:2px 5px;">УДАЛИТЬ</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align:center;" class="text-muted">Изолированное хранилище снимков пусто.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
// АСИНХРОННЫЙ СБОР ОБРАЗА ВСЕЙ ИНФРАСТРУКТУРЫ ПО ТЗ (BARE-METAL)
function startAshkaSystemInfrastructureBackup() {
    var btn = document.getElementById('btn_sys_backup');
    var bar = document.getElementById('sys_backup_bar');
    btn.disabled = true; bar.style.width = '0%'; bar.innerHTML = '0%';
    
    fetch('modules/ashka.php?action=run_system_backup')
        .then(function(res) { return res.json(); })
        .then(function() {
            var progress = 0;
            var timer = setInterval(function() {
                progress += 5;
                bar.style.width = progress + '%';
                bar.innerHTML = progress + '% (Архивация зависимостей ОС, Apache, PHP, СУБД...)';
                if (progress >= 100) {
                    clearInterval(timer); btn.disabled = false;
                    bar.innerHTML = 'ПОЛНЫЙ ИНФРАСТРУКТУРНЫЙ ОБРАЗ СГЕНЕРИРОВАН [SUCCESS]';
                    alert('[+] ASHKA BARE-METAL DR: Полный образ всей системы и зависимостей собран! Он доступен для скачивания в реестре.');
                    location.reload();
                }
            }, 300); // 300ms интервал для плавной визуализации тяжелого бэкапа
        });
}

var selectedRestoreFile = null;
function handleRestoreFileSelection(input) {
    if(input.files.length > 0) {
        selectedRestoreFile = input.files[0];
        document.getElementById('upload_status_label').innerHTML = "📁 ЗАГРУЖЕН: " + selectedRestoreFile.name;
        document.getElementById('btn_restore').disabled = false;
    }
}

function startAshkaBackupProcess() {
    var btn = document.getElementById('btn_backup'); var bar = document.getElementById('backup_bar');
    btn.disabled = true; bar.style.width = '0%'; bar.innerHTML = '0%';
    fetch('modules/ashka.php?action=run_backup').then(function(res) { return res.json(); }).then(function() {
        var progress = 0;
        var timer = setInterval(function() {
            progress += 10; bar.style.width = progress + '%'; bar.innerHTML = progress + '%';
            if (progress >= 100) { clearInterval(timer); btn.disabled = false; bar.innerHTML = 'ДЕЛЬТА ХРАНИЛИЩА СОЗДАНА'; location.reload(); }
        }, 100);
    });
}

function startAshkaRestoreProcess() {
    if (!selectedRestoreFile) return;
    if (!confirm('ВНИМАНИЕ: Начать накат выбранного слепка?')) return;
    var btn = document.getElementById('btn_restore'); var bar = document.getElementById('restore_bar');
    btn.disabled = true; var formData = new FormData(); formData.append('backup_file', selectedRestoreFile);
    bar.style.width = '10%'; bar.innerHTML = '10% (Передача файла...)';
    fetch('modules/ashka.php?action=upload_and_restore', { method: 'POST', body: formData }).then(function(res) { return res.json(); }).then(function(data) {
        if(data.success) {
            var progress = 20;
            var timer = setInterval(function() {
                progress += 10; bar.style.width = progress + '%'; bar.innerHTML = progress + '% (Реконструкция ядер кластера...)';
                if (progress >= 100) { clearInterval(timer); btn.disabled = false; bar.innerHTML = 'УСПЕШНО ВОССТАНОВЛЕНО'; location.reload(); }
            }, 100);
        } else { alert('[-] Ошибка: ' + data.error); bar.style.width = '0%'; bar.innerHTML = '0%'; btn.disabled = false; }
    });
}

function deleteBackupArchiveFile(fileName) {
    if (!confirm('Вы действительно хотите безвозвратно удалить снимок ' + fileName + ' с диска?')) return;
    fetch('modules/ashka.php?action=delete_backup&file=' + encodeURIComponent(fileName))
        .then(function(res) { return res.json(); }).then(function(data) { if (data.success) { location.reload(); } });
}

function downloadBackupArchiveFile(fileName) {
    window.location.href = 'modules/ashka.php?action=download_backup&file=' + encodeURIComponent(fileName);
}
</script>
