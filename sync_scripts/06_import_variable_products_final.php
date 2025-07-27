<?php
/**
 * Stage 6: Import Variable Products (Final Version)
 * 
 * This script imports variable products with proper attributes and variations.
 * All fixes from debugging are incorporated.
 */

// Load WordPress
require_once('/var/www/html/wp-load.php');

// Set memory limit
ini_set('memory_limit', '512M');

echo "🚀 Stage 6: Import Variable Products (Final Version)\n";
echo "==================================================\n\n";

// Load data
$variable_products_file = '/var/www/html/tmp/variable_products_20250727_215111.json';
if (!file_exists($variable_products_file)) {
    echo "❌ Variable products file not found: $variable_products_file\n";
    exit;
}

$variable_products_data = json_decode(file_get_contents($variable_products_file), true);
if (!$variable_products_data) {
    echo "❌ Failed to decode variable products JSON\n";
    exit;
}

$variable_products = isset($variable_products_data['products']) ? $variable_products_data['products'] : array();
echo "📦 Found " . count($variable_products) . " variable products to import\n\n";

// Load mappings
$mapping_file = '/var/www/html/tmp/category_brand_mapping_fixed_20250727_215103.json';

$mapping_data = file_exists($mapping_file) ? json_decode(file_get_contents($mapping_file), true) : array();
$categories_mapping = array();
$brands_mapping = array();

if (isset($mapping_data['categories'])) {
    foreach ($mapping_data['categories'] as $category) {
        $categories_mapping[$category['tag']] = $category['category_id'];
    }
}

if (isset($mapping_data['brands'])) {
    foreach ($mapping_data['brands'] as $brand) {
        $brands_mapping[$brand['vendor']] = $brand['brand_id'];
    }
}

echo "📋 Loaded " . count($categories_mapping) . " category mappings\n";
echo "📋 Loaded " . count($brands_mapping) . " brand mappings\n\n";

/**
 * Normalize attribute slug
 */
function normalize_attribute_slug($name) {
    return sanitize_title($name);
}

/**
 * Create or update attribute
 */
function create_or_update_attribute($attribute_name) {
    $attribute_slug = normalize_attribute_slug($attribute_name);
    
    global $wpdb;
    
    // Check if attribute exists
    $existing_attribute = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
        $attribute_slug
    ));
    
    if (!$existing_attribute) {
        // Create new attribute
        $attribute_data = array(
            'name' => $attribute_name,
            'slug' => $attribute_slug,
            'type' => 'select',
            'order_by' => 'menu_order',
            'has_archives' => true // This ensures attribute_public = 1
        );
        
        $attribute_id = wc_create_attribute($attribute_data);
        echo "  ✅ Created attribute: $attribute_name\n";
        return $attribute_id;
    } else {
        echo "  ℹ️ Attribute exists: $attribute_name\n";
        return $existing_attribute->attribute_id;
    }
}

/**
 * Find product by dealer ID
 */
function find_product_by_dealer_id($dealer_id) {
    // Extract the base SKU from dealer ID (e.g., 10204066152785 -> 101179)
    $base_sku = null;
    if (preg_match('/(\d{5,6})/', $dealer_id, $matches)) {
        $base_sku = $matches[1];
    }
    
    if ($base_sku) {
        global $wpdb;
        $product_id = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p 
             JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
             WHERE p.post_type = 'product' 
             AND pm.meta_key = '_sku' 
             AND pm.meta_value LIKE %s",
            $base_sku . '%'
        ));
        
        if ($product_id) {
            return $product_id;
        }
    }
    
    return false;
}

/**
 * Set product categories and brands
 */
function set_product_categories_and_brands($product_id, $product_data, $categories_mapping, $brands_mapping) {
    // Set category
    if (isset($product_data['category']) && isset($categories_mapping[$product_data['category']])) {
        $category_id = $categories_mapping[$product_data['category']];
        wp_set_object_terms($product_id, array($category_id), 'product_cat');
        echo "  ✅ Category set: " . $product_data['category'] . " (ID: $category_id)\n";
    }
    
    // Set brand
    if (isset($product_data['brand']) && isset($brands_mapping[$product_data['brand']])) {
        $brand_id = $brands_mapping[$product_data['brand']];
        wp_set_object_terms($product_id, array($brand_id), 'product_brand');
        echo "  ✅ Brand set: " . $product_data['brand'] . " (ID: $brand_id)\n";
    }
}

/**
 * Attach image to product
 */
function attach_image_to_product($product_id, $image_url) {
    if (empty($image_url)) {
        return false;
    }
    
    // Download image
    $upload_dir = wp_upload_dir();
    $image_data = file_get_contents($image_url);
    
    if ($image_data === false) {
        echo "  ⚠️ Failed to download image: $image_url\n";
        return false;
    }
    
    $filename = basename($image_url);
    $file_path = $upload_dir['path'] . '/' . $filename;
    
    // Save image
    if (file_put_contents($file_path, $image_data) === false) {
        echo "  ⚠️ Failed to save image: $file_path\n";
        return false;
    }
    
    // Prepare file array for wp_handle_sideload
    $file_array = array(
        'name' => $filename,
        'tmp_name' => $file_path
    );
    
    // Move file to uploads directory
    $file = wp_handle_sideload($file_array, array('test_form' => false));
    
    if (isset($file['error'])) {
        echo "  ⚠️ Image upload error: " . $file['error'] . "\n";
        return false;
    }
    
    // Create attachment
    $attachment = array(
        'post_mime_type' => $file['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    
    $attach_id = wp_insert_attachment($attachment, $file['file'], $product_id);
    
    if (is_wp_error($attach_id)) {
        echo "  ⚠️ Failed to create attachment: " . $attach_id->get_error_message() . "\n";
        return false;
    }
    
    // Generate attachment metadata
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $file['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);
    
    // Set as product thumbnail
    set_post_thumbnail($product_id, $attach_id);
    
    echo "  ✅ Image attached: $filename\n";
    return true;
}

/**
 * Create variable product
 */
function create_variable_product($product_data, $categories_mapping, $brands_mapping) {
    // Find existing product
    $product_id = find_product_by_dealer_id($product_data['id']);
    
    if (!$product_id) {
        echo "❌ Product not found for dealer ID: " . $product_data['id'] . "\n";
        return false;
    }
    
    echo "📦 Processing: " . $product_data['title'] . " (ID: $product_id)\n";
    
    // Get product object
    $product = wc_get_product($product_id);
    if (!$product) {
        echo "❌ Failed to get product object\n";
        return false;
    }
    
    // Set product type to variable
    wp_set_object_terms($product_id, 'variable', 'product_type');
    
    // Create attributes
    if (isset($product_data['attributes']) && is_array($product_data['attributes'])) {
        $product_attributes = array();
        
        foreach ($product_data['attributes'] as $attr_name => $attr_values) {
            // Create attribute
            create_or_update_attribute($attr_name);
            
            // Create WC_Product_Attribute object
            $attribute = new WC_Product_Attribute();
            $attribute->set_name($attr_name);
            $attribute->set_options($attr_values);
            $attribute->set_position(0);
            $attribute->set_visible(true);
            $attribute->set_variation(true);
            
            $product_attributes[] = $attribute;
        }
        
        // Set attributes on product
        $product->set_attributes($product_attributes);
        $product->save();
        
        echo "  ✅ Attributes set: " . count($product_data['attributes']) . " attributes\n";
    }
    
    // Set categories and brands
    set_product_categories_and_brands($product_id, $product_data, $categories_mapping, $brands_mapping);
    
    // Attach image if available
    if (isset($product_data['image_url'])) {
        attach_image_to_product($product_id, $product_data['image_url']);
    }
    
    // Create variations
    create_variations($product_id, $product_data);
    
    return true;
}

/**
 * Create variations
 */
function create_variations($product_id, $product_data) {
    $product = wc_get_product($product_id);
    if (!$product || $product->get_type() !== 'variable') {
        echo "  ❌ Product is not variable\n";
        return;
    }
    
    // Delete existing variations
    $existing_variations = $product->get_children();
    foreach ($existing_variations as $variation_id) {
        $variation = wc_get_product($variation_id);
        if ($variation) {
            $variation->delete(true);
        }
    }
    
    echo "  🗑️ Deleted " . count($existing_variations) . " existing variations\n";
    
    // Generate variation combinations
    $attributes = $product_data['attributes'] ?? array();
    $variation_combinations = generate_variation_combinations($attributes);
    
    echo "  🔄 Creating " . count($variation_combinations) . " variations...\n";
    
    foreach ($variation_combinations as $index => $combination) {
        $variation = new WC_Product_Variation();
        $variation->set_parent_id($product_id);
        
        // Set SKU
        $base_sku = $product->get_sku();
        $variation_sku = $base_sku . '-var-' . ($index + 1);
        $variation->set_sku($variation_sku);
        
        // Set attributes
        $variation_attributes = array();
        foreach ($combination as $attr_name => $attr_value) {
            $variation_attributes[strtolower($attr_name)] = $attr_value;
        }
        $variation->set_attributes($variation_attributes);
        
        // Set price
        $price = calculate_variation_price($product_data, $combination);
        $variation->set_regular_price($price);
        $variation->set_price($price);
        
        // Set stock
        $variation->set_manage_stock(false);
        $variation->set_stock_status('instock');
        
        // Save variation
        $variation_id = $variation->save();
        
        if ($variation_id) {
            echo "    ✅ Variation " . ($index + 1) . " created (ID: $variation_id)\n";
        } else {
            echo "    ❌ Failed to create variation " . ($index + 1) . "\n";
        }
    }
    
    // Refresh product
    $product = wc_get_product($product_id);
    $product->save();
}

/**
 * Generate variation combinations
 */
function generate_variation_combinations($attributes) {
    if (empty($attributes)) {
        return array();
    }
    
    $attribute_names = array_keys($attributes);
    $attribute_values = array_values($attributes);
    
    $combinations = array();
    $counts = array_map('count', $attribute_values);
    $total_combinations = array_product($counts);
    
    for ($i = 0; $i < $total_combinations; $i++) {
        $combination = array();
        $temp = $i;
        
        for ($j = 0; $j < count($attribute_names); $j++) {
            $index = $temp % $counts[$j];
            $combination[$attribute_names[$j]] = $attribute_values[$j][$index];
            $temp = intval($temp / $counts[$j]);
        }
        
        $combinations[] = $combination;
    }
    
    return $combinations;
}

/**
 * Calculate variation price
 */
function calculate_variation_price($product_data, $combination) {
    // Use dealer price if available, otherwise retail price
    $price = $product_data['dealer_price'] ?? $product_data['retail_price'] ?? 0;
    
    // Convert USD to EUR (1 USD = 0.92 EUR)
    $price_eur = $price * 0.92;
    
    return number_format($price_eur, 2, '.', '');
}

// Process products
$success_count = 0;
$error_count = 0;

foreach ($variable_products as $product_data) {
    try {
        if (create_variable_product($product_data, $categories_mapping, $brands_mapping)) {
            $success_count++;
        } else {
            $error_count++;
        }
    } catch (Exception $e) {
        echo "❌ Error processing product: " . $e->getMessage() . "\n";
        $error_count++;
    }
    
    echo "\n";
}

echo "🎉 Import completed!\n";
echo "✅ Success: $success_count\n";
echo "❌ Errors: $error_count\n";

// Clear cache
wp_cache_flush();
echo "🧹 Cache cleared\n";
?> 