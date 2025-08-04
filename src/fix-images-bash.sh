#!/bin/bash

# Image Fix Script for WordPress
# Run this script on your server to regenerate missing image sizes

echo "=== WordPress Image Fix Script ==="
echo "Starting at: $(date)"
echo ""

# Check if we're in the right directory
if [ ! -f "wp-config.php" ]; then
    echo "❌ Error: wp-config.php not found. Please run this script from WordPress root directory."
    exit 1
fi

# Check if wp-cli is available
if command -v wp &> /dev/null; then
    echo "✅ WP-CLI found, using it for image processing"
    USE_WPCLI=true
else
    echo "⚠️ WP-CLI not found, will try direct PHP execution"
    USE_WPCLI=false
fi

# Function to process images with WP-CLI
process_with_wpcli() {
    echo "Processing images with WP-CLI..."
    
    # Get total count
    TOTAL_IMAGES=$(wp post list --post_type=attachment --post_mime_type=image --format=count)
    echo "Found $TOTAL_IMAGES images to process"
    
    # Process in batches
    BATCH_SIZE=50
    PROCESSED=0
    ERRORS=0
    
    for ((i=0; i<TOTAL_IMAGES; i+=BATCH_SIZE)); do
        echo "Processing batch $((i/BATCH_SIZE + 1))..."
        
        # Get batch of image IDs
        IMAGE_IDS=$(wp post list --post_type=attachment --post_mime_type=image --field=ID --posts_per_page=$BATCH_SIZE --offset=$i)
        
        for ID in $IMAGE_IDS; do
            PROCESSED=$((PROCESSED + 1))
            echo "[$PROCESSED/$TOTAL_IMAGES] Processing image ID: $ID"
            
            # Regenerate thumbnails for this image
            if wp media regenerate $ID --only-missing --yes; then
                echo "  ✅ Success"
            else
                echo "  ❌ Error"
                ERRORS=$((ERRORS + 1))
            fi
        done
        
        echo "Batch completed. Processed: $PROCESSED, Errors: $ERRORS"
        echo ""
    done
    
    echo "=== FINAL RESULTS ==="
    echo "Total processed: $PROCESSED"
    echo "Errors: $ERRORS"
}

# Function to process images with direct PHP
process_with_php() {
    echo "Processing images with direct PHP..."
    
    # Create a temporary PHP script
    cat > temp_fix_images.php << 'EOF'
<?php
// Pідключаємо WordPress
require_once('wp-load.php');

// Збільшуємо ліміти
ini_set('memory_limit', '1G');
ini_set('max_execution_time', 0);
set_time_limit(0);

echo "Starting image processing...\n";

// Get all image attachments
$attachments = get_posts(array(
    'post_type' => 'attachment',
    'post_mime_type' => 'image',
    'numberposts' => -1,
    'post_status' => 'any'
));

$total_images = count($attachments);
echo "Found $total_images images to process\n\n";

$processed = 0;
$errors = 0;
$created = 0;

foreach ($attachments as $attachment) {
    $processed++;
    
    echo "[$processed/$total_images] Processing: {$attachment->post_title} (ID: {$attachment->ID})";
    
    $file_path = get_attached_file($attachment->ID);
    
    if (!$file_path || !file_exists($file_path)) {
        echo " - ERROR: File not found\n";
        $errors++;
        continue;
    }
    
    try {
        // Regenerate all sizes
        $new_metadata = wp_generate_attachment_metadata($attachment->ID, $file_path);
        
        if ($new_metadata && is_array($new_metadata)) {
            wp_update_attachment_metadata($attachment->ID, $new_metadata);
            
            $new_sizes = isset($new_metadata['sizes']) ? count($new_metadata['sizes']) : 0;
            $created += $new_sizes;
            
            echo " - SUCCESS: $new_sizes sizes created\n";
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
        echo "--- Progress: $processed/$total_images ---\n";
    }
}

echo "\n=== FINAL RESULTS ===\n";
echo "Total images: $total_images\n";
echo "Processed: $processed\n";
echo "Created sizes: $created\n";
echo "Errors: $errors\n";

if ($errors === 0) {
    echo "✅ SUCCESS: All images processed successfully!\n";
} else {
    echo "⚠️ WARNING: $errors errors occurred\n";
}
?>
EOF

    # Execute the PHP script
    if command -v php &> /dev/null; then
        php temp_fix_images.php
        PHP_RESULT=$?
    else
        echo "❌ Error: PHP CLI not available"
        echo "Please install PHP CLI: apt install php8.3-cli"
        PHP_RESULT=1
    fi
    
    # Clean up
    rm -f temp_fix_images.php
    
    return $PHP_RESULT
}

# Main execution
if [ "$USE_WPCLI" = true ]; then
    process_with_wpcli
else
    process_with_php
fi

echo ""
echo "Script completed at: $(date)" 