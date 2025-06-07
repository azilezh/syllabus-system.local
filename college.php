<?php
session_start();
require_once 'includes/config.php';

// Получение данных пользователя
$user_name = 'Гость';
$role = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $user['name'] ?? 'Пользователь';
    $role = $_SESSION['role'] ?? null;
}

// Получение направлений колледжа
try {
    $stmt = $pdo->query("SELECT id, name FROM directions WHERE type = 'college'");
    $directions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Получение курсов для каждого направления
    $courses = [];
    foreach ($directions as $direction) {
        $stmt = $pdo->prepare("SELECT id, title, description FROM courses WHERE direction_id = ?");
        $stmt->execute([$direction['id']]);
        $courses[$direction['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Ошибка загрузки данных: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Колледж - Система управления силлабусами</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f0f8ff;
            font-family: 'Open Sans', sans-serif;
        }
        .navbar {
            background-color: #005B99;
        }
        .navbar-brand, .nav-link {
            color: #ffffff !important;
        }
        .nav-link:hover {
            color: #4FC3F7 !important;
        }
        .container {
            margin-top: 2rem;
        }
        .card {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }
        .btn-primary {
            background-color: #005B99;
            border-color: #005B99;
        }
        .btn-primary:hover {
            background-color: #4FC3F7;
            border-color: #4FC3F7;
        }
        .direction-title {
            color: #005B99;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Система управления силлабусами</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="college.php">Колледж</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bachelor.php">Бакалавр</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="calendar.php">Календарь</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cabinet.php">Мой кабинет</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php"><?php echo $role ? 'Выйти' : 'Войти'; ?></a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="text-center mb-4" style="color: #005B99;">Направления колледжа</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <?php if (empty($directions)): ?>
                <div class="alert alert-info">Направления колледжа отсутствуют.</div>
            <?php else: ?>
                <?php foreach ($directions as $direction): ?>
                    <div class="card">
                        <div class="card-body">
                            <h3 class="direction-title"><?php echo htmlspecialchars($direction['name']); ?></h3>
                            <?php if (empty($courses[$direction['id']])): ?>
                                <p>Курсы для этого направления отсутствуют.</p>
                            <?php else: ?>
                                <h5>Курсы:</h5>
                                <ul>
                                    <?php foreach ($courses[$direction['id']] as $course): ?>
                                        <li>
                                            <strong><?php echo htmlspecialchars($course['title']); ?></strong>
                                            <?php if ($course['description']): ?>
                                                <p><?php echo htmlspecialchars($course['description']); ?></p>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>