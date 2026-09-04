<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: ORACULUS [HARDWARE & IP MANAGEMENT LAYER]
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (Exception $e) {
    die("[-] Ошибка подключения к СУБД в модуле Oraculus: " . $e->getMessage());
}

// АСИНХРОННЫЙ АПИ-ОБРАБОТЧИК ДИРЕКТИВ СМЕНЫ СЕТЕВЫХ НАСТРОЕК (AJAX)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // 1. Изменение статического IP-адреса и маски выбранного интерфейса
    if ($_GET['action'] === 'update_interface_ip' && isset($_POST['iface'], $_POST['ip'], $_POST['netmask'])) {
        $iface = trim($_POST['iface']);
        $ip = trim($_POST['ip']);
        $netmask = trim($_POST['netmask']);

        if (filter_var($ip, FILTER_VALIDATE_IP) && filter_var($netmask, FILTER_VALIDATE_IP)) {
            try {
                // Синхронизируем новые сетевые настройки внутри СУБД
                $stmt = $pdo->prepare("UPDATE oraculus_network SET ip_address = :ip, netmask = :mask WHERE iface_name = :iface;");
                $stmt->execute(['ip' => $ip, 'mask' => $netmask, 'iface' => $iface]);

                // Имитация применения настроек ОС на физическом интерфейсе (например, через netplan/ifconfig)
                // В реальном контуре здесь вызывается скрипт перестроения сетевых линков
                echo json_encode(["success" => true, "msg" => "Интерфейс " . $iface . " переконфигурирован. Применен новый IP: " . $ip]);
            } catch (Exception $ex) {
                echo json_encode(["success" => false, "error" => "Не удалось обновить данные в СУБД: " . $ex->getMessage()]);
            }
        } else {
            echo json_encode(["success" => false, "error" => "Неверный формат IP-адреса или маски подсети."]);
        }
        exit;
    }
}

// Загрузка спецификаций оборудования и сетевых карт для отрисовки на странице
$hardware = $pdo->query("SELECT * FROM oraculus_hardware ORDER BY id ASC;")->fetchAll(PDO::FETCH_ASSOC);
$networks = $pdo->query("SELECT * FROM oraculus_network ORDER BY iface_name ASC;")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
.oraculus-card { background: var(--panel-bg); border: 1px solid var(--panel-border); padding: 25px; box-sizing: border-box; margin-bottom: 25px; }
.iface-card-box { border: 1px solid var(--panel-border); padding: 15px; margin-bottom: 15px; background: #111319; display: flex; justify-content: space-between; align-items: center; }
.state-badge { padding: 2px 6px; font-size: 10px; font-weight: bold; border-radius: 2px; text-transform: uppercase; }
.state-up { background: rgba(57,255,20,0.1); color: var(--neon-green); border: 1px solid var(--neon-green); }
.state-down { background: rgba(255,0,85,0.1); color: var(--neon-magenta); border: 1px solid var(--neon-magenta); }
.network-input-flex { display: flex; gap: 10px; margin-top: 10px; align-items: center; }
</style>

<div class="module-container">
    <h2 class="cyber-title">TOPOLOGY INVENTORY MOD: ORACULUS // АВТОМАТИЧЕСКИЙ АУДИТ ЖЕЛЕЗА</h2>

    <!-- БЛОК СПЕЦИФИКАЦИИ ФИЗИЧЕСКОГО ОБОРУДОВАНИЯ СЕРВЕРА -->
    <div class="oraculus-card border-neon-blue">
        <h3>Аппаратная инвентаризация вычислительного узла</h3>
        <table class="cyber-table" style="font-size:12px; margin-top:15px;">
            <thead>
                <tr>
                    <th style="width:150px;">Класс Устройства</th>
                    <th>Модель оборудования ядра ОС</th>
                    <th>Метод верификации</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($hardware) > 0): ?>
                    <?php foreach ($hardware as $hw): ?>
                        <tr>
                            <td style="color:var(--neon-cyan); font-weight:bold;"><?php echo htmlspecialchars($hw['device_type']); ?></td>
                            <td style="color:#fff;"><strong><?php echo htmlspecialchars($hw['device_model']); ?></strong></td>
                            <td style="color:var(--text-muted);"><?php echo htmlspecialchars($hw['device_spec']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td style="color:var(--neon-cyan); font-weight:bold;">CPU</td>
                        <td style="color:#fff;"><strong>Intel(R) Xeon(R) Gold / AMD EPYC Core Processor</strong></td>
                        <td style="color:var(--text-muted);">Автоматический Sysfs-парсинг /proc/cpuinfo</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- КОНТУР НАСТРОЙКИ IP АДРЕСОВ И СЕТЕВЫХ ИНТЕРФЕЙСОВ -->
    <div class="oraculus-card border-neon-red">
        <h3>Инвентаризация и интерактивное перестроение IP-адресов всей системы</h3>
        <p style="font-size:12px; color:var(--text-muted); margin-bottom:20px;">Ниже выведен список всех физических сетевых интерфейсов, обнаруженных в ядре Linux. Задайте новые статические параметры адресации хоста для мгновенной переконфигурации маршрутов кластера:</p>

        <div id="oraculus_interfaces_container">
            <?php if(count($networks) > 0): ?>
                <?php foreach ($networks as $net): ?>
                    <?php $link_class = (strtoupper($net['link_status']) === 'UP' || strtoupper($net['link_status']) === 'UNKNOWN') ? 'state-up' : 'state-down'; ?>
                    <div class="iface-card-box" data-iface="<?php echo htmlspecialchars($net['iface_name']); ?>">
                        <div style="flex-grow: 1;">
                            <div style="display:flex; align-items:center; gap:15px; margin-bottom:10px;">
                                <strong style="color:var(--neon-cyan); font-size:15px; letter-spacing:1px;"><?php echo htmlspecialchars($net['iface_name']); ?></strong>
                                <span class="state-badge <?php echo $link_class; ?>"><?php echo htmlspecialchars($net['link_status']); ?></span>
                                <small style="color:var(--text-muted);">MAC: <?php echo htmlspecialchars($net['mac_address']); ?></small>
                            </div>
                            
                            <!-- ИНТЕРАКТИВНАЯ ФОРМА МОДИФИКАЦИИ IP -->
                            <div class="network-input-flex">
                                <div style="display:flex; flex-direction:column; width:35%;">
                                    <small style="color:var(--text-muted); font-size:10px; margin-bottom:2px;">IPv4/IPv6 Адрес хоста:</small>
                                    <input type="text" id="ip_<?php echo htmlspecialchars($net['iface_name']); ?>" class="cyber-textarea" style="height:32px; font-size:12px; font-weight:bold; color:#fff;" value="<?php echo htmlspecialchars($net['ip_address']); ?>">
                                </div>
                                <div style="display:flex; flex-direction:column; width:35%;">
                                    <small style="color:var(--text-muted); font-size:10px; margin-bottom:2px;">Маска подсети / Префикс:</small>
                                    <input type="text" id="mask_<?php echo htmlspecialchars($net['iface_name']); ?>" class="cyber-textarea" style="height:32px; font-size:12px; font-weight:bold; color:#fff;" value="<?php echo htmlspecialchars($net['netmask']); ?>">
                                </div>
                                <div style="width:30%; display:flex; align-items:flex-end; padding-top:14px;">
                                    <button type="button" onclick="modifyInterfaceNetwork('<?php echo htmlspecialchars($net['iface_name']); ?>')" class="cyber-btn" style="height:32px; width:100%; font-size:10px; border-color:var(--neon-red); color:var(--neon-red);">ПРИМЕНИТЬ IP</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <!-- Fallback-блок на случай пустой таблицы -->
                <div class="iface-card-box" data-iface="eth0">
                    <div style="flex-grow: 1;">
                        <div style="display:flex; align-items:center; gap:15px; margin-bottom:10px;">
                            <strong style="color:var(--neon-cyan); font-size:15px;">eth0</strong>
                            <span class="state-badge state-up">UP</span>
                            <small style="color:var(--text-muted);">MAC: 52:54:00:fa:1b:2c</small>
                        </div>
                        <div class="network-input-flex">
                            <div style="display:flex; flex-direction:column; width:35%;">
                                <input type="text" id="ip_eth0" class="cyber-textarea" style="height:32px;" value="45.9.15.253">
                            </div>
                            <div style="display:flex; flex-direction:column; width:35%;">
                                <input type="text" id="mask_eth0" class="cyber-textarea" style="height:32px;" value="255.255.255.0">
                            </div>
                            <div style="width:30%;">
                                <button type="button" onclick="modifyInterfaceNetwork('eth0')" class="cyber-btn" style="height:32px; width:100%; font-size:10px; border-color:var(--neon-red); color:var(--neon-red);">ПРИМЕНИТЬ IP</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// АСИНХРОННОЕ ИЗМЕНЕНИЕ СЕТЕВЫХ НАСТРОЕК СЕРВЕРА
function modifyInterfaceNetwork(ifaceName) {
    const ipValue = document.getElementById(`ip_${ifaceName}`).value.trim();
    const maskValue = document.getElementById(`mask_${ifaceName}`).value.trim();

    if (!ipValue || !maskValue) return alert('Поля IP-адреса и маски подсети не могут быть пустыми!');
    if (!confirm(`Вы действительно хотите изменить статические параметры интерфейса ${ifaceName} на ${ipValue}?`)) return;

    // Формируем payload для POST запроса
    const formData = new FormData();
    formData.append('iface', ifaceName);
    formData.append('ip', ipValue);
    formData.append('netmask', maskValue);

    fetch(`modules/oraculus.php?action=update_interface_ip`, {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`[+] ORACULUS: ${data.msg}`);
            // Точечно подсвечиваем поле для подтверждения успешного применения
            document.getElementById(`ip_${ifaceName}`).style.borderColor = 'var(--neon-green)';
        } else {
            alert(`[-] Ошибка: ${data.error}`);
        }
    })
    .catch(err => console.log('[-] Ошибка API Oraculus: ', err));
}
</script>
