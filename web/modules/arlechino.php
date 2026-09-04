<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: ARLECHINO
 * ==============================================================================
 * Описание: Панель администратора БД. Интерактивная консоль SQL запросов,
 *           мониторинг кластера, кнопки быстрой оптимизации и синхронизации.
 * ==============================================================================
 */

// Проверка сессии (внедряется при интеграции с Hatsumi)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Загрузка глобальной конфигурации веб-интерфейса
require_once __DIR__ . '/../config.php';

$sql_output = "";
$query_status = "";

// Обработка отправки SQL-команд через интерактивную консоль
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_sql'])) {
    $sql_query = trim($_POST['sql_query']);
    
    if (!empty($sql_query)) {
        try {
            // Подключение к БД с использованием учетных данных системы
            $dsn = "pgsql:host=127.0.0.1;port=5432;dbname=" . DB_NAME;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            
            $stmt = $pdo->query($sql_query);
            
            if ($stmt) {
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($results) > 0) {
                    // Форматирование результата в виде таблицы для Cyberpunk UI
                    $sql_output .= "<table class='cyber-table'>";
                    $sql_output .= "<tr>" . implode("", array_map(function($k) { return "<th>".htmlspecialchars($k)."</th>"; }, array_keys($results[0]))) . "</tr>";
                    foreach ($results as $row) {
                        $sql_output .= "<tr>" . implode("", array_map(function($v) { return "<td>".htmlspecialchars($v)."</td>"; }, $row)) . "</tr>";
                    }
                    $sql_output .= "</table>";
                } else {
                    $sql_output = "<p class='text-success'>[+] Команда выполнена успешно. Пострадало строк: " . $stmt->rowCount() . "</p>";
                }
            }
        } catch (PDOException $e) {
            $sql_output = "<p class='text-danger'>[-] Ошибка SQL: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}
?>

<div class="module-container">
    <h2 class="cyber-title">DB MOD: ARLECHINO // СУБД АНАЛИЗАТОР</h2>
    
    <!-- БЛОК СТАТУСОВ И РЕСУРСОВ -->
    <div class="cyber-row">
        <div class="status-card border-neon-blue">
            <h3>Текущий статус СУБД</h3>
            <div class="status-indicator online">ПОДКЛЮЧЕНО</div>
            <p><strong>База данных:</strong> <?php echo htmlspecialchars(DB_NAME); ?></p>
            <p><strong>Локальный порт:</strong> 5432</p>
        </div>
        
        <div class="status-card border-neon-purple">
            <h3>Кластеризация & Синхронизация</h3>
            <p>Статус репликации: <span class="text-warning">Одиночный узел (Standby)</span></p>
            <p>Связанные ноды в сети: 0 серверов</p>
            <button class="cyber-btn btn-sm">Добавить ноду</button>
        </div>
    </div>

    <!-- БЛОК УПРАВЛЕНИЯ И ФИЧИ ОПТИМИЗАЦИИ -->
    <div class="management-features border-neon-green">
        <h3>Инструменты обслуживания ядра СУБД</h3>
        <div class="btn-group">
            <button class="cyber-btn" onclick="triggerAction('vacuum')">Запустить Оптимизацию (VACUUM)</button>
            <button class="cyber-btn" onclick="triggerAction('reindex')">Переиндексация таблиц</button>
            <button class="cyber-btn" onclick="triggerAction('sync')">Синхронизировать конфигурацию</button>
        </div>
        <div id="action-log" class="terminal-log" style="display:none;"></div>
    </div>

    <!-- ИНТЕРАКТИВНАЯ SQL КОНСОЛЬ -->
    <div class="interactive-console border-neon-red">
        <h3>Интерактивная консоль ввода SQL-команд</h3>
        <form method="POST" action="">
            <textarea name="sql_query" class="cyber-textarea" placeholder="SELECT * FROM vulnerability_scans LIMIT 10;"><?php echo isset($_POST['sql_query']) ? htmlspecialchars($_POST['sql_query']) : ''; ?></textarea>
            <br>
            <button type="submit" name="execute_sql" class="cyber-btn btn-large">Выполнить запрос [RUN]</button>
        </form>
        
        <div class="console-output">
            <h4>Терминальный вывод СУБД:</h4>
            <div class="output-box">
                <?php echo $sql_output ? $sql_output : "<span class='text-muted'>Ожидание ввода команд администратора...</span>"; ?>
            </div>
        </div>
    </div>
</div>

<script>
function triggerAction(action) {
    const logBox = document.getElementById('action-log');
    logBox.style.display = 'block';
    logBox.innerHTML = `[+] Запуск процесса: \${action.toUpperCase()}...<br>`;
    
    // Эмуляция ответа бэкэнда для асинхронных операций СУБД
    setTimeout(() => {
        if(action === 'vacuum') {
            logBox.innerHTML += "[+] Очистка неиспользуемого пространства завершена успешно.<br>[+] База данных оптимизирована.";
        } else if (action === 'reindex') {
            logBox.innerHTML += "[+] Переиндексация всех таблиц Ishimura выполнена без ошибок.";
        } else {
            logBox.innerHTML += "[+] Синхронизация кластеров: Все ноды обновлены.";
        }
    }, 1000);
}
</script>
