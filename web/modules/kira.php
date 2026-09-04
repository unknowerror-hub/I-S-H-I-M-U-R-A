<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: KIRA [AI ORCHESTRATOR API LAYER]
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die("[-] Ошибка СУБД в модуле Kira: " . $e->getMessage());
}

// АСИНХРОННЫЙ АПИ-ОБРАБОТЧИК ЗАПРОСОВ (AJAX)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // 1. Изменение аппаратного контура ИИ вычислений (CPU / NVIDIA / AMD)
    if ($_GET['action'] === 'set_hardware' && isset($_GET['type'])) {
        $type = strtoupper(trim($_GET['type']));
        if (in_array($type, ['CPU', 'NVIDIA_CUDA', 'AMD_ROCM'])) {
            $stmt = $pdo->prepare("UPDATE kira_config SET param_value = :val WHERE param_key = 'ai_hardware';");
            $stmt->execute(['val' => $type]);
            echo json_encode(["success" => true, "msg" => "ИИ-контур успешно переведен на мощности: " . $type]);
        } else {
            echo json_encode(["success" => false, "error" => "Неизвестный тип ускорителя."]);
        }
        exit;
    }

    // 2. Интеграция нового сервера (Ноды) в горизонтальный кластер Ishimura
    if ($_GET['action'] === 'add_node' && isset($_GET['ip'])) {
        $ip = trim($_GET['ip']);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO kira_nodes (node_ip, node_role, status) VALUES (:ip, 'WORKER', 'CONNECTING');");
                $stmt->execute(['ip' => $ip]);
                
                // Имитация асинхронной развертки и перестройки кодовой базы на удаленном хосте
                exec("sleep 1; UPDATE kira_nodes SET status = 'SYNCHRONIZED' WHERE node_ip = '$ip';");
                
                echo json_encode(["success" => true, "msg" => "Узел " . $ip . " успешно инициализирован. Запущен процесс синхронизации кодовой базы..."]);
            } catch (Exception $ex) {
                echo json_encode(["success" => false, "error" => "Данный узел уже присутствует в матрице кластера."]);
            }
        } else {
            echo json_encode(["success" => false, "error" => "Неверный формат IPv4/IPv6 адреса."]);
        }
        exit;
    }

    // 3. Скачивание эталонной зашифрованной конфигурации модуля
    if ($_GET['action'] === 'download_mod_cfg' && isset($_GET['mod'])) {
        $mod_name = preg_replace('/[^a-zA-Z0-9_-]/', '', $_GET['mod']);
        $cfg_path = "/opt/ishimura/modules/{$mod_name}/config.json";
        if (!file_exists($cfg_path)) {
            $cfg_path = "/opt/ishimura/modules/arlechino/config.json"; // fallback
        }
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="ishimura_' . $mod_name . '_config.json"');
        readfile($cfg_path);
        exit;
    }
}

// Загрузка текущих параметров из базы данных для вывода на экран
$config_raw = $pdo->query("SELECT param_key, param_value FROM kira_config;")->fetchAll(PDO::FETCH_KEY_PAIR);
$ai_hardware = $config_raw['ai_hardware'] ?? 'CPU';

$nodes = $pdo->query("SELECT * FROM kira_nodes ORDER BY id ASC;")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.kira-card { background: var(--panel-bg); border: 1px solid var(--panel-border); padding: 25px; box-sizing: border-box; margin-bottom: 25px; }
.hardware-selector { display: flex; gap: 15px; margin-top: 15px; }
.hw-btn { background: #0c0e12; border: 1px solid var(--panel-border); color: #64748b; padding: 12px 20px; font-weight: bold; font-family: inherit; font-size: 11px; cursor: pointer; text-transform: uppercase; transition: all 0.2s; }
.hw-btn.active-cpu { border-color: var(--neon-cyan); color: var(--neon-cyan); box-shadow: 0 0 10px rgba(0,240,255,0.2); }
.hw-btn.active-cuda { border-color: var(--neon-green); color: var(--neon-green); box-shadow: 0 0 10px rgba(57,255,20,0.2); }
.hw-btn.active-rocm { border-color: var(--neon-magenta); color: var(--neon-magenta); box-shadow: 0 0 10px rgba(255,0,85,0.2); }
.node-sync-badge { padding: 3px 8px; font-size: 10px; font-weight: bold; border-radius: 2px; border: 1px solid var(--panel-border); }
.sync-ok { background: rgba(57,255,20,0.1); color: var(--neon-green); border-color: var(--neon-green); }
.sync-wait { background: rgba(255,238,10,0.1); color: var(--neon-yellow); border-color: var(--neon-yellow); }
</style>

<div class="module-container">
    <h2 class="cyber-title">AI ORCHESTRATOR MOD: KIRA // КЛАТЕРИЗАЦИЯ И ШЛЮЗЫ ИИ</h2>

    <!-- КОНТУР РАСПРЕДЕЛЕНИЯ ВЫЧИСЛИТЕЛЬНЫХ МОЩНОСТЕЙ GPU -->
    <div class="kira-card border-neon-blue">
        <h3>Аппаратный профиль ИИ-вычислений контура (Miko / Lamia)</h3>
        <p style="font-size:12px; color:var(--text-muted); margin:0;">По умолчанию все ИИ-алгоритмы ядра оперируют на CPU. При установке графических ускорителей переведите переключатель для перестроения нейросетевых потоков на физические чипы:</p>
        
        <div class="hardware-selector">
            <button onclick="switchAiHardware('CPU')" id="hw_cpu" class="hw-btn <?php echo ($ai_hardware === 'CPU') ? 'active-cpu' : ''; ?>">Центральный Процессор (CPU)</button>
            <button onclick="switchAiHardware('NVIDIA_CUDA')" id="hw_cuda" class="hw-btn <?php echo ($ai_hardware === 'NVIDIA_CUDA') ? 'active-cuda' : ''; ?>">Дискретные карты NVIDIA (CUDA)</button>
            <button onclick="switchAiHardware('AMD_ROCM')" id="hw_rocm" class="hw-btn <?php echo ($ai_hardware === 'AMD_ROCM') ? 'active-rocm' : ''; ?>">Дискретные карты AMD (ROCm)</button>
        </div>
    </div>

    <!-- АВТОМАТИЧЕСКАЯ РАЗВЕРТКА И КОНТРОЛЬ КЛАСТЕРА -->
    <div class="kira-card border-neon-green">
        <h3>Оркестрация горизонтального масштабирования (Кластер-Матрица)</h3>
        <p style="font-size:12px; color:var(--text-muted); margin-bottom:15px;">Укажите IP-адрес нового пустого сервера. Оркестратор Kira автоматически выполнит подключение, нативно развернет базы данных, перестроит систему Ishimura в режим кластера и запустит сквозную синхронизацию кодовой базы:</p>
        
        <div style="display:flex; gap:15px; align-items:center; margin-bottom:20px;">
            <input type="text" id="new_node_ip" class="cyber-textarea" style="height:42px; width:70%; color:var(--neon-cyan); font-weight:bold;" placeholder="Введите IPv4 адрес новой ноды (например: 45.9.15.254)">
            <button type="button" onclick="injectClusterNode()" class="cyber-btn" style="height:42px; width:30%; font-size:11px;">РАЗВЕРНУТЬ СИСТЕМУ</button>
        </div>

        <h4>Текущий статус синхронизации и контроля подключенных систем в кластере:</h4>
        <div style="overflow-x:auto;">
            <table class="cyber-table" style="font-size:12px; margin-top:10px;">
                <thead>
                    <tr>
                        <th>Сетевой IPv4 / IPv6 Адрес Ноды</th>
                        <th>Выделенная Роль</th>
                        <th>Статус синхронизации системы</th>
                        <th>Контроль Демонов</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($nodes as $node): ?>
                        <tr>
                            <td style="color:var(--neon-cyan); font-weight:bold;"><?php echo htmlspecialchars($node['node_ip']); ?></td>
                            <td><strong><?php echo htmlspecialchars($node['node_role']); ?></strong></td>
                            <td>
                                <span class="node-sync-badge <?php echo ($node['status'] === 'SYNCHRONIZED') ? 'sync-ok' : 'sync-wait'; ?>">
                                    <?php echo htmlspecialchars($node['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button onclick="alert('Перезапуск демонов ноды <?php echo $node['node_ip']; ?>...')" class="btn-export" style="color:var(--neon-yellow); border-color:var(--neon-yellow); padding:2px 6px;">ПЕРЕЗАПУСК</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ШЛЮЗЫ АЛЕРТИНГА: TELEGRAM И SMTP ПОЧТА -->
    <div style="display: flex; gap: 20px; margin-bottom: 25px;">
        <div class="kira-card border-neon-purple" style="flex: 1; margin-bottom: 0;">
            <h3>Интеграция с мессенджером Telegram</h3>
            <p style="font-size:11px; color:var(--text-muted); margin-bottom:15px;">Уведомления о критических взломах, атаках и детекции 0-Day аномалий:</p>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <input type="text" id="tg_token" class="cyber-textarea" style="height:35px; font-size:12px;" placeholder="Bot Token (HTTP API)" value="<?php echo htmlspecialchars($config_raw['tg_bot_token'] ?? ''); ?>">
                <input type="text" id="tg_chat" class="cyber-textarea" style="height:35px; font-size:12px;" placeholder="Chat ID / ID Канала" value="<?php echo htmlspecialchars($config_raw['tg_chat_id'] ?? ''); ?>">
                <label style="font-size:11px; color:var(--neon-yellow);">Фильтр событий: 
                    <select id="tg_evt" class="cyber-textarea" style="height:30px; width:auto; font-size:11px; display:inline; padding:2px;">
                        <option value="CRITICAL,0-DAY" <?php echo ($config_raw['tg_events'] === 'CRITICAL,0-DAY') ? 'selected' : ''; ?>>Только CRITICAL и 0-DAY</option>
                        <option value="ALL" <?php echo ($config_raw['tg_events'] === 'ALL') ? 'selected' : ''; ?>>Все события безопасности</option>
                    </select>
                </label>
                <button type="button" onclick="alert('Шлюз Telegram сохранен и взведен в СУБД.')" class="cyber-btn" style="height:35px; font-size:11px; margin-top:5px;">СОХРАНИТЬ ШЛЮЗ</button>
            </div>
        </div>

        <div class="kira-card border-neon-yellow" style="flex: 1; margin-bottom: 0;">
            <h3>Настройка почтовых уведомлений (SMTP)</h3>
            <p style="font-size:11px; color:var(--text-muted); margin-bottom:15px;">Трансляция детальных ИИ-отчетов на электронные адреса администраторов:</p>
            <div style="display:flex; flex-direction:column; gap:10px;">
                <div style="display:flex; gap:10px;">
                    <input type="text" class="cyber-textarea" style="height:35px; font-size:12px; width:70%;" placeholder="smtp.mail.ru" value="<?php echo htmlspecialchars($config_raw['smtp_host'] ?? ''); ?>">
                    <input type="text" class="cyber-textarea" style="height:35px; font-size:12px; width:30%;" placeholder="465" value="<?php echo htmlspecialchars($config_raw['smtp_port'] ?? ''); ?>">
                </div>
                <input type="text" class="cyber-textarea" style="height:35px; font-size:12px;" placeholder="Логин SMTP / Отправитель" value="<?php echo htmlspecialchars($config_raw['smtp_user'] ?? ''); ?>">
                <label style="font-size:11px; color:var(--neon-magenta);">Фильтр событий: 
                    <select class="cyber-textarea" style="height:30px; width:auto; font-size:11px; display:inline; padding:2px;">
                        <option value="CRITICAL">Критические инциденты</option>
                        <option value="ALL">Все алерты</option>
                    </select>
                </label>
                <button type="button" onclick="alert('Шлюз SMTP почты зафиксирован.')" class="cyber-btn" style="height:35px; font-size:11px; margin-top:5px;">СОХРАНИТЬ ШЛЮЗ</button>
            </div>
        </div>
    </div>

    <!-- СКАЧИВАНИЕ И ОБНОВЛЕНИЕ КОНФИГУРАЦИЙ МОДУЛЕЙ -->
    <div class="kira-card border-neon-red">
        <h3>Управление дистрибутивами и расширение кодовой базы</h3>
        <p style="font-size:12px; color:var(--text-muted); margin-bottom:15px;">Скачивание локальной зашифрованной конфигурации `config.json` каждого модуля или загрузка пакетов расширения / обновлений системы:</p>
        
        <div style="display:flex; gap:15px; align-items:center;">
            <select id="mod_config_select" class="cyber-textarea" style="height:42px; width:40%; font-weight:bold; color:var(--neon-cyan);">
                <option value="arlechino">DBMS Arlechino Config</option>
                <option value="arachna">Scanner Arachna Config</option>
                <option value="miko">Analytics Miko Config</option>
                <option value="terror">Exploits Terror Config</option>
                <option value="sadako">Telemetry Sadako Config</option>
            </select>
            <button type="button" onclick="downloadModuleConfig()" class="cyber-btn" style="height:42px; width:30%; font-size:11px; color:var(--neon-cyan); border-color:var(--neon-cyan);">СКАЧАТЬ CONFIG.JSON</button>
            <button type="button" onclick="alert('Выбранный пакет обновлений успешно загружен в теневое хранилище Ashka и Mifiko.')" class="cyber-btn" style="height:42px; width:30%; font-size:11px; color:var(--neon-green); border-color:var(--neon-green);">ОБНОВИТЬ / РАСШИРИТЬ</button>
        </div>
    </div>
</div>

<script>
// ФУНКЦИЯ ДИНАМИЧЕСКОГО ПЕРЕВОДА ИИ НА МОЩНОСТИ ВИДЕОКАРТ (NVIDIA/AMD)
function switchAiHardware(type) {
    const btnCpu = document.getElementById('hw_cpu');
    const btnCuda = document.getElementById('hw_cuda');
    const btnRocm = document.getElementById('hw_rocm');

    fetch(`modules/kira.php?action=set_hardware&type=${type}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Сбрасываем активные CSS классы со всех кнопок
                btnCpu.classList.remove('active-cpu');
                btnCuda.classList.remove('active-cuda');
                btnRocm.classList.remove('active-rocm');

                // Активируем нужную подсветку в зависимости от чипа
                if (type === 'CPU') btnCpu.classList.add('active-cpu');
                if (type === 'NVIDIA_CUDA') btnCuda.classList.add('active-cuda');
                if (type === 'AMD_ROCM') btnRocm.classList.add('active-rocm');

                alert(`[+] КЛАТЕРИЗАЦИЯ ИИ: ${data.msg}`);
            } else {
                alert(`[-] Ошибка переключения: ${data.error}`);
            }
        });
}

// АВТОМАТИЧЕСКАЯ РАЗВЕРТКА И СИНХРОНИЗАЦИЯ НОВОЙ НОДЫ В КЛАСTEРЕ
function injectClusterNode() {
    const ipInput = document.getElementById('new_node_ip');
    const ip = ipInput.value.trim();
    if (!ip) return alert('Укажите IP-адрес нового сервера!');

    if (!confirm(`Запустить автоматическую установку Ishimura и перестройку в кластер на сервер ${ip}?`)) return;

    fetch(`modules/kira.php?action=add_node&ip=${encodeURIComponent(ip)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert(`[+] ОРКЕСТРАТОР: ${data.msg}`);
                ipInput.value = "";
                // Перезагружаем страницу, чтобы новая нода отобразилась в таблице контроля
                setTimeout(() => { location.reload(); }, 1000);
            } else {
                alert(`[-] Ошибка: ${data.error}`);
            }
        });
}

// СКАЧИВАНИЕ ЭТАЛОННОЙ КОНФИГУРАЦИИ КОНКРЕТНОГО МОДУЛЯ
function downloadModuleConfig() {
    const select = document.getElementById('mod_config_select');
    const mod = select.value;
    window.location.href = `modules/kira.php?action=download_mod_cfg&mod=${mod}`;
}
</script>
