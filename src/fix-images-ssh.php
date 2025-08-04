<?php
/**
 * SSH Image Fix Script
 * 
 * Run this script via SSH: php fix-images-ssh.php
 * 
 * This script will regenerate missing image sizes for all attachments
 */

// Pідключаємо WordPress
require_once('wp-load.php');

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from command line: php fix-images-ssh.php\n");
}

echo "=== SSH Image Fix Script ===\n";
echo "Starting at: " . date('Y-m-d H:i:s') . "\n\n";

// Збільшуємо ліміти
ini_set('memory_limit', '1G');
ini_set('max_execution_time', 0); // No time limit
set_time_limit(0);

// Get all image attachments
$attachments = get_posts(array(
    'post_type' => 'attachment',
    'post_mime_type' => 'image',
    'numberposts' => -1,
    'post_status' => 'any'
));

$total_images = count($attachments);
echo "Found {$total_images} images to process\n\n";

$processed = 0;
$errors = 0;
$created = 0;
$skipped = 0;

// Process images
foreach ($attachments as $attachment) {
    $processed++;
    
    echo "[{$processed}/{$total_images}] Processing: {$attachment->post_title} (ID: {$attachment->ID})";
    
    $file_path = get_attached_file($attachment->ID);
    
    if (!$file_path) {
        echo " - ERROR: No file path\n";
        $errors++;
        continue;
    }
    
    if (!file_exists($file_path)) {
        echo " - ERROR: File not found\n";
        $errors++;
        continue;
    }
    
    // Check file size
    $file_size = filesize($file_path);
    $file_size_mb = round($file_size / 1024 / 1024, 2);
    echo " ({$file_size_mb}MB)";
    
    if ($file_size > 50 * 1024 * 1024) { // More than 50MB
        echo " - SKIPPED: File too large\n";
        $skipped++;
        continue;
    }
    
    try {
        // Get current metadata
        $metadata = wp_get_attachment_metadata($attachment->ID);
        
        if (!$metadata) {
            echo " - No metadata, creating new...";
        } else {
            $current_sizes = isset($metadata['sizes']) ? count($metadata['sizes']) : 0;
            echo " - Current sizes: {$current_sizes}";
        }
        
        // Regenerate all sizes
        echo " - Generating...";
        
        $new_metadata = wp_generate_attachment_metadata($attachment->ID, $file_path);
        
        if ($new_metadata && is_array($new_metadata)) {
            wp_update_attachment_metadata($attachment->ID, $new_metadata);
            
            $new_sizes = isset($new_metadata['sizes']) ? count($new_metadata['sizes']) : 0;
            $created += $new_sizes;
            
            echo " - SUCCESS: {$new_sizes} sizes created\n";
        } else {
            $errors++;
            echo " - ERROR: Failed to generate metadata\n";
        }
        
    } catch (Exception $e) {
        $errors++;
        echo " - ERROR: " . $e->getMessage() . "\n";
    }
    
    // Show progress every 10 images
    if ($processed % 10 == 0) {
        echo "\n--- Progress: {$processed}/{$total_images} ---\n";
        echo "Memory usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n\n";
    }
    
    // Small pause to prevent server overload
    usleep(50000); // 0.05 seconds
}

// Final results
echo "\n=== FINAL RESULTS ===\n";
echo "Total images: {$total_images}\n";
echo "Processed: {$processed}\n";
echo "Created sizes: {$created}\n";
echo "Skipped: {$skipped}\n";
echo "Errors: {$errors}\n";
echo "Completed at: " . date('Y-m-d H:i:s') . "\n";

if ($errors === 0) {
    echo "✅ SUCCESS: All images processed successfully!\n";
} else {
    echo "⚠️ WARNING: {$errors} errors occurred\n";
}

echo "\nScript completed.\n";
?> 