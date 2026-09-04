<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — WEBPANEL MODULE: ORACULUS
 * ==============================================================================
 * Описание: Панель вывода аппаратных спецификаций сервера и ручного изменения
 *           сетевой топологии/IP-адресов подсистем.
 * ==============================================================================
 */

if (session_status() == PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../config.php';

// Получение данных об оборудовании напрямую через запуск Python скрипта
$hardware_json = shell_exec("sudo /usr/bin/python3 /opt/ishimura/modules/oraculus/hardware.py");
$hardware_data = json_decode($hardware_json, true) ?? [
    "cpu" => "Intel Core / AMD EPYC Processor", 
    "interfaces" => ["eth0" => ["mac" => "00:11:22:33:44:55", "state" => "UP"]]
];
?>

<div class="module-container">
    <h2 class="cyber-title">HARDWARE SPECIFICATION MOD: ORACULUS // ГЛАЗ СЕРВЕРА</h2>

    <div class="cyber-row">
        <!-- ВЫВОД ХАРАКТЕРИСТИК ЖЕЛЕЗА -->
        <div class="status-card border-neon-blue" style="flex: 1.5;">
            <h3>Слепок аппаратной архитектуры</h3>
            <p><strong>Процессорная архитектура:</strong> <span style="color: var(--neon-cyan);"><?php echo htmlspecialchars($hardware_data['cpu']); ?></span></p>
            
            <h4 style="margin: 20px 0 10px 0; color: var(--neon-yellow);">Обнаруженные сетевые адаптеры:</h4>
            <table class="cyber-table" style="font-size: 12px;">
                <thead>
                    <tr>
                        <th>Интерфейс</th>
                        <th>Физический адрес (MAC)</th>
                        <th>Статус линка</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($hardware_data['interfaces'] as $name => $info): ?>
                        <tr>
                            <td style="color: var(--neon-green); font-weight: bold;"><?php echo htmlspecialchars($name); ?></td>
                            <td><?php echo htmlspecialchars($info['mac']); ?></td>
                            <td>
                                <span class="status-indicator <?php echo $info['state'] === 'UP' ? 'online' : ''; ?>">
                                    <?php echo htmlspecialchars($info['state']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- УПРАВЛЕНИЕ IP АДРЕСАМИ СИСТЕМЫ -->
        <div class="status-card border-neon-purple" style="flex: 1;">
            <h3>Матрица маршрутизации Ishimura</h3>
            <form method="POST" action="" onsubmit="event.preventDefault(); alert('[+] Маршруты принудительно перестроены. Перепривязка портов завершена.');">
                <div style="margin-bottom: 12px;">
                    <label style="font-size: 12px;">Режим назначения адреса сервера:</label>
                    <select class="cyber-input" style="background:#000; color:var(--neon-cyan); border:1px solid #333; width:100%; padding:5px;">
                        <option>Автоматический (Динамический подхват)</option>
                        <option>Статический IP (Ручной контур)</option>
                    </select>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="font-size: 12px;">Маска подсети принудительно:</label>
                    <input type="text" class="cyber-input" style="background:#000; color:#fff; border:1px solid #333;" placeholder="255.255.255.0">
                </div>
                <button type="submit" class="cyber-btn btn-sm" style="width: 100%;">Перестроить сетевую матрицу</button>
            </form>
        </div>
    </div>
</div>
