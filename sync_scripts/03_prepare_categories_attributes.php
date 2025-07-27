<?php
/**
 * Step 3: Prepare categories and brands (FIXED VERSION)
 * Creates categories from full tags without splitting
 */

// Load WordPress
require_once('/var/www/html/wp-load.php');

// Increase memory limit
ini_set('memory_limit', '512M');

// Function to normalize slug
function normalize_slug($input) {
    $slug = strtolower($input);
    $slug = preg_replace('/[^a-z0-9]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

// Function to create category from full tag
function create_category($tag) {
    $slug = normalize_slug($tag);
    $existing_term = get_term_by('slug', $slug, 'product_cat');
    if ($existing_term) {
        echo "Category already exists: $tag (ID: {$existing_term->term_id})\n";
        return $existing_term->term_id;
    }
    $result = wp_insert_term($tag, 'product_cat', [
        'slug' => $slug,
        'description' => "Category created from tag: $tag"
    ]);
    if (is_wp_error($result)) {
        echo "Failed to create category: $tag - " . $result->get_error_message() . "\n";
        return false;
    }
    echo "✅ Created category: $tag (ID: {$result['term_id']})\n";
    return $result['term_id'];
}

// Function to create brand
function create_brand($vendor) {
    $slug = normalize_slug($vendor);
    $existing_term = get_term_by('slug', $slug, 'product_brand');
    if ($existing_term) {
        echo "Brand already exists: $vendor (ID: {$existing_term->term_id})\n";
        return $existing_term->term_id;
    }
    $result = wp_insert_term($vendor, 'product_brand', [
        'slug' => $slug,
        'description' => "Brand created from vendor: $vendor"
    ]);
    if (is_wp_error($result)) {
        echo "Failed to create brand: $vendor - " . $result->get_error_message() . "\n";
        return false;
    }
    echo "✅ Created brand: $vendor (ID: {$result['term_id']})\n";
    return $result['term_id'];
}

// Main execution
echo "🚀 Step 3: Preparing categories and brands (FIXED VERSION)\n";
$dealer_analysis_file = '/var/www/html/tmp/dealer_analysis_20250727_215012.json';
if (!file_exists($dealer_analysis_file)) {
    echo "❌ Dealer analysis file not found\n";
    exit(1);
}
$dealer_data = json_decode(file_get_contents($dealer_analysis_file), true);

echo "\n📁 Creating categories from full tags...\n";
$categories_created = [];
foreach ($dealer_data['tags'] as $tag_data) {
    $tag = $tag_data['tag'];
    if (preg_match('/^Q\|/', $tag)) {
        echo "Skipping Q-code tag: $tag\n";
        continue;
    }
    echo "Processing tag: '$tag'\n";
    $category_id = create_category($tag);
    if ($category_id) {
        $categories_created[] = [
            'tag' => $tag,
            'category_id' => $category_id,
            'slug' => normalize_slug($tag)
        ];
    }
}

echo "\n🏷️ Creating brands from vendors...\n";
$brands_created = [];
foreach ($dealer_data['vendors'] as $vendor_data) {
    $vendor = $vendor_data['vendor'];
    echo "Processing vendor: '$vendor'\n";
    $brand_id = create_brand($vendor);
    if ($brand_id) {
        $brands_created[] = [
            'vendor' => $vendor,
            'brand_id' => $brand_id,
            'slug' => normalize_slug($vendor)
        ];
    }
}

$output_dir = '/var/www/html/tmp';
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
}
$timestamp = date('Ymd_His');
$categories_file = $output_dir . "/categories_created_fixed_{$timestamp}.json";
$brands_file = $output_dir . "/brands_created_fixed_{$timestamp}.json";
$mapping_file = $output_dir . "/category_brand_mapping_fixed_{$timestamp}.json";

file_put_contents($categories_file, json_encode($categories_created, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($brands_file, json_encode($brands_created, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$mapping = [
    'timestamp' => $timestamp,
    'categories' => $categories_created,
    'brands' => $brands_created,
    'summary' => [
        'categories_created' => count($categories_created),
        'brands_created' => count($brands_created),
        'total_tags_processed' => count($dealer_data['tags']),
        'total_vendors_processed' => count($dealer_data['vendors'])
    ]
];
file_put_contents($mapping_file, json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\n📊 Summary:\n";
echo "  📁 Categories created: " . count($categories_created) . "\n";
echo "  🏷️ Brands created: " . count($brands_created) . "\n";
echo "  📋 Files created:\n";
echo "    - " . basename($categories_file) . "\n";
echo "    - " . basename($brands_file) . "\n";
echo "    - " . basename($mapping_file) . "\n";
echo "\n✅ Step 3 completed successfully!\n";
?> 