<?php
session_start();

// Завершение сессии
session_destroy();

// Перенаправление на страницу входа
header('Location: login.php');
exit;
?>