<?php
session_start();
require_once 'includes/config.php';

// Проверка авторизации
$user_name = 'Гость';
$role = null;
$user_id = null;
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_name = $user['name'] ?? 'Пользователь';
        $role = $_SESSION['role'] ?? null;
        $user_id = $_SESSION['user_id'];
    } catch (PDOException $e) {
        error_log($e->getMessage(), 3, 'errors.log');
        $error = "Ошибка загрузки данных пользователя.";
    }
}

// Получение событий
try {
    $events = [];
    if ($role === 'admin') {
        $stmt = $pdo->query("SELECT e.id, e.title, e.start_time AS start, e.end_time AS end, c.title AS course_title 
                             FROM events e 
                             LEFT JOIN courses c ON e.course_id = c.id");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT e.id, e.title, e.start_time AS start, e.end_time AS end, c.title AS course_title 
                               FROM events e 
                               LEFT JOIN courses c ON e.course_id = c.id 
                               WHERE e.created_by = ? OR EXISTS (
                                   SELECT 1 FROM study_groups g 
                                   WHERE g.course_id = e.course_id AND g.teacher_id = ?
                               )");
        $stmt->execute([$user_id, $user_id]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($role === 'student') {
        $stmt = $pdo->prepare("SELECT e.id, e.title, e.start_time AS start, e.end_time AS end, c.title AS course_title 
                               FROM events e 
                               LEFT JOIN courses c ON e.course_id = c.id 
                               JOIN enrollments en ON e.course_id = en.course_id 
                               WHERE en.student_id = ?");
        $stmt->execute([$user_id]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->query("SELECT e.id, e.title, e.start_time AS start, e.end_time AS end, c.title AS course_title 
                             FROM events e 
                             LEFT JOIN courses c ON e.course_id = c.id 
                             WHERE e.course_id IS NULL");
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Форматирование событий для FullCalendar
    $calendar_events = array_map(function($event) {
        // Сохраняем время без смещения часового пояса
        return [
            'id' => $event['id'],
            'title' => $event['title'] . ($event['course_title'] ? ' (' . $event['course_title'] . ')' : ''),
            'start' => $event['start'], // Используем исходное время из БД
            'end' => $event['end'] ?: null,
            'backgroundColor' => '#005B99',
            'borderColor' => '#005B99'
        ];
    }, $events);
} catch (PDOException $e) {
    error_log($e->getMessage(), 3, 'errors.log');
    $error = "Ошибка загрузки событий: " . htmlspecialchars($e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Календарь - Система управления</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/main.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
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
            max-width: 1200px;
        }
        #calendar {
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin: 0 auto;
        }
        .btn-primary {
            background-color: #005B99;
            border-color: #005B99;
        }
        .btn-primary:hover {
            background-color: #4FC3F7;
            border-color: #4FC3F7;
        }
        .fc-event {
            cursor: pointer;
        }
        .alert {
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
                        <a class="nav-link" href="college.php">Колледж</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="bachelor.php">Бакалавр</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="calendar.php">Календарь</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cabinet.php">Мой кабинет</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo $role ? 'logout.php' : 'login.php'; ?>">
                            <?php echo $role ? 'Выйти' : 'Войти'; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="text-center mb-4" style="color: #005B99;">Учебный календарь</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php elseif (empty($calendar_events)): ?>
            <div class="alert alert-info">События отсутствуют. <?php echo $role === 'teacher' ? 'Добавьте событие в личном кабинете.' : ''; ?></div>
        <?php else: ?>
            <div id="calendar"></div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'ru',
                height: 'auto',
                timeZone: 'Asia/Almaty', // Укажи нужный часовой пояс
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: <?php echo json_encode($calendar_events, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
                eventClick: function(info) {
                    var endTime = info.event.end ? '\nКонец: ' + info.event.end.toLocaleString('ru-RU', { timeZone: 'Asia/Almaty' }) : '';
                    alert('Событие: ' + info.event.title + 
                          '\nНачало: ' + info.event.start.toLocaleString('ru-RU', { timeZone: 'Asia/Almaty' }) + 
                          endTime);
                },
                eventDidMount: function(info) {
                    console.log('Событие отрендерено:', {
                        id: info.event.id,
                        title: info.event.title,
                        start: info.event.start.toISOString(),
                        end: info.event.end ? info.event.end.toISOString() : null
                    });
                }
            });
            calendar.render();
        });
    </script>
</body>
</html>