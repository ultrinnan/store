<?php
/*
Plugin Name: Fixer Hardening
Description: Hides user enumeration (REST users endpoint and ?author= scans) from visitors who are not logged in.
Version: 1.0.0
*/
add_filter('rest_endpoints', function ($endpoints) {
    if (is_user_logged_in()) return $endpoints;
    foreach (array_keys($endpoints) as $route) {
        if (preg_match('#^/wp/v2/users#', $route)) unset($endpoints[$route]);
    }
    return $endpoints;
});
add_action('template_redirect', function () {
    if (!is_user_logged_in() && isset($_GET['author'])) { wp_safe_redirect(home_url('/'), 301); exit; }
});
