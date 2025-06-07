<?php
session_start();
require_once 'includes/config.php';

// Получение новостей из базы данных
try {
    $stmt = $pdo->query("SELECT title, content, publish_date FROM news ORDER BY publish_date DESC LIMIT 5");
    $news = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Ошибка загрузки новостей: " . $e->getMessage();
}

// Проверка, вошел ли пользователь
$user_name = 'Гость';
$role = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $user['name'] ?? 'Пользователь';
    $role = $_SESSION['role'] ?? null;
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Главная - Система управления силлабусами</title>
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
        .institute-info {
            background-color: #ffffff;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
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
                        <a class="nav-link active" href="index.php">Главная</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="college.php">Колледж</a>
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
        <div class="institute-info">
            <h2 class="text-center mb-4" style="color: #005B99;">ОУ «Кейин Интернейшнл Институт»</h2>
            <p>Добро пожаловать в ОУ «Кейин Интернейшнл Институт» — ведущий образовательный центр, предлагающий программы колледжа и бакалавриата в области менеджмента, информатики, туризма, переводческого дела и лингвистики. Наша миссия — предоставить студентам современные знания и практические навыки для успешной карьеры.</p>
        </div>

        <h3 class="mb-4" style="color: #005B99;">Новости</h3>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php else: ?>
            <?php if (empty($news)): ?>
                <div class="alert alert-info">Новостей пока нет.</div>
            <?php else: ?>
                <?php foreach ($news as $item): ?>
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($item['title']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($item['content']); ?></p>
                            <p class="card-text"><small class="text-muted">Опубликовано: <?php echo date('d.m.Y H:i', strtotime($item['publish_date'])); ?></small></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>