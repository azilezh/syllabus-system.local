<?php
$upload_dir = 'Uploads/';
if (is_dir($upload_dir)) {
    echo "Папка Uploads существует.<br>";
    if (is_writable($upload_dir)) {
        echo "Папка Uploads доступна для записи.";
    } else {
        echo "Папка Uploads НЕ доступна для записи.";
    }
} else {
    echo "Папка Uploads НЕ существует.";
}
?>