<?php
/**
 * ==============================================================================
 * SYSTEM ISHIMURA — MODULE: HATSUMI (AUTHENTICATION)
 * ==============================================================================
 * Описание: Защищенный модуль авторизации администратора. Заставляет сменить
 *           дефолтные учетные данные admin/admin при первом логине.
 * ==============================================================================
 */

require_once __DIR__ . '/config.php';
session_start();

// Если пользователь уже авторизован, отправляем в корень панели
if (isset($_SESSION['ishimura_auth']) && $_SESSION['ishimura_auth'] === true) {
    header('Location: index.php');
    exit;
}

$error_msg = "";
$force_password_change = false;

// Подключение к БД для проверки существования/смены учетных данных администратора
try {
    $dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Автоматическое создание таблицы пользователей для Hatsumi, если её еще нет
    $pdo->exec("CREATE TABLE IF NOT EXISTS system_users (
        id SERIAL PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password_hash VARCHAR(255) NOT NULL,
        is_default BOOLEAN DEFAULT TRUE
    );");

    // Если таблица пуста, создаем дефолтного admin/admin
    $stmt = $pdo->query("SELECT COUNT(*) FROM system_users");
    if ($stmt->fetchColumn() == 0) {
        $default_hash = password_hash('admin', PASSWORD_BCRYPT);
        $pdo->exec("INSERT INTO system_users (username, password_hash, is_default) VALUES ('admin', '$default_hash', true);");
    }
} catch (PDOException $e) {
    die("[-] Критическая ошибка подключения к Hatsumi DB: " . $e->getMessage());
}

// Обработка попытки входа или смены пароля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login_action'])) {
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);
        
        $stmt = $pdo->prepare("SELECT * FROM system_users WHERE username = :user");
        $stmt->execute(['user' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['is_default'] && $username === 'admin' && $password === 'admin') {
                // Если реквизиты дефолтные, взводим флаг принудительной смены пароля
                $force_password_change = true;
                $_SESSION['temp_user_id'] = $user['id'];
            } else {
                // Обычный успешный вход
                $_SESSION['ishimura_auth'] = true;
                $_SESSION['username'] = $user['username'];
                header('Location: index.php');
                exit;
            }
        } else {
            $error_msg = "[-] ОТКАЗАНО В ДОСТУПЕ: Нарушение сигнатуры ключа.";
        }
    } elseif (isset($_POST['change_password_action']) && isset($_SESSION['temp_user_id'])) {
        $new_pass = trim($_POST['new_password']);
        if (strlen($new_pass) < 6) {
            $error_msg = "[-] Ошибка: Длина пароля должна быть не менее 6 символов.";
            $force_password_change = true;
        } else {
            $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE system_users SET password_hash = :hash, is_default = false WHERE id = :id");
            $stmt->execute(['hash' => $new_hash, 'id' => $_SESSION['temp_user_id']]);
            
            unset($_SESSION['temp_user_id']);
            $error_msg = "[+] Пароль успешно изменен! Выполните вход с новыми данными.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>ISHIMURA // AUTH INTERFACE</title>
    <link rel="stylesheet" href="css/cyberpunk.css">
    <style>
        .auth-container {
            width: 400px;
            margin: 15vh auto;
            background: var(--panel-bg);
            padding: 30px;
            border: 1px solid var(--neon-magenta);
            box-shadow: 0 0 20px rgba(255, 0, 85, 0.3);
        }
        .form-group { margin-bottom: 20px; }
        .cyber-input {
            width: 100%; padding: 12px; background: #000; border: 1px solid var(--border-color);
            color: var(--neon-cyan); font-family: inherit; box-sizing: border-box;
        }
        .cyber-input:focus { outline: none; border-color: var(--neon-cyan); }
    </style>
</head>
<body>
    <div class="auth-container">
        <h2 class="cyber-title" style="text-align: center;">ISHIMURA TERMINAL</h2>
        
        <?php if (!empty($error_msg)): ?>
            <p style="color: var(--neon-yellow); font-size: 12px;"><?php echo htmlspecialchars($error_msg); ?></p>
        <?php endif; ?>

        <?php if (!$force_password_change): ?>
            <!-- Форма стандартного входа -->
            <form method="POST" action="">
                <div class="form-group">
                    <label>ИДЕНТИФИКАТОР:</label>
                    <input type="text" name="username" class="cyber-input" required autocomplete="off" placeholder="admin">
                </div>
                <div class="form-group">
                    <label>КЛЮЧ ДОСТУПА:</label>
                    <input type="password" name="password" class="cyber-input" required placeholder="•••••">
                </div>
                <button type="submit" name="login_action" class="cyber-btn" style="width: 100%;">ИНИЦИАЛИЗИРОВАТЬ ВХОД</button>
            </form>
        <?php else: ?>
            <!-- Форма принудительной смены дефолтного пароля -->
            <p class="text-danger" style="color: var(--neon-magenta);">[ВНИМАНИЕ] Обнаружен первичный вход. Требуется немедленная смена стандартного пароля!</p>
            <form method="POST" action="">
                <div class="form-group">
                    <label>НОВЫЙ БОЕВОЙ ПАРОЛЬ:</label>
                    <input type="password" name="new_password" class="cyber-input" required placeholder="Минимум 6 символов">
                </div>
                <button type="submit" name="change_password_action" class="cyber-btn" style="width: 100%;">ОБНОВИТЬ И ЗАШИФРОВАТЬ</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
