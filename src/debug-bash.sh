#!/bin/bash

# WordPress Debug Script
# Run this script to check server capabilities

echo "=== WordPress Server Debug Script ==="
echo "Starting at: $(date)"
echo ""

# Check if we're in the right directory
if [ ! -f "wp-config.php" ]; then
    echo "❌ Error: wp-config.php not found. Please run this script from WordPress root directory."
    exit 1
fi

echo "✅ WordPress root directory found"
echo ""

# 1. Check PHP availability
echo "1. PHP AVAILABILITY:"
if command -v php &> /dev/null; then
    PHP_VERSION=$(php -v | head -n 1)
    echo "   ✅ PHP found: $PHP_VERSION"
    
    # Check PHP modules
    echo "   Checking PHP modules:"
    if php -m | grep -q "gd"; then
        echo "   ✅ GD extension available"
    else
        echo "   ❌ GD extension NOT available"
    fi
    
    if php -m | grep -q "imagick"; then
        echo "   ✅ ImageMagick extension available"
    else
        echo "   ❌ ImageMagick extension NOT available"
    fi
    
    # Check PHP settings
    echo "   PHP settings:"
    MEMORY_LIMIT=$(php -r "echo ini_get('memory_limit');")
    echo "   Memory limit: $MEMORY_LIMIT"
    
    MAX_EXECUTION_TIME=$(php -r "echo ini_get('max_execution_time');")
    echo "   Max execution time: $MAX_EXECUTION_TIME"
    
else
    echo "   ❌ PHP CLI not found"
    echo "   Install with: apt install php8.3-cli"
fi
echo ""

# 2. Check WP-CLI availability
echo "2. WP-CLI AVAILABILITY:"
if command -v wp &> /dev/null; then
    WP_VERSION=$(wp --version)
    echo "   ✅ WP-CLI found: $WP_VERSION"
else
    echo "   ❌ WP-CLI not found"
    echo "   Install with: curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar && chmod +x wp-cli.phar && mv wp-cli.phar /usr/local/bin/wp"
fi
echo ""

# 3. Check upload directory
echo "3. UPLOAD DIRECTORY:"
if [ -d "wp-content/uploads" ]; then
    echo "   ✅ Upload directory exists"
    
    if [ -w "wp-content/uploads" ]; then
        echo "   ✅ Upload directory is writable"
    else
        echo "   ❌ Upload directory is NOT writable"
        echo "   Fix with: chmod 755 wp-content/uploads"
    fi
    
    # Count files in uploads
    UPLOAD_COUNT=$(find wp-content/uploads -type f | wc -l)
    echo "   Files in uploads: $UPLOAD_COUNT"
    
else
    echo "   ❌ Upload directory not found"
fi
echo ""

# 4. Check WordPress database connection
echo "4. WORDPRESS DATABASE:"
if command -v wp &> /dev/null; then
    if wp db check &> /dev/null; then
        echo "   ✅ Database connection OK"
        
        # Count attachments
        ATTACHMENT_COUNT=$(wp post list --post_type=attachment --format=count 2>/dev/null || echo "0")
        echo "   Total attachments: $ATTACHMENT_COUNT"
        
        IMAGE_COUNT=$(wp post list --post_type=attachment --post_mime_type=image --format=count 2>/dev/null || echo "0")
        echo "   Image attachments: $IMAGE_COUNT"
        
    else
        echo "   ❌ Database connection failed"
    fi
else
    echo "   ⚠️ Cannot check database (WP-CLI not available)"
fi
echo ""

# 5. Check specific problematic image
echo "5. PROBLEMATIC IMAGE CHECK:"
if command -v wp &> /dev/null; then
    PROBLEM_IMAGE=$(wp post get 8979 --field=post_title 2>/dev/null)
    if [ ! -z "$PROBLEM_IMAGE" ]; then
        echo "   Image ID 8979: $PROBLEM_IMAGE"
        
        # Get file path
        FILE_PATH=$(wp post meta get 8979 _wp_attached_file 2>/dev/null)
        if [ ! -z "$FILE_PATH" ]; then
            FULL_PATH="wp-content/uploads/$FILE_PATH"
            echo "   File path: $FULL_PATH"
            
            if [ -f "$FULL_PATH" ]; then
                echo "   ✅ File exists"
                
                # Get file size
                FILE_SIZE=$(stat -c%s "$FULL_PATH" 2>/dev/null || echo "0")
                FILE_SIZE_MB=$(echo "scale=2; $FILE_SIZE/1024/1024" | bc 2>/dev/null || echo "0")
                echo "   File size: ${FILE_SIZE_MB}MB"
                
                # Check if readable
                if [ -r "$FULL_PATH" ]; then
                    echo "   ✅ File is readable"
                else
                    echo "   ❌ File is NOT readable"
                fi
                
            else
                echo "   ❌ File does NOT exist"
            fi
        else
            echo "   ❌ Cannot get file path"
        fi
    else
        echo "   ❌ Image ID 8979 not found"
    fi
else
    echo "   ⚠️ Cannot check image (WP-CLI not available)"
fi
echo ""

# 6. Test file creation
echo "6. FILE CREATION TEST:"
TEST_FILE="wp-content/uploads/test-write-$(date +%s).txt"
if echo "test" > "$TEST_FILE" 2>/dev/null; then
    echo "   ✅ Can create files in uploads directory"
    rm -f "$TEST_FILE"
else
    echo "   ❌ Cannot create files in uploads directory"
fi
echo ""

# 7. Recommendations
echo "7. RECOMMENDATIONS:"
if ! command -v php &> /dev/null; then
    echo "   ❌ Install PHP CLI: apt install php8.3-cli"
fi

if ! command -v wp &> /dev/null; then
    echo "   ❌ Install WP-CLI for easier management"
fi

if [ ! -w "wp-content/uploads" ]; then
    echo "   ❌ Fix upload directory permissions: chmod 755 wp-content/uploads"
fi

if ! php -m | grep -q "gd"; then
    echo "   ❌ Install GD extension: apt install php8.3-gd"
fi

echo ""
echo "=== DEBUG COMPLETED ==="
echo "Completed at: $(date)" 