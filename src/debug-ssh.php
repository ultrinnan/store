<?php
/**
 * SSH Debug Script
 * 
 * Run this script via SSH: php debug-ssh.php
 */

// Pідключаємо WordPress
require_once('wp-load.php');

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line: php debug-ssh.php\n");
}

echo "=== SSH Debug Script ===\n";
echo "Starting at: " . date('Y-m-d H:i:s') . "\n\n";

// 1. PHP Settings
echo "1. PHP SETTINGS:\n";
echo "   Memory Limit: " . ini_get('memory_limit') . "\n";
echo "   Max Execution Time: " . ini_get('max_execution_time') . "\n";
echo "   Upload Max Filesize: " . ini_get('upload_max_filesize') . "\n";
echo "   Post Max Size: " . ini_get('post_max_size') . "\n\n";

// 2. Image Libraries
echo "2. IMAGE LIBRARIES:\n";
echo "   GD Extension: " . (extension_loaded('gd') ? 'YES' : 'NO') . "\n";
if (extension_loaded('gd')) {
    $gd_info = gd_info();
    echo "   GD Version: " . $gd_info['GD Version'] . "\n";
    echo "   JPEG Support: " . (isset($gd_info['JPEG Support']) && $gd_info['JPEG Support'] ? 'YES' : 'NO') . "\n";
    echo "   PNG Support: " . (isset($gd_info['PNG Support']) && $gd_info['PNG Support'] ? 'YES' : 'NO') . "\n";
}
echo "   ImageMagick Extension: " . (extension_loaded('imagick') ? 'YES' : 'NO') . "\n\n";

// 3. Upload Directory
echo "3. UPLOAD DIRECTORY:\n";
$upload_dir = wp_upload_dir();
echo "   Upload Directory: " . $upload_dir['basedir'] . "\n";
echo "   Directory exists: " . (is_dir($upload_dir['basedir']) ? 'YES' : 'NO') . "\n";
echo "   Directory writable: " . (is_writable($upload_dir['basedir']) ? 'YES' : 'NO') . "\n\n";

// 4. Test file creation
echo "4. FILE CREATION TEST:\n";
$test_file = $upload_dir['basedir'] . '/test-write-' . time() . '.txt';
$write_result = file_put_contents($test_file, 'test');
if ($write_result !== false) {
    echo "   File creation: SUCCESS\n";
    unlink($test_file);
} else {
    echo "   File creation: FAILED\n";
}
echo "\n";

// 5. WordPress Functions
echo "5. WORDPRESS FUNCTIONS:\n";
echo "   wp_generate_attachment_metadata: " . (function_exists('wp_generate_attachment_metadata') ? 'AVAILABLE' : 'NOT AVAILABLE') . "\n";
echo "   wp_get_attachment_metadata: " . (function_exists('wp_get_attachment_metadata') ? 'AVAILABLE' : 'NOT AVAILABLE') . "\n";
echo "   get_attached_file: " . (function_exists('get_attached_file') ? 'AVAILABLE' : 'NOT AVAILABLE') . "\n\n";

// 6. Test specific image
echo "6. TEST SPECIFIC IMAGE (ID: 8979):\n";
$problem_attachment = get_post(8979);
if ($problem_attachment) {
    echo "   Image title: " . $problem_attachment->post_title . "\n";
    
    $file_path = get_attached_file($problem_attachment->ID);
    echo "   File path: " . $file_path . "\n";
    echo "   File exists: " . (file_exists($file_path) ? 'YES' : 'NO') . "\n";
    
    if (file_exists($file_path)) {
        $file_size = filesize($file_path);
        echo "   File size: " . round($file_size / 1024 / 1024, 2) . " MB\n";
        echo "   File readable: " . (is_readable($file_path) ? 'YES' : 'NO') . "\n";
        
        $image_info = getimagesize($file_path);
        if ($image_info) {
            echo "   Image type: " . $image_info['mime'] . "\n";
            echo "   Image dimensions: {$image_info[0]}x{$image_info[1]}\n";
        } else {
            echo "   Image info: FAILED TO READ\n";
        }
    }
} else {
    echo "   Image not found\n";
}
echo "\n";

// 7. Simple image processing test
echo "7. SIMPLE IMAGE PROCESSING TEST:\n";
if (extension_loaded('gd') && isset($file_path) && file_exists($file_path)) {
    echo "   Testing GD image processing...\n";
    
    try {
        $source_image = imagecreatefromjpeg($file_path);
        if ($source_image) {
            echo "   Load image: SUCCESS\n";
            
            $test_image = imagecreatetruecolor(100, 100);
            if ($test_image) {
                echo "   Create new image: SUCCESS\n";
                
                $copy_result = imagecopyresampled($test_image, $source_image, 0, 0, 0, 0, 100, 100, imagesx($source_image), imagesy($source_image));
                if ($copy_result) {
                    echo "   Resize image: SUCCESS\n";
                    
                    $test_output = $upload_dir['basedir'] . '/test-resize-' . time() . '.jpg';
                    $save_result = imagejpeg($test_image, $test_output, 85);
                    if ($save_result) {
                        echo "   Save image: SUCCESS\n";
                        unlink($test_output);
                    } else {
                        echo "   Save image: FAILED\n";
                    }
                } else {
                    echo "   Resize image: FAILED\n";
                }
                
                imagedestroy($test_image);
            } else {
                echo "   Create new image: FAILED\n";
            }
            
            imagedestroy($source_image);
        } else {
            echo "   Load image: FAILED\n";
        }
    } catch (Exception $e) {
        echo "   Error: " . $e->getMessage() . "\n";
    }
} else {
    echo "   GD not available or file not found\n";
}
echo "\n";

// 8. Recommendations
echo "8. RECOMMENDATIONS:\n";
if (!extension_loaded('gd')) {
    echo "   ❌ Install GD extension for PHP\n";
}
if (!is_writable($upload_dir['basedir'])) {
    echo "   ❌ Fix upload directory permissions (chmod 755)\n";
}
if (ini_get('memory_limit') < '256M') {
    echo "   ⚠️ Increase memory_limit to 256M or more\n";
}
if (ini_get('max_execution_time') < 300) {
    echo "   ⚠️ Increase max_execution_time to 300 or more\n";
}

echo "\n=== DEBUG COMPLETED ===\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";
?> 