<?php
/**
 * Step 4: Split products into simple and variable
 * Analyzes product data and separates into simple and complex products
 */

// Load WordPress
require_once('/var/www/html/wp-load.php');

// Increase memory limit for processing large product files
ini_set('memory_limit', '512M');

// Function to determine if product is simple
function is_simple_product($product_data) {
    // Check if product has variants
    if (!isset($product_data['variants']) || empty($product_data['variants'])) {
        return true;
    }
    
    $variants = $product_data['variants'];
    
    // If only one variant, it's simple
    if (count($variants) === 1) {
        return true;
    }
    
    // Check if all variants have the same price (simple product)
    $first_price = $variants[0]['price'];
    $all_same_price = true;
    
    foreach ($variants as $variant) {
        if ($variant['price'] != $first_price) {
            $all_same_price = false;
            break;
        }
    }
    
    // If all variants have same price and no meaningful options, it's simple
    if ($all_same_price) {
        // Check if options are just "Default Title" or similar
        if (isset($product_data['options'])) {
            foreach ($product_data['options'] as $option) {
                if ($option['name'] === 'Title' && count($option['values']) === 1 && $option['values'][0] === 'Default Title') {
                    return true;
                }
            }
        }
    }
    
    // Otherwise, it's variable
    return false;
}

// Function to analyze product complexity
function analyze_product_complexity($product_data) {
    $analysis = [
        'is_simple' => false,
        'variants_count' => 0,
        'has_different_prices' => false,
        'has_meaningful_options' => false,
        'options_count' => 0,
        'reason' => ''
    ];
    
    if (!isset($product_data['variants']) || empty($product_data['variants'])) {
        $analysis['is_simple'] = true;
        $analysis['reason'] = 'No variants';
        return $analysis;
    }
    
    $variants = $product_data['variants'];
    $analysis['variants_count'] = count($variants);
    
    if (count($variants) === 1) {
        $analysis['is_simple'] = true;
        $analysis['reason'] = 'Single variant';
        return $analysis;
    }
    
    // Check for different prices
    $first_price = $variants[0]['price'];
    foreach ($variants as $variant) {
        if ($variant['price'] != $first_price) {
            $analysis['has_different_prices'] = true;
            break;
        }
    }
    
    // Check for meaningful options
    if (isset($product_data['options'])) {
        $analysis['options_count'] = count($product_data['options']);
        foreach ($product_data['options'] as $option) {
            if ($option['name'] !== 'Title' || count($option['values']) > 1 || $option['values'][0] !== 'Default Title') {
                $analysis['has_meaningful_options'] = true;
                break;
            }
        }
    }
    
    // Determine if simple
    if (!$analysis['has_different_prices'] && !$analysis['has_meaningful_options']) {
        $analysis['is_simple'] = true;
        $analysis['reason'] = 'Same prices, no meaningful options';
    } else {
        $analysis['is_simple'] = false;
        $analysis['reason'] = 'Variable product with different prices or options';
    }
    
    return $analysis;
}

// Main execution
echo "🚀 Step 4: Splitting products into simple and variable\n";

// Read products file
$products_file = '/var/www/html/tmp/dealers_products_latest.json';
if (!file_exists($products_file)) {
    echo "❌ Products file not found: $products_file\n";
    exit(1);
}

echo "📖 Reading products file...\n";
$products_data = json_decode(file_get_contents($products_file), true);

if (!isset($products_data['products'])) {
    echo "❌ Invalid products file structure\n";
    exit(1);
}

$products = $products_data['products'];
$total_products = count($products);

echo "📊 Total products to analyze: $total_products\n\n";

// Analyze and split products
$simple_products = [];
$variable_products = [];
$analysis_summary = [];

echo "🔍 Analyzing products...\n";

foreach ($products as $index => $product) {
    $product_id = $product['id'];
    $product_title = $product['title'];
    
    // Analyze product complexity
    $complexity = analyze_product_complexity($product);
    
    // Store analysis
    $analysis_summary[] = [
        'id' => $product_id,
        'title' => $product_title,
        'is_simple' => $complexity['is_simple'],
        'variants_count' => $complexity['variants_count'],
        'has_different_prices' => $complexity['has_different_prices'],
        'has_meaningful_options' => $complexity['has_meaningful_options'],
        'options_count' => $complexity['options_count'],
        'reason' => $complexity['reason']
    ];
    
    // Split into arrays
    if ($complexity['is_simple']) {
        $simple_products[] = $product;
    } else {
        $variable_products[] = $product;
    }
    
    // Progress indicator
    if (($index + 1) % 100 === 0) {
        echo "Processed " . ($index + 1) . " / $total_products products\n";
    }
}

// Create output directory
$output_dir = '/var/www/html/tmp';
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
}

// Save split products
$timestamp = date('Ymd_His');
$simple_file = $output_dir . "/simple_products_{$timestamp}.json";
$variable_file = $output_dir . "/variable_products_{$timestamp}.json";
$analysis_file = $output_dir . "/products_split_analysis_{$timestamp}.json";
$summary_file = $output_dir . "/products_split_summary_{$timestamp}.json";

// Save simple products
file_put_contents($simple_file, json_encode([
    'source' => 'dealer',
    'split_timestamp' => $timestamp,
    'total_products' => count($simple_products),
    'products' => $simple_products
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Save variable products
file_put_contents($variable_file, json_encode([
    'source' => 'dealer',
    'split_timestamp' => $timestamp,
    'total_products' => count($variable_products),
    'products' => $variable_products
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Save detailed analysis
file_put_contents($analysis_file, json_encode($analysis_summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Create summary
$summary = [
    'timestamp' => $timestamp,
    'source_file' => basename($products_file),
    'total_products_analyzed' => $total_products,
    'simple_products' => [
        'count' => count($simple_products),
        'percentage' => round((count($simple_products) / $total_products) * 100, 2)
    ],
    'variable_products' => [
        'count' => count($variable_products),
        'percentage' => round((count($variable_products) / $total_products) * 100, 2)
    ],
    'complexity_breakdown' => [
        'single_variant' => 0,
        'same_prices_no_options' => 0,
        'different_prices' => 0,
        'meaningful_options' => 0
    ],
    'files_created' => [
        'simple_products' => basename($simple_file),
        'variable_products' => basename($variable_file),
        'analysis' => basename($analysis_file),
        'summary' => basename($summary_file)
    ]
];

// Count complexity reasons
foreach ($analysis_summary as $analysis) {
    if ($analysis['reason'] === 'Single variant') {
        $summary['complexity_breakdown']['single_variant']++;
    } elseif ($analysis['reason'] === 'Same prices, no meaningful options') {
        $summary['complexity_breakdown']['same_prices_no_options']++;
    } elseif ($analysis['has_different_prices']) {
        $summary['complexity_breakdown']['different_prices']++;
    } elseif ($analysis['has_meaningful_options']) {
        $summary['complexity_breakdown']['meaningful_options']++;
    }
}

file_put_contents($summary_file, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

// Display results
echo "\n📊 Split Results:\n";
echo "  📁 Simple products: " . count($simple_products) . " (" . $summary['simple_products']['percentage'] . "%)\n";
echo "  🔄 Variable products: " . count($variable_products) . " (" . $summary['variable_products']['percentage'] . "%)\n";
echo "  📋 Files created:\n";
echo "    - " . basename($simple_file) . "\n";
echo "    - " . basename($variable_file) . "\n";
echo "    - " . basename($analysis_file) . "\n";
echo "    - " . basename($summary_file) . "\n";

echo "\n🔍 Complexity Breakdown:\n";
echo "  • Single variant: " . $summary['complexity_breakdown']['single_variant'] . "\n";
echo "  • Same prices, no options: " . $summary['complexity_breakdown']['same_prices_no_options'] . "\n";
echo "  • Different prices: " . $summary['complexity_breakdown']['different_prices'] . "\n";
echo "  • Meaningful options: " . $summary['complexity_breakdown']['meaningful_options'] . "\n";

echo "\n✅ Step 4 completed successfully!\n";
?> 