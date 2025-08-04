<?php
/**
 * Debug Image Processing Script
 * 
 * This script will help identify why image processing is hanging
 */

// Pідключаємо WordPress
require_once('wp-load.php');

// Security check - only allow admin users
if (!current_user_can('manage_options')) {
    wp_die('Access denied. Admin privileges required.');
}

echo "<h1>Діагностика обробки зображень</h1>";

// 1. Перевіряємо PHP налаштування
echo "<h2>1. PHP Налаштування</h2>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . "</p>";
echo "<p>Upload Max Filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>Post Max Size: " . ini_get('post_max_size') . "</p>";

// 2. Перевіряємо доступні бібліотеки
echo "<h2>2. Бібліотеки обробки зображень</h2>";
echo "<p>GD Extension: " . (extension_loaded('gd') ? '✅ Завантажено' : '❌ Не завантажено') . "</p>";
if (extension_loaded('gd')) {
    echo "<p>GD Version: " . gd_info()['GD Version'] . "</p>";
    echo "<p>Supported Formats: " . implode(', ', array_keys(array_filter(gd_info(), function($v) { return $v === true; }))) . "</p>";
}

echo "<p>ImageMagick Extension: " . (extension_loaded('imagick') ? '✅ Завантажено' : '❌ Не завантажено') . "</p>";

// 3. Перевіряємо права доступу до папки uploads
echo "<h2>3. Права доступу</h2>";
$upload_dir = wp_upload_dir();
echo "<p>Upload Directory: " . $upload_dir['basedir'] . "</p>";
echo "<p>Directory exists: " . (is_dir($upload_dir['basedir']) ? '✅' : '❌') . "</p>";
echo "<p>Directory writable: " . (is_writable($upload_dir['basedir']) ? '✅' : '❌') . "</p>";

// 4. Тестуємо просте створення файлу
echo "<h2>4. Тест створення файлу</h2>";
$test_file = $upload_dir['basedir'] . '/test-write-' . time() . '.txt';
$write_result = file_put_contents($test_file, 'test');
if ($write_result !== false) {
    echo "<p>✅ Можна створювати файли в uploads</p>";
    unlink($test_file); // Видаляємо тестовий файл
} else {
    echo "<p>❌ Не можна створювати файли в uploads</p>";
}

// 5. Знаходимо проблемне зображення
echo "<h2>5. Аналіз проблемного зображення</h2>";
$problem_attachment = get_post(8979); // ID з попереднього скрипта
if ($problem_attachment) {
    echo "<p>Проблемне зображення: {$problem_attachment->post_title}</p>";
    
    $file_path = get_attached_file($problem_attachment->ID);
    echo "<p>Шлях до файлу: {$file_path}</p>";
    echo "<p>Файл існує: " . (file_exists($file_path) ? '✅' : '❌') . "</p>";
    
    if (file_exists($file_path)) {
        $file_size = filesize($file_path);
        echo "<p>Розмір файлу: " . round($file_size / 1024 / 1024, 2) . " MB</p>";
        
        // Перевіряємо тип файлу
        $image_info = getimagesize($file_path);
        if ($image_info) {
            echo "<p>Тип зображення: " . $image_info['mime'] . "</p>";
            echo "<p>Розміри: {$image_info[0]}x{$image_info[1]}</p>";
        } else {
            echo "<p>❌ Не вдається отримати інформацію про зображення</p>";
        }
        
        // Перевіряємо права доступу до файлу
        echo "<p>Файл читається: " . (is_readable($file_path) ? '✅' : '❌') . "</p>";
    }
}

// 6. Тестуємо просту обробку зображення
echo "<h2>6. Тест простої обробки зображення</h2>";
if (extension_loaded('gd') && file_exists($file_path)) {
    echo "<p>Спроба створити копію зображення...</p>";
    
    try {
        $source_image = imagecreatefromjpeg($file_path);
        if ($source_image) {
            echo "<p>✅ Вдалося завантажити зображення в пам'ять</p>";
            
            // Створюємо маленьку копію для тесту
            $test_width = 100;
            $test_height = 100;
            $test_image = imagecreatetruecolor($test_width, $test_height);
            
            if ($test_image) {
                echo "<p>✅ Вдалося створити нове зображення</p>";
                
                // Копіюємо частину зображення
                $copy_result = imagecopyresampled($test_image, $source_image, 0, 0, 0, 0, $test_width, $test_height, imagesx($source_image), imagesy($source_image));
                
                if ($copy_result) {
                    echo "<p>✅ Вдалося обробити зображення</p>";
                    
                    // Зберігаємо тестове зображення
                    $test_output = $upload_dir['basedir'] . '/test-resize-' . time() . '.jpg';
                    $save_result = imagejpeg($test_image, $test_output, 85);
                    
                    if ($save_result) {
                        echo "<p>✅ Вдалося зберегти оброблене зображення</p>";
                        unlink($test_output); // Видаляємо тестовий файл
                    } else {
                        echo "<p>❌ Не вдалося зберегти оброблене зображення</p>";
                    }
                } else {
                    echo "<p>❌ Не вдалося обробити зображення</p>";
                }
                
                imagedestroy($test_image);
            } else {
                echo "<p>❌ Не вдалося створити нове зображення</p>";
            }
            
            imagedestroy($source_image);
        } else {
            echo "<p>❌ Не вдалося завантажити зображення в пам'ять</p>";
        }
    } catch (Exception $e) {
        echo "<p>❌ Помилка при обробці: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ GD не завантажено або файл не існує</p>";
}

// 7. Перевіряємо WordPress функції
echo "<h2>7. Тест WordPress функцій</h2>";
echo "<p>wp_generate_attachment_metadata доступна: " . (function_exists('wp_generate_attachment_metadata') ? '✅' : '❌') . "</p>";
echo "<p>wp_get_attachment_metadata доступна: " . (function_exists('wp_get_attachment_metadata') ? '✅' : '❌') . "</p>";

// 8. Рекомендації
echo "<h2>8. Рекомендації</h2>";
echo "<ul>";
if (!extension_loaded('gd')) {
    echo "<li>❌ Встановіть GD extension для PHP</li>";
}
if (!is_writable($upload_dir['basedir'])) {
    echo "<li>❌ Змініть права доступу до папки uploads (chmod 755)</li>";
}
if (ini_get('memory_limit') < '256M') {
    echo "<li>⚠️ Збільшіть memory_limit до 256M або більше</li>";
}
echo "</ul>";

echo "<hr>";
echo "<p><strong>Діагностика завершена: " . date('Y-m-d H:i:s') . "</strong></p>";
?> 