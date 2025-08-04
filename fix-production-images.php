<?php
/**
 * Production Image Fix Script
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to your production site root
 * 2. Run it once via browser: https://yoursite.com/fix-production-images.php
 * 3. Delete this file after running
 * 
 * WARNING: This script will create many image files and may take time to run
 */

// Pідключаємо WordPress
require_once('wp-load.php');

// Security check - only allow admin users
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>Виправлення зображень на продакшн сайті</h1>";
echo "<p><strong>Увага:</strong> Цей скрипт створить відсутні розміри зображень. Може зайняти кілька хвилин.</p>";

// Отримуємо всі зображення
$attachments = get_posts(array(
    'post_type' => 'attachment',
    'post_mime_type' => 'image',
    'numberposts' => -1,
    'post_status' => 'any'
));

echo "<h2>Знайдено зображень: " . count($attachments) . "</h2>";

$processed = 0;
$errors = 0;
$created = 0;

foreach ($attachments as $attachment) {
    echo "<h3>Обробляємо: {$attachment->post_title}</h3>";
    
    $file_path = get_attached_file($attachment->ID);
    
    if (file_exists($file_path)) {
        // Отримуємо метадані
        $metadata = wp_get_attachment_metadata($attachment->ID);
        
        if ($metadata) {
            // Регенеруємо всі розміри
            $new_metadata = wp_generate_attachment_metadata($attachment->ID, $file_path);
            
            if ($new_metadata) {
                wp_update_attachment_metadata($attachment->ID, $new_metadata);
                $processed++;
                $created += count($new_metadata['sizes']);
                echo "<p style='color: green;'>✅ Успішно оброблено</p>";
            } else {
                $errors++;
                echo "<p style='color: red;'>✗ Помилка при обробці</p>";
            }
        } else {
            $errors++;
            echo "<p style='color: red;'>✗ Немає метаданих</p>";
        }
    } else {
        $errors++;
        echo "<p style='color: red;'>✗ Файл не знайдено: {$file_path}</p>";
    }
    
    // Показуємо прогрес кожні 10 зображень
    if ($processed % 10 == 0) {
        echo "<p><strong>Прогрес: {$processed} з " . count($attachments) . "</strong></p>";
        // Очищаємо буфер для відображення прогресу
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
}

echo "<h2>Результат:</h2>";
echo "<p>Оброблено зображень: <strong>{$processed}</strong></p>";
echo "<p>Створено розмірів: <strong>{$created}</strong></p>";
echo "<p>Помилок: <strong>{$errors}</strong></p>";

if ($errors === 0) {
    echo "<p style='color: green; font-weight: bold;'>✅ Всі зображення успішно оброблено!</p>";
} else {
    echo "<p style='color: orange; font-weight: bold;'>⚠️ Є помилки, перевірте вище</p>";
}

echo "<hr>";
echo "<p><strong>ВАЖЛИВО:</strong> Видаліть цей файл після завершення!</p>";
echo "<p><small>Скрипт завершено: " . date('Y-m-d H:i:s') . "</small></p>";
?> 