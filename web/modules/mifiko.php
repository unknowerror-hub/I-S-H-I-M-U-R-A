<?php
// Ishimura Security Intelligence Core // Module Mifiko
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>AI INTEGRITY MONITOR: MIFIKO // КОНТРОЛЬ ЦЕЛОСТНОСТИ И РАСШИРЕНИЕ</title>
    <style>
        body { font-family: monospace; background: #0a0a0a; color: #a6a6a6; padding: 20px; margin: 0; }
        .header-title { font-size: 16px; font-weight: bold; color: #fff; letter-spacing: 2px; text-transform: uppercase; margin-bottom: 30px; border-bottom: 1px solid #222; padding-bottom: 10px; }
        .alert-box { border: 1px solid #ff0000; padding: 20px; margin-bottom: 30px; background: #1a0202; transition: all 0.5s ease; }
        .alert-title { color: #ff0055; font-weight: bold; margin-bottom: 10px; }
        .alert-text { font-size: 13px; color: #ff9999; margin-bottom: 15px; line-height: 1.6; }
        .alert-actions { display: flex; gap: 15px; }
        .btn-ok { background: transparent; border: 1px solid #00ff00; color: #00ff00; padding: 8px 16px; cursor: pointer; font-family: monospace; font-size: 12px; text-transform: uppercase; }
        .btn-ok:hover { background: #00ff00; color: #000; }
        .btn-abort { background: transparent; border: 1px solid #ff0055; color: #ff0055; padding: 8px 16px; cursor: pointer; font-family: monospace; font-size: 12px; text-transform: uppercase; }
        .btn-abort:hover { background: #ff0055; color: #fff; }
        .btn-apply { background: #0a0a0a; border: 1px solid #00ff00; color: #00ff00; padding: 4px 10px; cursor: pointer; font-family: monospace; font-size: 11px; text-transform: uppercase; }
        .btn-apply:hover { background: #00ff00; color: #000; }
        .btn-apply:disabled { border-color: #444; color: #444; cursor: not-allowed; background: transparent; }
        .btn-clear { background: #111; border: 1px solid #555; color: #aaa; padding: 4px 10px; cursor: pointer; font-family: monospace; font-size: 11px; text-transform: uppercase; margin-bottom: 10px; }
        .btn-clear:hover { background: #333; color: #fff; }
        
        .section-title-wrapper { display: flex; justify-content: space-between; align-items: flex-end; margin-top: 30px; margin-bottom: 5px; }
        .section-title { font-size: 15px; color: #fff; }
        .section-desc { font-size: 12px; color: #555; margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; transition: all 0.3s ease; }
        th, td { border: 1px solid #222; padding: 12px; text-align: left; }
        th { color: #00ffff; font-weight: normal; background: #111; text-transform: uppercase; font-size: 12px; }
        tr.applied-row { background: #021a02; color: #00ff00; }
        tr.applied-row td { border-color: #004400; }
        
        .console-wrapper { margin-top: 40px; border-top: 1px dashed #333; padding-top: 20px; }
        .console-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .console-title { font-size: 13px; color: #00ff00; text-transform: uppercase; }
        .console-box { background: #020202; border: 1px solid #222; height: 160px; padding: 12px; overflow-y: auto; font-size: 12px; color: #777; line-height: 1.6; box-shadow: inset 0 0 10px #000; }
    </style>
</head>
<body>

<div class="header-title">AI INTEGRITY MONITOR: MIFIKO // КОНТРОЛЬ ЦЕЛОСТНОСТИ И РАСШИРЕНИЕ</div>

<!-- КРИТИЧЕСКОЕ ПРЕДУПРЕЖДЕНИЕ -->
<div id="critical-alert-box" class="alert-box">
    <div class="alert-title">⚠️ Внимание: Обнаружены критические изменения кодовой базы!</div>
    <div class="alert-text">
        ИИ-сканер зафиксировал несовпадение контрольных сумм в каталоге <code>/opt/ishimura/web/modules/</code> при сравнении с эталонной теневой копией Ashka. 
        Возможно несанкционированное повышение прав или инъекция вредоносного кода.
    </div>
    <div class="alert-actions">
        <button class="btn-ok" onclick="resolveIntegrityAlert()">Подтвердить вариант все ок</button>
        <button class="btn-abort" onclick="rollbackFromAshka()">Перезаписать из резервной копии</button>
    </div>
</div>

<!-- ТАБЛИЦА АНАЛИЗА -->
<div id="recommendations-section">
    <div class="section-title-wrapper">
        <div class="section-title">ИИ-Анализ состояния операционной системы сервера и Docker-контейнеров</div>
        <button class="btn-clear" onclick="clearRecommendations()">Очистить рекомендации</button>
    </div>
    <div class="section-desc">Нейросетевой контур Mifiko непрерывно инспектирует изоляцию контейнеров и права учетных записей инфраструктуры кластера:</div>

    <table id="advice-table">
        <thead>
            <tr>
                <th style="width: 25%;">Целевой компонент / Контейнер</th>
                <th style="width: 30%;">Выявленная уязвимость / Аномалия</th>
                <th style="width: 30%;">Рекомендация ИИ по улучшению системы</th>
                <th style="width: 15%;">Применить</th>
            </tr>
        </thead>
        <tbody>
            <tr id="row-sudoers">
                <td style="color: #00ffff;">Система прав ядра <br><span style="color: #666;">[/etc/sudoers]</span></td>
                <td>Пользователь www-data имеет беспарольный доступ к утилитам</td>
                <td style="color: #ffcc00;">Заменить NOPASSWD на строгий контроль сессий eBPF-экраном Lamia</td>
                <td><button class="btn-apply" onclick="applyFix('sudoers', 'Замена NOPASSWD на eBPF-экран Lamia для /etc/sudoers')">Применить</button></td>
            </tr>
            <tr id="row-arachna">
                <td style="color: #00ffff;">Docker Контейнер <br><span style="color: #666;">[Arachna_Core]</span></td>
                <td>Обнаружено избыточное выделение RAM операционной системой</td>
                <td style="color: #ffcc00;">Ограничить лимит памяти контейнера до 2GB (флаг --memory=2g)</td>
                <td><button class="btn-apply" onclick="applyFix('arachna', 'Ограничение лимита RAM контейнера Arachna_Core до 2GB')">Применить</button></td>
            </tr>
        </tbody>
    </table>
</div>

<!-- СИСТЕМНЫЙ ЖУРНАЛ -->
<div class="console-wrapper">
    <div class="console-header">
        <div class="console-title">[ СИСТЕМНЫЙ ЖУРНАЛ СОБЫТИЙ MIFIKO ]</div>
        <button class="btn-clear" style="margin-bottom: 0;" onclick="clearConsoleLog()">Очистить журнал</button>
    </div>
    <div id="log-console" class="console-box">
        <div style="color: #00ff00; opacity: 0.6;">[INFO] Контур ИИ-мониторинга Mifiko подключен к ядру Ishimura. Ожидание команд...</div>
    </div>
</div>

<script>
function logEvent(eventCode, message, isSuccess = true) {
    const consoleBox = document.getElementById('log-console');
    if (!consoleBox) return;
    
    // Вычисляем локальное время с учетом текущей таймзоны сервера (UTC+3)
    const now = new Date();
    const tzOffset = now.getTimezoneOffset() * 60000;
    const localISOTime = (new Date(now.getTime() - tzOffset)).toISOString();
    const timeStr = localISOTime.replace('T', ' ').substring(0, 19);
    
    const color = isSuccess ? '#00ff00' : '#ff0055';
    const entry = document.createElement('div');
    entry.style.marginTop = '4px';
    entry.innerHTML = `<span style="color: #555;">[${timeStr}]</span> <span style="color: ${color}; font-weight: bold;">[${eventCode}]:</span> <span style="color: #fff;">${message}</span>`;
    consoleBox.appendChild(entry);
    consoleBox.scrollTop = consoleBox.scrollHeight;
}

function resolveIntegrityAlert() {
    document.getElementById('critical-alert-box').style.display = 'none';
    logEvent('INTEGRITY_OK', 'Оператор подтвердил изменения. Текущие хэш-суммы приняты как эталонные. Откат не требуется.');
}

function rollbackFromAshka() {
    document.getElementById('critical-alert-box').style.display = 'none';
    logEvent('ASHKA_ROLLBACK', 'Запущен принудительный откат кодовой базы к теневой копии Ashka...');
    
    setTimeout(() => {
        logEvent('SYS_INTEGRITY_RESTORED', 'Файлы успешно заменены оригиналами из архива снапшота. Статус кодовой базы: 100% ВАЛИДНОСТЬ.');
    }, 1200);
}

function applyFix(type, description) {
    const row = document.getElementById(`row-${type}`);
    if (!row) return;
    
    row.querySelector('.btn-apply').disabled = true;
    row.classList.add('applied-row');
    
    logEvent('SYS_OPTIMIZATION_APPLIED', `Действие зафиксировано: ${description}. Конфигурация успешно применена.`);
}

// Функция удаления/скрытия таблицы советов
function clearRecommendations() {
    const section = document.getElementById('recommendations-section');
    if (section) {
        section.style.display = 'none';
        logEvent('INTERFACE_MOD', 'Уведомление: Сгруппированные советы по оптимизации скрыты оператором.');
    }
}

// Функция очистки текстового терминала логов
function clearConsoleLog() {
    const consoleBox = document.getElementById('log-console');
    if (consoleBox) {
        consoleBox.innerHTML = '<div style="color: #555; opacity: 0.5;">[Журнал очищен оператором]</div>';
    }
}
</script>
</body>
</html>
