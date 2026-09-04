<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — MODULE: HATSUMI (MAIN WORKSPACE)
 * ==============================================================================
 */

require_once __DIR__ . '/config.php';
session_start();

if (!isset($_SESSION['ishimura_auth']) || $_SESSION['ishimura_auth'] !== true) {
    header('Location: auth.php');
    exit;
}

$active_module = isset($_GET['mod']) ? trim($_GET['mod']) : 'arlechino';
$allowed_modules = ['arlechino', 'arachna', 'miko', 'terror', 'sadako', 'kira', 'oraculus', 'lamia', 'ashka', 'mifiko'];
if (!in_array($active_module, $allowed_modules)) {
    $active_module = 'arlechino';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>ISHIMURA // CORE CONTROLLER: HATSUMI</title>
    <link rel="stylesheet" href="css/cyberpunk.css">
</head>
<body>

    <div class="hatsumi-wrapper">
        <div class="sidebar-panel">
            <div class="sidebar-header">
                <h1 class="system-title">ISHIMURA</h1>
                <small style="color: var(--neon-yellow); font-size: 9px; letter-spacing: 1px;">CORE ENGINE v1.0.0</small>
            </div>
            
            <ul class="sidebar-menu">
                <?php
                $modules_display = [
                    'arlechino' => 'СУБД Arlechino',
                    'arachna'   => 'Сканер Arachna',
                    'miko'      => 'Аналитика Miko',
                    'terror'    => 'Эксплойты Terror',
                    'sadako'    => 'Мониторинг Sadako',
                    'kira'      => 'ИИ Кластер Kira',
                    'oraculus'  => 'Спецификация Oraculus',
                    'lamia'     => 'Ядро Защиты Lamia',
                    'ashka'     => 'Тень Бекапа Ashka',
                    'mifiko'    => 'Контроль Кода Mifiko'
                ];

                foreach ($modules_display as $mod_sys_name => $mod_title) {
                    $active_class = ($active_module === $mod_sys_name) ? 'active' : '';
                    $status_dot_class = 'state-online';
                    
                    if ($mod_sys_name === 'terror') {
                        $status_dot_class = 'state-offline';
                    }

                    echo "<li class='menu-item {$active_class}' id='menu-item-{$mod_sys_name}'>";
                    echo "  <a href='?mod={$mod_sys_name}' onclick='clearModuleActivity(\"{$mod_sys_name}\")'>";
                    echo "    <span>{$mod_title}</span>";
                    echo "    <span class='mod-status-dot {$status_dot_class}'></span>";
                    echo "  </a>";
                    echo "</li>";
                }
                ?>
            </ul>

            <div class="sidebar-status-box">
                <p style="margin: 5px 0;">Монитор Hatsumi: <span class="status-indicator online">ACTIVE</span></p>
                <p style="margin: 5px 0; color: #777;">Оператор: <?php echo htmlspecialchars($_SESSION['username']); ?> | <a href="auth.php?logout=1" style="color: var(--neon-magenta); text-decoration: none;">[ВЫХОД]</a></p>
            </div>
        </div>

        <div class="main-content-panel">
            <?php 
                $target_module_file = __DIR__ . "/modules/" . $active_module . ".php";
                if (file_exists($target_module_file)) {
                    include $target_module_file;
                } else {
                    echo "<h3 class='text-danger'>[-] Ошибка архитектуры: Компонент отображения интерфейса '$active_module' не найден.</h3>";
                }
            ?>
        </div>
    </div>

    <script src="js/core.js"></script>
</body>
</html>
