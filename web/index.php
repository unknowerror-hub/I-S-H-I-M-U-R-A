<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — MODULE: HATSUMI [DYNAMIC DAEMON STATUS ENGINE]
 * ==============================================================================
 */
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

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

// ДИНАМИЧЕСКИЙ СЧИТЫВАТЕЛЬ ЖИЗНЕННОГО ЦИКЛА СЛУЖБЫ TERROR
// Проверяем, запущен ли наш асинхронный Reverse Shell сокет на порту 4444
$connection = @fsockopen('127.0.0.1', 4444, $errno, $errstr, 0.2);
$terror_online = is_resource($connection);
if ($terror_online) {
    fclose($connection);
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>ISHIMURA // CORE CONTROLLER</title>
    <link rel="stylesheet" href="css/cyberpunk.css?v=<?php echo time(); ?>">
</head>
<body>
    <div class="hatsumi-wrapper">
        <div class="sidebar-panel">
            <div class="sidebar-header">
                <h1 class="system-title">ISHIMURA</h1>
                <small style="color:var(--neon-yellow); font-size:9px;">CORE ENGINE v1.0.0</small>
            </div>
            <ul class="sidebar-menu">
                <?php
                $modules_display = [
                    'arlechino' => 'СУБД Arlechino', 'arachna' => 'Сканер Arachna',
                    'miko' => 'Аналитика Miko', 'terror' => 'Эксплойты Terror',
                    'sadako' => 'Мониторинг Sadako', 'kira' => 'ИИ Кластер Kira',
                    'oraculus' => 'Спецификация Oraculus', 'lamia' => 'Ядро Защиты Lamia',
                    'ashka' => 'Тень Бекапа Ashka', 'mifiko' => 'Контроль Кода Mifiko'
                ];
                foreach ($modules_display as $name => $title) {
                    $act = ($active_module === $name) ? 'active' : '';
                    
                    // Переключение светодиода на основе реального состояния порта
                    if ($name === 'terror') {
                        $dot = $terror_online ? 'state-online' : 'state-offline';
                    } else {
                        $dot = 'state-online'; // Дефолтный статус для остальных активных веб-компонентов
                    }
                    
                    echo "<li class='menu-item {$act}' id='menu-item-{$name}'>";
                    echo "  <a href='?mod={$name}' onclick='clearModuleActivity(\"{$name}\")'>";
                    echo "    <span>{$title}</span><span class='mod-status-dot {$dot}'></span>";
                    echo "  </a>";
                    echo "</li>";
                }
                ?>
            </ul>
            <div class="sidebar-status-box">
                <p style="margin:5px 0;">Монитор Hatsumi: <span class="status-indicator online">ACTIVE</span></p>
                <p style="margin:5px 0; color:#777;">Оператор: admin | <a href="auth.php?logout=1" style="color:var(--neon-magenta); text-decoration:none;">[ВЫХОД]</a></p>
            </div>
        </div>
        <div class="main-content-panel">
            <?php 
                $target = __DIR__ . "/modules/" . $active_module . ".php";
                if (file_exists($target)) { include $target; }
            ?>
        </div>
    </div>
    <script src="js/core.js?v=<?php echo time(); ?>"></script>
</body>
</html>
