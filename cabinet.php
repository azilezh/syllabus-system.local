<?php
session_start();
require_once 'includes/config.php';

// Проверка, вошел ли пользователь
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: login.php');
    exit;
}

// Получение данных пользователя
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

try {
    $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $user_name = $user['name'] ?? 'Пользователь';
} catch (PDOException $e) {
    error_log($e->getMessage(), 3, 'errors.log');
    $error = "Ошибка загрузки данных пользователя.";
}

// Создание папки Uploads, если не существует
if (!is_dir('Uploads')) {
    mkdir('Uploads', 0775, true);
}

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Администратор: добавление пользователя
        if ($role === 'admin' && isset($_POST['add_user'])) {
            $login = trim($_POST['login']);
            $password = password_hash(trim($_POST['password']), PASSWORD_DEFAULT); // Хеширование пароля
            $name = trim($_POST['name']);
            $role_id = intval($_POST['role_id']);
            if (!empty($login) && !empty($password) && !empty($name) && $role_id > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE login = ?");
                $stmt->execute([$login]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO users (login, password, name, role_id, status) VALUES (?, ?, ?, ?, 'active')");
                    $stmt->execute([$login, $password, $name, $role_id]);
                    $success = "Пользователь успешно добавлен!";
                } else {
                    $error = "Логин уже занят!";
                }
            } else {
                $error = "Заполните все поля!";
            }
        }

        // Администратор: редактирование пользователя
        if ($role === 'admin' && isset($_POST['edit_user'])) {
            $edit_user_id = intval($_POST['edit_user_id']);
            $login = trim($_POST['edit_login']);
            $name = trim($_POST['edit_name']);
            $role_id = intval($_POST['edit_role_id']);
            $status = $_POST['edit_status'];
            if (!empty($login) && !empty($name) && $role_id > 0) {
                $stmt = $pdo->prepare("UPDATE users SET login = ?, name = ?, role_id = ?, status = ? WHERE id = ?");
                $stmt->execute([$login, $name, $role_id, $status, $edit_user_id]);
                $success = "Пользователь успешно обновлен!";
            } else {
                $error = "Заполните все поля!";
            }
        }

        // Администратор: удаление пользователя
        if ($role === 'admin' && isset($_POST['delete_user'])) {
            $delete_user_id = intval($_POST['user_id']);
            if ($delete_user_id !== $user_id) {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$delete_user_id]);
                $success = "Пользователь успешно удален!";
            } else {
                $error = "Нельзя удалить самого себя!";
            }
        }

        // Администратор: добавление курса
        if ($role === 'admin' && isset($_POST['add_course'])) {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            if (!empty($title)) {
                $stmt = $pdo->prepare("INSERT INTO courses (title, description) VALUES (?, ?)");
                $stmt->execute([$title, $description]);
                $success = "Курс успешно добавлен!";
            } else {
                $error = "Укажите название курса!";
            }
        }

        // Администратор: удаление курса
        if ($role === 'admin' && isset($_POST['delete_course'])) {
            $course_id = intval($_POST['course_id']);
            $stmt = $pdo->prepare("DELETE FROM courses WHERE id = ?");
            $stmt->execute([$course_id]);
            $success = "Курс успешно удален!";
        }

        // Администратор: блокировка/разблокировка
        if ($role === 'admin' && isset($_POST['toggle_status'])) {
            $target_user_id = intval($_POST['user_id']);
            $new_status = $_POST['new_status'];
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $target_user_id]);
            $success = "Статус пользователя успешно изменен!";
        }

        // Преподаватель: добавление силлабуса или материала
        if ($role === 'teacher' && isset($_POST['add_syllabus'])) {
            $course_id = intval($_POST['course_id']);
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            $is_file = isset($_FILES['material_file']) && $_FILES['material_file']['error'] === UPLOAD_ERR_OK;

            if ($course_id > 0 && !empty($title)) {
                if ($is_file) {
                    $file = $_FILES['material_file'];
                    $allowed_types = ['application/pdf', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.pptx'];
                    $max_size = 10 * 1024 * 1024;
                    if (in_array($file['type'], $allowed_types) && $file['size'] <= $max_size) {
                        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $unique_name = uniqid('material_') . '.' . $ext;
                        $upload_path = 'Uploads/' . $unique_name;
                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            $stmt = $pdo->prepare("INSERT INTO materials (course_id, title, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$course_id, $title, $unique_name, $user_id]);
                            $success = "Материал успешно загружен!";
                        } else {
                            $error = "Ошибка при загрузке файла!";
                        }
                    } else {
                        $error = "Недопустимый тип файла или размер превышает 10 МБ!";
                    }
                } elseif (!empty($content)) {
                    $stmt = $pdo->prepare("INSERT INTO syllabuses (course_id, content, created_by) VALUES (?, ?, ?)");
                    $stmt->execute([$course_id, $content, $user_id]);
                    $success = "Силлабус успешно добавлен!";
                } else {
                    $error = "Укажите содержимое силлабуса или загрузите файл!";
                }
            } else {
                $error = "Заполните обязательные поля!";
            }
        }

        // Преподаватель: удаление силлабуса
        if ($role === 'teacher' && isset($_POST['delete_syllabus'])) {
            $syllabus_id = intval($_POST['syllabus_id']);
            $stmt = $pdo->prepare("DELETE FROM syllabuses WHERE id = ? AND created_by = ?");
            $stmt->execute([$syllabus_id, $user_id]);
            $success = "Силлабус успешно удален!";
        }

        // Преподаватель: удаление материала
        if ($role === 'teacher' && isset($_POST['delete_material'])) {
            $material_id = intval($_POST['material_id']);
            $stmt = $pdo->prepare("SELECT file_path FROM materials WHERE id = ? AND uploaded_by = ?");
            $stmt->execute([$material_id, $user_id]);
            $material = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($material) {
                $file_path = 'Uploads/' . $material['file_path'];
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
                $stmt = $pdo->prepare("DELETE FROM materials WHERE id = ? AND uploaded_by = ?");
                $stmt->execute([$material_id, $user_id]);
                $success = "Материал успешно удален!";
            } else {
                $error = "Материал не найден!";
            }
        }

        // Преподаватель: выставление оценки
        if ($role === 'teacher' && isset($_POST['add_grade'])) {
            $student_id = intval($_POST['student_id']);
            $course_id = intval($_POST['course_id']);
            $grade = intval($_POST['grade']);
            $status = $_POST['status'];
            if ($student_id > 0 && $course_id > 0 && $grade >= 0 && $grade <= 100) {
                $stmt = $pdo->prepare("INSERT INTO grades (student_id, course_id, teacher_id, grade, status) 
                                       VALUES (?, ?, ?, ?, ?) 
                                       ON DUPLICATE KEY UPDATE grade = ?, status = ?");
                $stmt->execute([$student_id, $course_id, $user_id, $grade, $status, $grade, $status]);
                $success = "Оценка успешно выставлена!";
            } else {
                $error = "Некорректные данные!";
            }
        }

        // Преподаватель: редактирование оценки
        if ($role === 'teacher' && isset($_POST['edit_grade'])) {
            $grade_id = intval($_POST['grade_id']);
            $grade = intval($_POST['grade']);
            $status = $_POST['status'];
            if ($grade >= 0 && $grade <= 100) {
                $stmt = $pdo->prepare("UPDATE grades SET grade = ?, status = ? WHERE id = ? AND teacher_id = ?");
                $stmt->execute([$grade, $status, $grade_id, $user_id]);
                $success = "Оценка успешно обновлена!";
            } else {
                $error = "Некорректная оценка!";
            }
        }

        // Преподаватель: удаление оценки
        if ($role === 'teacher' && isset($_POST['delete_grade'])) {
            $grade_id = intval($_POST['grade_id']);
            $stmt = $pdo->prepare("DELETE FROM grades WHERE id = ? AND teacher_id = ?");
            $stmt->execute([$grade_id, $user_id]);
            $success = "Оценка успешно удалена!";
        }

        // Преподаватель: добавление события
        if ($role === 'teacher' && isset($_POST['add_event'])) {
            $title = trim($_POST['title']);
            $course_id = intval($_POST['course_id']);
            $start_time = $_POST['start_time'];
            $end_time = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
            if (!empty($title) && $course_id > 0 && !empty($start_time)) {
                $stmt = $pdo->prepare("INSERT INTO events (title, start_time, end_time, course_id, created_by) 
                                       VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$title, $start_time, $end_time, $course_id, $user_id]);
                $success = "Событие успешно добавлено!";
            } else {
                $error = "Заполните обязательные поля!";
            }
        }

        // Преподаватель: удаление события
        if ($role === 'teacher' && isset($_POST['delete_event'])) {
            $event_id = intval($_POST['event_id']);
            $stmt = $pdo->prepare("DELETE FROM events WHERE id = ? AND created_by = ?");
            $stmt->execute([$event_id, $user_id]);
            $success = "Событие успешно удалено!";
        }

        // Студент: запись на курс
        if ($role === 'student' && isset($_POST['enroll_course'])) {
            $course_id = intval($_POST['course_id']);
            if ($course_id > 0) {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE student_id = ? AND course_id = ?");
                $stmt->execute([$user_id, $course_id]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $course_id]);
                    $success = "Вы успешно записались на курс!";
                } else {
                    $error = "Вы уже записаны на этот курс!";
                }
            } else {
                $error = "Выберите курс!";
            }
        }
    } catch (PDOException $e) {
        error_log($e->getMessage(), 3, 'errors.log');
        $error = "Ошибка: " . $e->getMessage();
    }
}

// Получение данных в зависимости от роли
try {
    if ($role === 'admin') {
        $stmt = $pdo->query("SELECT u.id, u.login, u.name, u.status, r.role_name 
                             FROM users u 
                             JOIN roles r ON u.role_id = r.id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT id, role_name FROM roles");
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT id, title, description FROM courses");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT c.id, c.title 
                               FROM courses c 
                               JOIN study_groups g ON c.id = g.course_id 
                               WHERE g.teacher_id = ?");
        $stmt->execute([$user_id]);
        $teacher_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT g.id, g.name, c.title AS course_title 
                               FROM study_groups g 
                               JOIN courses c ON g.course_id = c.id 
                               WHERE g.teacher_id = ?");
        $stmt->execute([$user_id]);
        $teacher_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT s.id, s.content, c.title AS course_title, 'syllabus' AS type 
                               FROM syllabuses s 
                               JOIN courses c ON s.course_id = c.id 
                               WHERE s.created_by = ?");
        $stmt->execute([$user_id]);
        $teacher_syllabuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT m.id, m.title, m.file_path, c.title AS course_title, 'material' AS type 
                               FROM materials m 
                               JOIN courses c ON m.course_id = c.id 
                               WHERE m.uploaded_by = ?");
        $stmt->execute([$user_id]);
        $teacher_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $teacher_syllabuses_and_materials = array_merge($teacher_syllabuses, $teacher_materials);

        $stmt = $pdo->prepare("SELECT s.id, s.content, c.title AS course_title, u.name AS creator_name 
                               FROM syllabuses s 
                               JOIN courses c ON s.course_id = c.id 
                               JOIN users u ON s.created_by = u.id 
                               JOIN study_groups g ON c.id = g.course_id 
                               WHERE s.created_by != ? AND g.teacher_id = ?");
        $stmt->execute([$user_id, $user_id]);
        $other_syllabuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT g.id, g.grade, g.status, u.name AS student_name, c.title AS course_title 
                               FROM grades g 
                               JOIN users u ON g.student_id = u.id 
                               JOIN courses c ON g.course_id = c.id 
                               WHERE g.teacher_id = ?");
        $stmt->execute([$user_id]);
        $teacher_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT DISTINCT u.id, u.name 
                               FROM users u 
                               JOIN enrollments e ON u.id = e.student_id 
                               JOIN study_groups g ON e.course_id = g.course_id 
                               WHERE g.teacher_id = ? AND u.role_id = 3");
        $stmt->execute([$user_id]);
        $teacher_students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT e.id, e.title, e.start_time, e.end_time, c.title AS course_title 
                               FROM events e 
                               LEFT JOIN courses c ON e.course_id = c.id 
                               WHERE e.created_by = ?");
        $stmt->execute([$user_id]);
        $teacher_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($role === 'student') {
        $stmt = $pdo->prepare("SELECT c.id, c.title 
                               FROM enrollments e 
                               JOIN courses c ON e.course_id = c.id 
                               WHERE e.student_id = ?");
        $stmt->execute([$user_id]);
        $student_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT s.id, s.content, c.title AS course_title 
                               FROM syllabuses s 
                               JOIN courses c ON s.course_id = c.id 
                               JOIN enrollments e ON c.id = e.course_id 
                               WHERE e.student_id = ?");
        $stmt->execute([$user_id]);
        $student_syllabuses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT g.grade, g.status, c.title AS course_title 
                               FROM grades g 
                               JOIN courses c ON g.course_id = c.id 
                               WHERE g.student_id = ?");
        $stmt->execute([$user_id]);
        $student_grades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT c.id, c.title 
                               FROM courses c 
                               WHERE c.id NOT IN (SELECT course_id FROM enrollments WHERE student_id = ?)");
        $stmt->execute([$user_id]);
        $available_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("SELECT m.id, m.title, m.file_path, c.title AS course_title 
                               FROM materials m 
                               JOIN courses c ON m.course_id = c.id 
                               JOIN enrollments e ON c.id = e.course_id 
                               WHERE e.student_id = ?");
        $stmt->execute([$user_id]);
        $student_materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log($e->getMessage(), 3, 'errors.log');
    $error = "Ошибка загрузки данных.";
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Личный кабинет - Система управления силлабусами</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="/css/styles.css">
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
                        <a class="nav-link" href="calendar.php">Календарь</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="cabinet.php">Мой кабинет</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">Выйти</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h2 class="text-center mb-4">Личный кабинет</h2>
        <div class="card p-4">
            <h4>Добро пожаловать, <?php echo htmlspecialchars($user_name); ?>!</h4>
            <p>Ваша роль: <?php echo htmlspecialchars($role); ?></p>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($role === 'admin'): ?>
                <h5>Управление пользователями</h5>
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    Добавить пользователя
                </button>
                <?php if (empty($users)): ?>
                    <p>Пользователи отсутствуют.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Логин</th>
                                <th>Имя</th>
                                <th>Роль</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['login']); ?></td>
                                    <td><?php echo htmlspecialchars($user['name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                                    <td><?php echo $user['status'] === 'active' ? 'Активен' : 'Заблокирован'; ?></td>
                                    <td>
                                        <?php if ($user['id'] !== $user_id): ?>
                                            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                                Редактировать
                                            </button>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $user['status'] === 'active' ? 'blocked' : 'active'; ?>">
                                                <button type="submit" name="toggle_status" class="btn btn-warning btn-sm">
                                                    <?php echo $user['status'] === 'active' ? 'Заблокировать' : 'Разблокировать'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return ConfirmModal('Вы уверены, что хотите удалить пользователя?');">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Удалить</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editUserModalLabel<?php echo $user['id']; ?>">Редактировать пользователя</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST" id="editUserForm<?php echo $user['id']; ?>">
                                                    <input type="hidden" name="edit_user_id" value="<?php echo $user['id']; ?>">
                                                    <div class="mb-3">
                                                        <label for="edit_login<?php echo $user['id']; ?>" class="form-label">Логин</label>
                                                        <input type="text" class="form-control" id="edit_login<?php echo $user['id']; ?>" name="edit_login" value="<?php echo htmlspecialchars($user['login']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="edit_name<?php echo $user['id']; ?>" class="form-label">Имя</label>
                                                        <input type="text" class="form-control" id="edit_name<?php echo $user['id']; ?>" name="edit_name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="edit_role<?php echo $user['id']; ?>" class="form-label">Роль</label>
                                                        <select class="form-select" id="edit_role<?php echo $user['id']; ?>" name="edit_role_id" required>
                                                            <option value="">Выберите роль</option>
                                                            <?php foreach ($roles as $r): ?>
                                                                <option value="<?php echo $r['id']; ?>" <?php echo $r['role_name'] === $user['role_name'] ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($r['role_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="edit_status<?php echo $user['id']; ?>" class="form-label">Статус</label>
                                                        <select class="form-select" id="edit_status<?php echo $user['id']; ?>" name="edit_status" required>
                                                            <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Активен</option>
                                                            <option value="blocked" <?php echo $user['status'] === 'blocked' ? 'selected' : ''; ?>>Заблокирован</option>
                                                        </select>
                                                    </div>
                                                    <button type="submit" name="edit_user" class="btn btn-primary">Сохранить</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addUserModalLabel">Добавить пользователя</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" id="addUserForm">
                                    <div class="mb-3">
                                        <label for="login" class="form-label">Логин</label>
                                        <input type="text" class="form-control" id="login" name="login" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="password" class="form-label">Пароль</label>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Имя</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="role" class="form-label">Роль</label>
                                        <select class="form-select" id="role" name="role_id" required>
                                            <option value="">Выберите роль</option>
                                            <?php foreach ($roles as $r): ?>
                                                <option value="<?php echo $r['id']; ?>"><?php echo htmlspecialchars($r['role_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="add_user" class="btn btn-primary">Добавить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <h5>Управление курсами</h5>
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addCourseModal">
                    Добавить курс
                </button>
                <?php if (empty($courses)): ?>
                    <p>Курсы отсутствуют.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Описание</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courses as $course): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($course['title']); ?></td>
                                    <td><?php echo htmlspecialchars($course['description'] ?? ''); ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return ConfirmModal('Вы уверены, что хотите удалить курс?');">
                                            <input type="hidden" name="course_id" value="<?php echo $course['id']; ?>">
                                            <button type="submit" name="delete_course" class="btn btn-danger btn-sm">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="modal fade" id="addCourseModal" tabindex="-1" aria-labelledby="addCourseModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addCourseModalLabel">Добавить курс</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" id="addCourseForm">
                                    <div class="mb-3">
                                        <label for="course_title" class="form-label">Название курса</label>
                                        <input type="text" class="form-control" id="course_title" name="title" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="course_description" class="form-label">Описание</label>
                                        <textarea class="form-control" id="course_description" name="description" rows="4"></textarea>
                                    </div>
                                    <button type="submit" name="add_course" class="btn btn-primary">Добавить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($role === 'teacher'): ?>
                <h5>Мои курсы</h5>
                <?php if (empty($teacher_courses)): ?>
                    <p>Курсы отсутствуют.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($teacher_courses as $course): ?>
                            <li><?php echo htmlspecialchars($course['title']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <h5>Мои группы</h5>
                <?php if (empty($teacher_groups)): ?>
                    <p>Группы отсутствуют.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Название группы</th>
                                <th>Курс</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_groups as $group): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($group['name']); ?></td>
                                    <td><?php echo htmlspecialchars($group['course_title']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h5>Силлабусы</h5>
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addSyllabusModal">
                    Загрузить силлабус
                </button>
                <?php if (empty($teacher_syllabuses_and_materials)): ?>
                    <p>Силлабусы и материалы отсутствуют.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Курс</th>
                                <th>Название</th>
                                <th>Тип</th>
                                <th>Содержание/Файл</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_syllabuses_and_materials as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['course_title']); ?></td>
                                    <td><?php echo isset($item['title']) ? htmlspecialchars($item['title']) : 'Силлабус'; ?></td>
                                    <td><?php echo $item['type'] === 'syllabus' ? 'Силлабус' : 'Материал'; ?></td>
                                    <td>
                                        <?php if ($item['type'] === 'syllabus'): ?>
                                            <?php echo htmlspecialchars(substr($item['content'], 0, 50)) . '...'; ?>
                                        <?php else: ?>
                                            <a href="/Uploads/<?php echo htmlspecialchars($item['file_path']); ?>" class="btn btn-primary btn-sm" download>Скачать</a>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return ConfirmModal('Вы уверены, что хотите удалить этот элемент?');">
                                            <input type="hidden" name="<?php echo $item['type'] === 'syllabus' ? 'syllabus_id' : 'material_id'; ?>" value="<?php echo $item['id']; ?>">
                                            <button type="submit" name="delete_<?php echo $item['type']; ?>" class="btn btn-danger btn-sm">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="modal fade" id="addSyllabusModal" tabindex="-1" aria-labelledby="addSyllabusModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addSyllabusModalLabel">Загрузить силлабус</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" enctype="multipart/form-data" id="addSyllabusForm">
                                    <div class="mb-3">
                                        <label for="course_id_syllabus" class="form-label">Курс</label>
                                        <select class="form-select" id="course_id_syllabus" name="course_id" required>
                                            <option value="">Выберите курс</option>
                                            <?php foreach ($teacher_courses as $course): ?>
                                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="syllabus_title" class="form-label">Название</label>
                                        <input type="text" class="form-control" id="syllabus_title" name="title" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="content" class="form-label">Содержание (для текстового силлабуса)</label>
                                        <textarea class="form-control" id="content" name="content" rows="5"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label for="material_file" class="form-label">Файл (PDF, PPT, PPTX, до 10 МБ)</label>
                                        <input type="file" class="form-control" id="material_file" name="material_file" accept=".pdf,.ppt,.pptx">
                                    </div>
                                    <button type="submit" name="add_syllabus" class="btn btn-primary">Загрузить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <h5>Силлабусы других преподавателей</h5>
                <?php if (empty($other_syllabuses)): ?>
                    <p>Силлабусы других преподавателей отсутствуют.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Курс</th>
                                <th>Содержание</th>
                                <th>Автор</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($other_syllabuses as $syllabus): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($syllabus['course_title']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($syllabus['content'], 0, 50)) . '...'; ?></td>
                                    <td><?php echo htmlspecialchars($syllabus['creator_name']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h5>Мои события</h5>
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addEventModal">
                    Добавить событие
                </button>
                <?php if (empty($teacher_events)): ?>
                    <p>События отсутствуют.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Курс</th>
                                <th>Начало</th>
                                <th>Конец</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_events as $event): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($event['title']); ?></td>
                                    <td><?php echo htmlspecialchars($event['course_title'] ?? 'Без курса'); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($event['start_time'])); ?></td>
                                    <td><?php echo $event['end_time'] ? date('d.m.Y H:i', strtotime($event['end_time'])) : '-'; ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;" onsubmit="return ConfirmModal('Вы уверены, что хотите удалить событие?');">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" name="delete_event" class="btn btn-danger btn-sm">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addEventModalLabel">Добавить событие</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" id="addEventForm">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Название события</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="course_id_event" class="form-label">Курс</label>
                                        <select class="form-select" id="course_id_event" name="course_id" required>
                                            <option value="">Выберите курс</option>
                                            <?php foreach ($teacher_courses as $course): ?>
                                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="start_time" class="form-label">Дата и время начала</label>
                                        <input type="datetime-local" class="form-control" id="start_time" name="start_time" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="end_time" class="form-label">Дата и время окончания (необязательно)</label>
                                        <input type="datetime-local" class="form-control" id="end_time" name="end_time">
                                    </div>
                                    <button type="submit" name="add_event" class="btn btn-primary">Добавить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <h5>Успеваемость студентов</h5>
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                    Выставить оценку
                </button>
                <?php if (empty($teacher_grades)): ?>
                    <p>Оценки отсутствуют.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Студент</th>
                                <th>Курс</th>
                                <th>Оценка</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($teacher_grades as $grade): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($grade['course_title']); ?></td>
                                    <td><?php echo isset($grade['grade']) ? htmlspecialchars($grade['grade']) : 'N/A'; ?></td>
                                    <td><?php echo $grade['status'] === 'passed' ? 'Сдано' : ($grade['status'] === 'failed' ? 'Не сдано' : 'Не оценено'); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editGradeModal<?php echo $grade['id']; ?>">
                                            Редактировать
                                        </button>
                                        <form method="POST" style="display:inline;" onsubmit="return ConfirmModal('Вы уверены, что хотите удалить оценку?');">
                                            <input type="hidden" name="grade_id" value="<?php echo $grade['id']; ?>">
                                            <button type="submit" name="delete_grade" class="btn btn-danger btn-sm">Удалить</button>
                                        </form>
                                    </td>
                                </tr>
                                <div class="modal fade" id="editGradeModal<?php echo $grade['id']; ?>" tabindex="-1" aria-labelledby="editGradeModalLabel<?php echo $grade['id']; ?>" aria-hidden="true">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="editGradeModalLabel<?php echo $grade['id']; ?>">Редактировать оценку</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                            </div>
                                            <div class="modal-body">
                                                <form method="POST" id="editGradeForm<?php echo $grade['id']; ?>">
                                                    <input type="hidden" name="grade_id" value="<?php echo $grade['id']; ?>">
                                                    <div class="mb-3">
                                                        <label for="grade<?php echo $grade['id']; ?>" class="form-label">Оценка (0-100)</label>
                                                        <input type="number" class="form-control" id="grade<?php echo $grade['id']; ?>" name="grade" value="<?php echo $grade['grade'] ?? '0'; ?>" min="0" max="100" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label for="status_grade<?php echo $grade['id']; ?>" class="form-label">Статус</label>
                                                        <select class="form-select" id="status_grade<?php echo $grade['id']; ?>" name="status" required>
                                                            <option value="passed" <?php echo $grade['status'] === 'passed' ? 'selected' : ''; ?>>Сдано</option>
                                                            <option value="failed" <?php echo $grade['status'] === 'failed' ? 'selected' : ''; ?>>Не сдано</option>
                                                            <option value="not_graded" <?php echo $grade['status'] === 'not_graded' ? 'selected' : ''; ?>>Не оценено</option>
                                                        </select>
                                                    </div>
                                                    <button type="submit" name="edit_grade" class="btn btn-primary">Сохранить</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="modal fade" id="addGradeModal" tabindex="-1" aria-labelledby="addGradeModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addGradeModalLabel">Выставить оценку</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" id="addGradeForm">
                                    <div class="mb-3">
                                        <label for="student_id_grade" class="form-label">Студент</label>
                                        <select class="form-select" id="student_id_grade" name="student_id" required>
                                            <option value="">Выберите студента</option>
                                            <?php foreach ($teacher_students as $student): ?>
                                                <option value="<?php echo $student['id']; ?>"><?php echo htmlspecialchars($student['name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="course_id_grade" class="form-label">Курс</label>
                                        <select class="form-select" id="course_id_grade" name="course_id" required>
                                            <option value="">Выберите курс</option>
                                            <?php foreach ($teacher_courses as $course): ?>
                                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label for="grade" class="form-label">Оценка (0-100)</label>
                                        <input type="number" class="form-control" id="grade" name="grade" min="0" max="100" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="status_grade" class="form-label">Статус</label>
                                        <select class="form-select" id="status_grade" name="status" required>
                                            <option value="">Выберите статус</option>
                                            <option value="passed">Сдано</option>
                                            <option value="failed">Не сдано</option>
                                            <option value="not_graded">Не оценено</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="add_grade" class="btn btn-primary">Сохранить</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            <?php elseif ($role === 'student'): ?>
                <h5>Мои курсы</h5>
                <button type="button" class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#enrollCourseModal">
                    Записаться на курс
                </button>
                <?php if (empty($student_courses)): ?>
                    <p>Курсы отсутствуют.</p>
                <?php else: ?>
                    <ul>
                        <?php foreach ($student_courses as $course): ?>
                            <li><?php echo htmlspecialchars($course['title']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="modal fade" id="enrollCourseModal" tabindex="-1" aria-labelledby="enrollCourseModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="enrollCourseModalLabel">Записаться на курс</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <form method="POST" id="enrollCourseForm">
                                    <div class="mb-3">
                                        <label for="course_id_enroll" class="form-label">Курс</label>
                                        <select class="form-select" id="course_id_enroll" name="course_id" required>
                                            <option value="">Выберите курс</option>
                                            <?php foreach ($available_courses as $course): ?>
                                                <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['title']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="enroll_course" class="btn btn-primary">Записаться</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <h5>Мои силлабусы</h5>
                <?php if (empty($student_syllabuses)): ?>
                    <p>Силлабусы отсутствуют.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Курс</th>
                                <th>Содержание</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_syllabuses as $syllabus): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($syllabus['course_title']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($syllabus['content'], 0, 50)) . '...'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h5>Мои оценки</h5>
                <?php if (empty($student_grades)): ?>
                    <p>Оценки отсутствуют.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Курс</th>
                                <th>Оценка</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_grades as $grade): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($grade['course_title']); ?></td>
                                    <td><?php echo isset($grade['grade']) ? htmlspecialchars($grade['grade']) : 'N/A'; ?></td>
                                    <td><?php echo $grade['status'] === 'passed' ? 'Сдано' : ($grade['status'] === 'failed' ? 'Не сдано' : 'Не оценено'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <h5>Материалы</h5>
                <?php if (empty($student_materials)): ?>
                    <p>Материалы отсутствуют.</p>
                <?php else: ?>
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Название</th>
                                <th>Курс</th>
                                <th>Файл</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($student_materials as $material): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($material['title']); ?></td>
                                    <td><?php echo htmlspecialchars($material['course_title']); ?></td>
                                    <td>
                                        <a href="/Uploads/<?php echo htmlspecialchars($material['file_path']); ?>" class="btn btn-primary btn-sm" download>Скачать</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="/js/scripts.js"></script>
    <script>
        // Клиентская валидация форм
        document.getElementById('addUserForm')?.addEventListener('submit', function(e) {
            const login = document.getElementById('login').value;
            const password = document.getElementById('password').value;
            const name = document.getElementById('name').value;
            const role = document.getElementById('role').value;
            if (login.length < 3) {
                e.preventDefault();
                alert('Логин должен быть длиннее 3 символов');
            }
            if (password.length < 6) {
                e.preventDefault();
                alert('Пароль должен быть длиннее 6 символов');
            }
            if (!name) {
                e.preventDefault();
                alert('Имя обязательно');
            }
            if (!role) {
                e.preventDefault();
                alert('Выберите роль');
            }
        });

        document.querySelectorAll('[id^="editUserForm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const login = form.querySelector('[name="edit_login"]').value;
                const name = form.querySelector('[name="edit_name"]').value;
                const role = form.querySelector('[name="edit_role_id"]').value;
                if (login.length < 3 || !name || !role) {
                    e.preventDefault();
                    alert('Заполните все поля корректно');
                }
            });
        });

        document.getElementById('addCourseForm')?.addEventListener('submit', function(e) {
            const title = document.getElementById('course_title').value;
            if (title.length < 3) {
                e.preventDefault();
                alert('Название курса должно быть длиннее 3 символов');
            }
        });

        document.getElementById('addSyllabusForm')?.addEventListener('submit', function(e) {
            const course = document.getElementById('course_id_syllabus').value;
            const title = document.getElementById('syllabus_title').value;
            const content = document.getElementById('content').value;
            const file = document.getElementById('material_file').files[0];
            if (!course || !title) {
                e.preventDefault();
                alert('Выберите курс и укажите название');
            }
            if (!content && !file) {
                e.preventDefault();
                alert('Укажите содержимое или загрузите файл');
            }
            if (file && file.size > 10 * 1024 * 1024) {
                e.preventDefault();
                alert('Файл превышает 10 МБ');
            }
        });

        document.getElementById('addEventForm')?.addEventListener('submit', function(e) {
            const title = document.getElementById('title').value;
            const course = document.getElementById('course_id_event').value;
            const startTime = document.getElementById('start_time').value;
            if (!title || !course || !startTime) {
                e.preventDefault();
                alert('Заполните название, курс и дату начала');
            }
        });

        document.getElementById('addGradeForm')?.addEventListener('submit', function(e) {
            const student = document.getElementById('student_id_grade').value;
            const course = document.getElementById('course_id_grade').value;
            const grade = document.getElementById('grade').value;
            const status = document.getElementById('status_grade').value;
            if (!student || !course || !status) {
                e.preventDefault();
                alert('Выберите студента, курс и статус');
            }
            if (grade < 0 || grade > 100) {
                e.preventDefault();
                alert('Оценка должна быть от 0 до 100');
            }
        });

        document.querySelectorAll('[id^="editGradeForm"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const grade = form.querySelector('[name="grade"]').value;
                const status = form.querySelector('[name="status"]').value;
                if (grade < 0 || grade > 100) {
                    e.preventDefault();
                    alert('Оценка должна быть от 0 до 100');
                }
                if (!status) {
                    e.preventDefault();
                    alert('Выберите статус');
                }
            });
        });

        document.getElementById('enrollCourseForm')?.addEventListener('submit', function(e) {
            const course = document.getElementById('course_id_enroll').value;
            if (!course) {
                e.preventDefault();
                alert('Выберите курс');
            }
        });
    </script>
</body>
</html>