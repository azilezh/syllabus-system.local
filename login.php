<?php
session_start();
require_once 'includes/config.php'; // Подключение к базе данных

// Проверка, если пользователь уже вошел
if (isset($_SESSION['user_id'])) {
    header('Location: cabinet.php'); // Перенаправление в личный кабинет
    exit;
}

// Обработка формы входа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login']);
    $password = trim($_POST['password']);
    $role_id = intval($_POST['role']);

    // Проверка введенных данных
    if (!empty($login) && !empty($password) && $role_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT u.id, u.login, u.password, u.role_id, r.role_name 
                                   FROM users u 
                                   JOIN roles r ON u.role_id = r.id 
                                   WHERE u.login = ? AND u.role_id = ?");
            $stmt->execute([$login, $role_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['password'] === $password) {
                // Успешный вход
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role_name'];
                header('Location: cabinet.php'); // Перенаправление в личный кабинет
                exit;
            } else {
                $error = "Неверный логин, пароль или роль!";
            }
        } catch (PDOException $e) {
            $error = "Ошибка: " . $e->getMessage();
        }
    } else {
        $error = "Заполните все поля!";
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Система управления силлабусами</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f8ff; /* Светло-голубой фон */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            font-family: 'Open Sans', sans-serif;
        }
        .login-container {
            background-color: #ffffff; /* Белый фон для формы */
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        .btn-primary {
            background-color: #005B99; /* Синий */
            border-color: #005B99;
        }
        .btn-primary:hover {
            background-color: #4FC3F7; /* Голубой */
            border-color: #4FC3F7;
        }
        .form-control:focus {
            border-color: #4FC3F7;
            box-shadow: 0 0 5px rgba(79, 195, 247, 0.5);
        }
        .alert {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="text-center mb-4" style="color: #005B99;">Вход в систему</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" action="">
            <div class="mb-3">
                <label for="login" class="form-label">Логин</label>
                <input type="text" class="form-control" id="login" name="login" required>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Пароль</label>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
            <div class="mb-3">
                <label for="role" class="form-label">Роль</label>
                <select class="form-select" id="role" name="role" required>
                    <option value="">Выберите роль</option>
                    <option value="1">Администратор</option>
                    <option value="2">Преподаватель</option>
                    <option value="3">Студент</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-100">Войти</button>
        </form>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>