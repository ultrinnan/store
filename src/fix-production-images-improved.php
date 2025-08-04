<?php
/**
 * Improved Production Image Fix Script
 *
 * INSTRUCTIONS:
 * 1. Upload this file to your production site root
 * 2. Run it once via browser: https://yoursite.com/fix-production-images-improved.php
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

// Збільшуємо ліміти для обробки великих файлів
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);
set_time_limit(300);

echo "<h1>Покращене виправлення зображень на продакшн сайті</h1>";
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
$skipped = 0;

// Обмежуємо кількість зображень для тестування
$max_images = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
$attachments = array_slice($attachments, 0, $max_images);

echo "<p><strong>Обробляємо перші {$max_images} зображень для тестування</strong></p>";
echo "<p><a href='?limit=50'>Обробити 50 зображень</a> | <a href='?limit=100'>Обробити 100 зображень</a> | <a href='?limit=1000'>Обробити 1000 зображень</a></p>";

foreach ($attachments as $attachment) {
    echo "<h3>Обробляємо: {$attachment->post_title} (ID: {$attachment->ID})</h3>";
    
    $file_path = get_attached_file($attachment->ID);
    
    if (!$file_path) {
        echo "<p style='color: red;'>✗ Немає шляху до файлу</p>";
        $errors++;
        continue;
    }
    
    if (!file_exists($file_path)) {
        echo "<p style='color: red;'>✗ Файл не знайдено: {$file_path}</p>";
        $errors++;
        continue;
    }
    
    // Перевіряємо розмір файлу
    $file_size = filesize($file_path);
    $file_size_mb = round($file_size / 1024 / 1024, 2);
    echo "<p>Розмір файлу: {$file_size_mb} MB</p>";
    
    if ($file_size > 50 * 1024 * 1024) { // Більше 50MB
        echo "<p style='color: orange;'>⚠️ Файл занадто великий, пропускаємо</p>";
        $skipped++;
        continue;
    }
    
    try {
        // Отримуємо метадані
        $metadata = wp_get_attachment_metadata($attachment->ID);
        
        if (!$metadata) {
            echo "<p style='color: orange;'>⚠️ Немає метаданих, створюємо нові</p>";
            $metadata = array();
        }
        
        // Регенеруємо всі розміри
        echo "<p>Генеруємо розміри...</p>";
        
        $new_metadata = wp_generate_attachment_metadata($attachment->ID, $file_path);
        
        if ($new_metadata && is_array($new_metadata)) {
            wp_update_attachment_metadata($attachment->ID, $new_metadata);
            $processed++;
            $sizes_count = isset($new_metadata['sizes']) ? count($new_metadata['sizes']) : 0;
            $created += $sizes_count;
            echo "<p style='color: green;'>✅ Успішно оброблено. Створено {$sizes_count} розмірів</p>";
        } else {
            $errors++;
            echo "<p style='color: red;'>✗ Помилка при генерації метаданих</p>";
        }
        
    } catch (Exception $e) {
        $errors++;
        echo "<p style='color: red;'>✗ Помилка: " . $e->getMessage() . "</p>";
    }
    
    // Показуємо прогрес кожні 5 зображень
    if ($processed % 5 == 0) {
        echo "<p><strong>Прогрес: {$processed} з " . count($attachments) . "</strong></p>";
        echo "<p>Пам'ять використана: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB</p>";
        
        // Очищаємо буфер для відображення прогресу
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }
    
    // Невелика пауза між обробкою
    usleep(100000); // 0.1 секунди
}

echo "<h2>Результат:</h2>";
echo "<p>Оброблено зображень: <strong>{$processed}</strong></p>";
echo "<p>Створено розмірів: <strong>{$created}</strong></p>";
echo "<p>Пропущено: <strong>{$skipped}</strong></p>";
echo "<p>Помилок: <strong>{$errors}</strong></p>";

if ($errors === 0 && $processed > 0) {
    echo "<p style='color: green; font-weight: bold;'>✅ Всі зображення успішно оброблено!</p>";
} elseif ($errors > 0) {
    echo "<p style='color: orange; font-weight: bold;'>⚠️ Є помилки, перевірте вище</p>";
}

echo "<hr>";
echo "<p><strong>ВАЖЛИВО:</strong> Видаліть цей файл після завершення!</p>";
echo "<p><small>Скрипт завершено: " . date('Y-m-d H:i:s') . "</small></p>";
?> 