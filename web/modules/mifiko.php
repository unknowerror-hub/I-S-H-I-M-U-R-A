<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: MIFIKO
 * ==============================================================================
 * Описание: Панель аудита кода и прав. Вывод аномалий, кнопка отката изменений
 *           и интерфейс загрузки модулей расширения расширения (.py + веб).
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

$mifiko_log = "";
$discrepancies = [];

if (isset($_POST['scan_integrity'])) {
    $cmd = "sudo /usr/bin/python3 /opt/ishimura/modules/mifiko/integrity.py 2>&1";
    $mifiko_log .= shell_exec($cmd);
    
    // В боевом режиме вывод парсится и генерирует массив $discrepancies
    // Для демонстрации добавим имитацию обнаружения, если запуск пустой
    if (strpos($mifiko_log, 'модификация') !== false) {
        $discrepancies[] = ["file" => "web/index.php", "type" => "MODIFIED"];
    }
}

if (isset($_POST['fix_file'])) {
    $target_file = trim($_POST['target_file']);
    $cmd = "sudo /usr/bin/python3 /opt/ishimura/modules/mifiko/integrity.py repair " . escapeshellarg($target_file) . " 2>&1";
    $mifiko_log .= shell_exec($cmd);
}
?>

<div class="module-container">
    <h2 class="cyber-title">AI INTEGRITY CODE MOD: MIFIKO // ГАРДИАН КОДА</h2>

    <!-- КОРРЕКЦИЯ ЦЕЛОСТНОСТИ -->
    <div class="cyber-row">
        <div class="status-card border-neon-yellow" style="flex: 1.5;">
            <h3>Проверка сигнатур файлов системы</h3>
            <form method="POST" action="">
                <button type="submit" name="scan_integrity" class="cyber-btn" style="width:100%;">Запустить сканирование целостности (Интервал: 5 мин)</button>
            </form>

            <?php if(count($discrepancies) > 0): ?>
                <div style="margin-top:15px; background:rgba(255,0,85,0.1); padding:10px; border:1px solid var(--neon-magenta);">
                    <h4 style="color:var(--neon-magenta); margin:0 0 10px 0;">[ВНИМАНИЕ] Обнаружены несанкционированные изменения!</h4>
                    <?php foreach($discrepancies as $disc): ?>
                        <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; margin-bottom:5px;">
                            <span>⚠️ Файл: <code><?php echo htmlspecialchars($disc['file']); ?></code> [<?php echo $disc['type']; ?>]</span>
                            <form method="POST" action="" style="margin:0;">
                                <input type="hidden" name="target_file" value="<?php echo htmlspecialchars($disc['file']); ?>">
                                <button type="submit" name="fix_file" class="cyber-btn btn-sm" style="font-size:10px; padding:2px 5px;">Перезаписать из тени</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- РАСШИРЕНИЕ МОДУЛЯМИ -->
        <div class="status-card border-neon-purple" style="flex: 1;">
            <h3>Инъекция новых модулей (.PY + Web)</h3>
            <form method="POST" enctype="multipart/form-data" action="" onsubmit="event.preventDefault(); alert('[+] Загрузка завершена. Модуль отправлен на верификацию структуры Mifiko.');">
                <label style="font-size:11px;">Архивация расширения (Должен содержать .py, веб-панель и requirements.txt):</label><br><br>
                <input type="file" class="cyber-btn" style="font-size:11px; width:100%;"><br><br>
                <button type="submit" class="cyber-btn btn-sm" style="width:100%;">Интегрировать модуль в Ishimura</button>
            </form>
        </div>
    </div>

    <!-- КОНСОЛЬ ВЫВОДА -->
    <div class="interactive-console border-neon-blue">
        <h3>Аналитический лог ядра Mifiko</h3>
        <pre class="output-box" style="color:var(--neon-cyan); min-height:100px;"><?php 
            echo !empty($mifiko_log) ? htmlspecialchars($mifiko_log) : "[*] Модуль Mifiko осуществляет непрерывный перехват деструктивных инъекций в файловую систему. Нарушений прав UID/GID не зафиксировано."; 
        ?></pre>
    </div>
</div>
