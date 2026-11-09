<?php
/**
 * Bootstrap file for PHPUnit tests.
 */

// Load Composer autoloader - assumes running from project root
$vendor_autoload = __DIR__ . '/../../../../../../vendor/autoload.php';
if (!file_exists($vendor_autoload)) {
    // Try relative to project root
    $vendor_autoload = getcwd() . '/vendor/autoload.php';
}
require_once $vendor_autoload;

// Mock WordPress functions for unit tests
if (!function_exists('sanitize_file_name')) {
    function sanitize_file_name($filename) {
        return basename($filename);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $flags = 0) {
        return json_encode($data, $flags);
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return [
            'basedir' => sys_get_temp_dir(),
            'baseurl' => 'http://example.com',
        ];
    }
}

if (!function_exists('wp_mkdir_p')) {
    function wp_mkdir_p($path) {
        return @mkdir($path, 0755, true);
    }
}

// Transient mocks
$transients = [];

if (!function_exists('get_transient')) {
    function get_transient($key) {
        global $transients;
        return $transients[$key] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        global $transients;
        $transients[$key] = $value;
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        global $transients;
        unset($transients[$key]);
        return true;
    }
}

// Register the plugin's PSR-4 autoloader
// __DIR__ = /Users/serhii/www/store/src/wp-content/plugins/f-pro-bearpaw-dealer/tests
// Need to go up one level to plugin root, then into src/
spl_autoload_register(function ($class) {
    $prefix = 'FPro\\BearpawDealer\\';
    $base_dir = __DIR__ . '/../src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});