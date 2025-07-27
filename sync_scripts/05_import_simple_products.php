<?php
/**
 * Step 5: Import simple products
 * Imports simple products with support for updating existing products
 */

// Load WordPress
require_once('/var/www/html/wp-load.php');

// Load media functions
require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

// Increase memory limit for processing large datasets
ini_set('memory_limit', '512M');

// Function to find existing product
function find_existing_product($product_data) {
    $product_id = $product_data['id'];
    $title = $product_data['title'];
    
    // Get SKU from first variant
    $sku = null;
    if (isset($product_data['variants']) && !empty($product_data['variants']) && isset($product_data['variants'][0]['sku'])) {
        $sku = $product_data['variants'][0]['sku'];
    }
    
    // Try to find by dealer product ID (most reliable)
    $existing_posts = get_posts([
        'meta_key' => 'dealer_product_id',
        'meta_value' => $product_id,
        'post_type' => 'product',
        'post_status' => 'any',
        'numberposts' => 1
    ]);
    
    if (!empty($existing_posts)) {
        return $existing_posts[0]->ID;
    }
    
    // Try to find by SKU (very reliable)
    if ($sku) {
        $existing_product = wc_get_product_id_by_sku($sku);
        if ($existing_product) {
            return $existing_product;
        }
    }
    
    // Try to find by title
    $existing_post = get_page_by_title($title, OBJECT, 'product');
    if ($existing_post) {
        return $existing_post->ID;
    }
    
    return false;
}

// Function to find retail price by SKU
function find_retail_price_by_sku($sku, $retail_products) {
    foreach ($retail_products as $retail_product) {
        if (isset($retail_product['variants']) && !empty($retail_product['variants'])) {
            $retail_sku = $retail_product['variants'][0]['sku'] ?? null;
            if ($retail_sku === $sku) {
                return floatval($retail_product['variants'][0]['price']);
            }
        }
    }
    return null;
}

// Function to calculate prices with retail comparison
function calculate_prices($product_data, $retail_products) {
    // Get dealer price from first variant (in EUR)
    $dealer_price = 0;
    $sku = null;
    if (isset($product_data['variants']) && !empty($product_data['variants'])) {
        $dealer_price = floatval($product_data['variants'][0]['price']);
        $sku = $product_data['variants'][0]['sku'] ?? null;
    }
    
    // Try to find retail price by SKU (in USD)
    $retail_price_usd = null;
    if ($sku) {
        $retail_price_usd = find_retail_price_by_sku($sku, $retail_products);
    }
    
    if ($retail_price_usd) {
        // Convert retail price from USD to EUR
        $usd_to_eur_rate = 0.92; // Current approximate rate
        $retail_price_eur = $retail_price_usd * $usd_to_eur_rate;
        
        // Our price = retail price in EUR - 10%
        $our_price = $retail_price_eur * 0.9;
        $regular_price = $our_price;
        $sale_price = null; // No sale price needed
        
        echo "  💰 Dealer: €$dealer_price, Retail: \$$retail_price_usd (€$retail_price_eur), Our: €$our_price (SKU: $sku)\n";
    } else {
        // No retail price found - apply markup to dealer price (e.g., +30%)
        $markup_multiplier = 1.3; // 30% markup
        $our_price = $dealer_price * $markup_multiplier;
        $regular_price = $our_price;
        $sale_price = null; // No sale price needed
        
        echo "  💰 Dealer: €$dealer_price, No retail found, Our: €$our_price (markup applied, SKU: $sku)\n";
    }
    
    return [
        'regular_price' => $regular_price,
        'sale_price' => $sale_price,
        'dealer_price' => $dealer_price,
        'retail_price_usd' => $retail_price_usd,
        'retail_price_eur' => $retail_price_eur ?? null,
        'our_price' => $our_price,
        'sku' => $sku
    ];
}

// Function to map categories from tags
function map_categories($product_data, $categories_mapping) {
    $category_ids = [];
    
    if (isset($product_data['tags'])) {
        foreach ($product_data['tags'] as $tag) {
            // Skip Q-codes
            if (preg_match('/^Q\|/', $tag)) {
                continue;
            }
            
            // Find category by exact tag match
            foreach ($categories_mapping as $mapping) {
                if ($mapping['tag'] === $tag) {
                    $category_ids[] = $mapping['category_id'];
                    echo "  🏷️ Mapped tag '$tag' to category '{$mapping['tag']}'\n";
                    break;
                }
            }
        }
    }
    
    return $category_ids;
}

// Function to map brand from vendor
function map_brand($product_data, $brands_mapping) {
    if (isset($product_data['vendor'])) {
        foreach ($brands_mapping as $mapping) {
            if ($mapping['vendor'] === $product_data['vendor']) {
                return $mapping['brand_id'];
            }
        }
    }
    
    return false;
}

// Function to create simple product
function create_simple_product($product_data, $prices, $category_ids, $brand_id) {
    $product = new WC_Product_Simple();
    
    // Set basic product data
    $product->set_name($product_data['title']);
    $product->set_description($product_data['body_html'] ?? '');
    $product->set_short_description(substr($product_data['body_html'] ?? '', 0, 200) . '...');
    
    // Set prices
    $product->set_regular_price($prices['regular_price']);
    $product->set_sale_price($prices['sale_price']);
    
    // Set categories
    if (!empty($category_ids)) {
        $product->set_category_ids($category_ids);
    }
    
    // Set brand
    if ($brand_id) {
        $product->set_meta_data('_product_brand', $brand_id);
    }
    
    // Set dealer product ID for future reference
    $product->set_meta_data('dealer_product_id', $product_data['id']);
    
    // Set SKU from first variant if available
    if (isset($product_data['variants']) && !empty($product_data['variants']) && isset($product_data['variants'][0]['sku'])) {
        $sku = $product_data['variants'][0]['sku'];
        $product->set_sku($sku);
        echo "  📦 SKU: $sku\n";
    }
    
    // Set product status
    $product->set_status('publish');
    
    // Save product
    $product_id = $product->save();
    
    if (is_wp_error($product_id)) {
        return false;
    }
    
    return $product_id;
}

// Function to update existing product
function update_simple_product($existing_id, $product_data, $prices, $category_ids, $brand_id) {
    $product = wc_get_product($existing_id);
    
    if (!$product) {
        return false;
    }
    
    // Update basic data
    $product->set_name($product_data['title']);
    $product->set_description($product_data['body_html'] ?? '');
    $product->set_short_description(substr($product_data['body_html'] ?? '', 0, 200) . '...');
    
    // Update prices
    $product->set_regular_price($prices['regular_price']);
    $product->set_sale_price($prices['sale_price']);
    
    // Update categories
    if (!empty($category_ids)) {
        $product->set_category_ids($category_ids);
    }
    
    // Update brand
    if ($brand_id) {
        $product->set_meta_data('_product_brand', $brand_id);
    }
    
    // Update dealer product ID
    $product->set_meta_data('dealer_product_id', $product_data['id']);
    
    // Update SKU from first variant if available
    if (isset($product_data['variants']) && !empty($product_data['variants']) && isset($product_data['variants'][0]['sku'])) {
        $sku = $product_data['variants'][0]['sku'];
        $product->set_sku($sku);
        echo "  📦 SKU: $sku\n";
    }
    
    // Save product
    $product_id = $product->save();
    
    if (is_wp_error($product_id)) {
        return false;
    }
    
    return $product_id;
}

// Function to add product images
function add_product_images($product_id, $product_data) {
    if (!isset($product_data['images']) || empty($product_data['images'])) {
        return false;
    }
    
    $image_ids = [];
    $first_image = true;
    
    foreach ($product_data['images'] as $image_data) {
        if (!isset($image_data['src'])) {
            continue;
        }
        
        $image_url = $image_data['src'];
        
        // Download and attach image
        $attachment_id = media_sideload_image($image_url, $product_id, '', 'id');
        
        if (!is_wp_error($attachment_id)) {
            $image_ids[] = $attachment_id;
            
            // Set first image as product image
            if ($first_image) {
                set_post_thumbnail($product_id, $attachment_id);
                $first_image = false;
            }
        }
    }
    
    // Set product gallery
    if (!empty($image_ids)) {
        update_post_meta($product_id, '_product_image_gallery', implode(',', $image_ids));
    }
    
    return true;
}

// Main execution
echo "🚀 Step 5: Importing simple products\n";

// Load simple products
$simple_products_file = '/var/www/html/tmp/simple_products_20250727_215111.json';
if (!file_exists($simple_products_file)) {
    echo "❌ Simple products file not found: $simple_products_file\n";
    exit(1);
}

echo "📖 Loading simple products...\n";
$simple_data = json_decode(file_get_contents($simple_products_file), true);
$products = $simple_data['products'];
$total_products = count($products);

echo "📊 Total simple products to process: $total_products\n\n";

// Load categories and brands mapping
$categories_mapping_file = '/var/www/html/tmp/category_brand_mapping_fixed_20250727_215103.json';
$brands_mapping_file = '/var/www/html/tmp/category_brand_mapping_fixed_20250727_215103.json';

$mapping_data = file_exists($categories_mapping_file) ? json_decode(file_get_contents($categories_mapping_file), true) : array();
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

echo "📁 Loaded " . count($categories_mapping) . " categories mapping\n";
echo "🏷️ Loaded " . count($brands_mapping) . " brands mapping\n\n";

// Process products
$created_count = 0;
$updated_count = 0;
$failed_count = 0;
$processed_products = [];

echo "🔄 Processing products...\n";

foreach ($products as $index => $product_data) {
    $product_id = $product_data['id'];
    $title = $product_data['title'];
    
    echo "Processing: $title (ID: $product_id)\n";
    
    // Find existing product
    $existing_id = find_existing_product($product_data);
    
    // Load retail products for price comparison
    $retail_products_file = '/var/www/html/tmp/retail_products_latest.json';
    $retail_products = [];
    if (file_exists($retail_products_file)) {
        $retail_products = json_decode(file_get_contents($retail_products_file), true)['products'] ?? [];
    }

    // Calculate prices
    $prices = calculate_prices($product_data, $retail_products);
    
    // Map categories and brand
    $category_ids = map_categories($product_data, $categories_mapping);
    $brand_id = map_brand($product_data, $brands_mapping);
    
    // Create or update product
    if ($existing_id) {
        $result_id = update_simple_product($existing_id, $product_data, $prices, $category_ids, $brand_id);
        if ($result_id) {
            echo "  ✅ Updated existing product (ID: $result_id)\n";
            $updated_count++;
        } else {
            echo "  ❌ Failed to update product\n";
            $failed_count++;
        }
    } else {
        $result_id = create_simple_product($product_data, $prices, $category_ids, $brand_id);
        if ($result_id) {
            echo "  ✅ Created new product (ID: $result_id)\n";
            $created_count++;
        } else {
            echo "  ❌ Failed to create product\n";
            $failed_count++;
        }
    }
    
    // Add images if product was created/updated successfully
    if ($result_id) {
        add_product_images($result_id, $product_data);
        echo "  🖼️ Added images\n";
        
        // Store processed product info
        $processed_products[] = [
            'dealer_id' => $product_id,
            'woo_id' => $result_id,
            'title' => $title,
            'action' => $existing_id ? 'updated' : 'created',
            'categories' => $category_ids,
            'brand' => $brand_id,
            'prices' => $prices
        ];
    }
    
    // Progress indicator
    if (($index + 1) % 50 === 0) {
        echo "\n📊 Progress: " . ($index + 1) . " / $total_products processed\n";
        echo "  Created: $created_count, Updated: $updated_count, Failed: $failed_count\n\n";
    }
}

// Create output directory
$output_dir = '/var/www/html/tmp';
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
}

// Save results
$timestamp = date('Ymd_His');
$results_file = $output_dir . "/simple_products_import_results_{$timestamp}.json";
$summary_file = $output_dir . "/simple_products_import_summary_{$timestamp}.json";

// Save detailed results
file_put_contents($results_file, json_encode($processed_products, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Create summary
$summary = [
    'timestamp' => $timestamp,
    'source_file' => basename($simple_products_file),
    'total_products_processed' => $total_products,
    'results' => [
        'created' => $created_count,
        'updated' => $updated_count,
        'failed' => $failed_count,
        'success_rate' => round((($created_count + $updated_count) / $total_products) * 100, 2)
    ],
    'files_created' => [
        'results' => basename($results_file),
        'summary' => basename($summary_file)
    ]
];

file_put_contents($summary_file, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Display final results
echo "\n📊 Final Results:\n";
echo "  ✅ Created: $created_count\n";
echo "  🔄 Updated: $updated_count\n";
echo "  ❌ Failed: $failed_count\n";
echo "  📈 Success Rate: " . $summary['results']['success_rate'] . "%\n";
echo "  📋 Files created:\n";
echo "    - " . basename($results_file) . "\n";
echo "    - " . basename($summary_file) . "\n";

echo "\n✅ Step 5 completed successfully!\n";
?> 