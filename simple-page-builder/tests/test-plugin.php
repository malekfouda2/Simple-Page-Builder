<?php
/**
 * Comprehensive test suite for Simple Page Builder
 * This validates plugin structure, classes, and methods without requiring WordPress
 */

echo "========================================\n";
echo "Simple Page Builder - Comprehensive Tests\n";
echo "========================================\n\n";

$test_results = [];
$total_tests = 0;
$passed_tests = 0;

function test($name, $callback) {
    global $test_results, $total_tests, $passed_tests;
    $total_tests++;
    
    try {
        $result = $callback();
        if ($result) {
            $test_results[] = "✓ PASS: $name";
            $passed_tests++;
            return true;
        } else {
            $test_results[] = "✗ FAIL: $name";
            return false;
        }
    } catch (Exception $e) {
        $test_results[] = "✗ ERROR: $name - " . $e->getMessage();
        return false;
    }
}

// Test 1: Check all required files exist
test("All core files exist", function() {
    $required_files = [
        'simple-page-builder.php',
        'includes/class-spb-database.php',
        'includes/class-spb-api-keys.php',
        'includes/class-spb-rate-limiter.php',
        'includes/class-spb-activity-logger.php',
        'includes/class-spb-webhook.php',
        'includes/class-spb-rest-api.php',
        'admin/class-spb-admin.php',
        'assets/css/admin.css',
        'assets/js/admin.js',
        'README.md'
    ];
    
    foreach ($required_files as $file) {
        if (!file_exists(__DIR__ . '/../' . $file)) {
            echo "Missing file: $file\n";
            return false;
        }
    }
    return true;
});

// Test 2: Check PHP syntax for all files
test("All PHP files have valid syntax", function() {
    $php_files = [
        'simple-page-builder.php',
        'includes/class-spb-database.php',
        'includes/class-spb-api-keys.php',
        'includes/class-spb-rate-limiter.php',
        'includes/class-spb-activity-logger.php',
        'includes/class-spb-webhook.php',
        'includes/class-spb-rest-api.php',
        'admin/class-spb-admin.php'
    ];
    
    foreach ($php_files as $file) {
        $filepath = __DIR__ . '/../' . $file;
        exec("php -l " . escapeshellarg($filepath) . " 2>&1", $output, $return_var);
        if ($return_var !== 0) {
            echo "Syntax error in: $file\n";
            return false;
        }
    }
    return true;
});

// Test 3: Check main plugin file structure
test("Main plugin file has correct structure", function() {
    $content = file_get_contents(__DIR__ . '/../simple-page-builder.php');
    
    // Check for plugin header
    if (!preg_match('/Plugin Name:.*Simple Page Builder/i', $content)) {
        return false;
    }
    
    // Check for required constants
    if (!preg_match('/define\(.*SPB_VERSION/', $content)) {
        return false;
    }
    
    if (!preg_match('/define\(.*SPB_PLUGIN_DIR/', $content)) {
        return false;
    }
    
    // Check for main class
    if (!preg_match('/class Simple_Page_Builder/', $content)) {
        return false;
    }
    
    return true;
});

// Test 4: Check database class structure
test("Database class has required methods", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-database.php');
    
    $required_methods = [
        'create_tables',
        'get_api_keys_table',
        'get_activity_log_table',
        'get_created_pages_table'
    ];
    
    foreach ($required_methods as $method) {
        if (!preg_match('/function\s+' . preg_quote($method) . '\s*\(/', $content)) {
            echo "Missing method: $method in Database class\n";
            return false;
        }
    }
    
    return true;
});

// Test 5: Check API Keys class structure
test("API Keys class has required methods", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-api-keys.php');
    
    $required_methods = [
        'generate_api_key',
        'validate_api_key',
        'revoke_api_key',
        'update_last_used',
        'get_all_keys'
    ];
    
    foreach ($required_methods as $method) {
        if (!preg_match('/function\s+' . preg_quote($method) . '\s*\(/', $content)) {
            echo "Missing method: $method in API Keys class\n";
            return false;
        }
    }
    
    // Check for secure random generation
    if (!preg_match('/random_bytes/', $content)) {
        echo "API Keys class should use random_bytes for security\n";
        return false;
    }
    
    // Check for password hashing
    if (!preg_match('/wp_hash_password/', $content)) {
        echo "API Keys class should use wp_hash_password\n";
        return false;
    }
    
    return true;
});

// Test 6: Check Rate Limiter class
test("Rate Limiter class has required methods", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-rate-limiter.php');
    
    $required_methods = [
        'check_rate_limit',
        'get_remaining_requests',
        'reset_rate_limit'
    ];
    
    foreach ($required_methods as $method) {
        if (!preg_match('/function\s+' . preg_quote($method) . '\s*\(/', $content)) {
            echo "Missing method: $method in Rate Limiter class\n";
            return false;
        }
    }
    
    return true;
});

// Test 7: Check Activity Logger class
test("Activity Logger class has required methods", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-activity-logger.php');
    
    $required_methods = [
        'log_request',
        'log_created_page',
        'get_activity_logs',
        'get_created_pages'
    ];
    
    foreach ($required_methods as $method) {
        if (!preg_match('/function\s+' . preg_quote($method) . '\s*\(/', $content)) {
            echo "Missing method: $method in Activity Logger class\n";
            return false;
        }
    }
    
    return true;
});

// Test 8: Check Webhook class
test("Webhook class has required methods", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-webhook.php');
    
    $required_methods = [
        'send_notification',
        'generate_signature',
        'verify_signature'
    ];
    
    foreach ($required_methods as $method) {
        if (!preg_match('/function\s+' . preg_quote($method) . '\s*\(/', $content)) {
            echo "Missing method: $method in Webhook class\n";
            return false;
        }
    }
    
    // Check for HMAC usage
    if (!preg_match('/hash_hmac.*sha256/', $content)) {
        echo "Webhook class should use HMAC-SHA256\n";
        return false;
    }
    
    return true;
});

// Test 9: Check REST API class
test("REST API class has required methods", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-rest-api.php');
    
    $required_methods = [
        'register_routes',
        'create_pages',
        'check_api_key_permission'
    ];
    
    foreach ($required_methods as $method) {
        if (!preg_match('/function\s+' . preg_quote($method) . '\s*\(/', $content)) {
            echo "Missing method: $method in REST API class\n";
            return false;
        }
    }
    
    // Check for proper endpoint registration
    if (!preg_match('/pagebuilder\/v1/', $content)) {
        echo "REST API should register pagebuilder/v1 namespace\n";
        return false;
    }
    
    // Check for API key header validation
    if (!preg_match('/X-API-Key/', $content)) {
        echo "REST API should check X-API-Key header\n";
        return false;
    }
    
    return true;
});

// Test 10: Check Admin class
test("Admin class has required methods", function() {
    $content = file_get_contents(__DIR__ . '/../admin/class-spb-admin.php');
    
    $required_methods = [
        'add_admin_menu',
        'render_admin_page',
        'ajax_generate_api_key',
        'ajax_revoke_api_key',
        'ajax_export_logs'
    ];
    
    foreach ($required_methods as $method) {
        if (!preg_match('/function\s+' . preg_quote($method) . '\s*\(/', $content)) {
            echo "Missing method: $method in Admin class\n";
            return false;
        }
    }
    
    return true;
});

// Test 11: Check for security measures
test("Security measures are implemented", function() {
    $api_keys_content = file_get_contents(__DIR__ . '/../includes/class-spb-api-keys.php');
    $rest_api_content = file_get_contents(__DIR__ . '/../includes/class-spb-rest-api.php');
    $admin_content = file_get_contents(__DIR__ . '/../admin/class-spb-admin.php');
    
    // Check for input sanitization
    if (!preg_match('/sanitize_text_field/', $rest_api_content)) {
        echo "REST API should use sanitize_text_field\n";
        return false;
    }
    
    // Check for nonce usage in admin
    if (!preg_match('/wp_nonce_field|wp_create_nonce/', $admin_content)) {
        echo "Admin should use WordPress nonces\n";
        return false;
    }
    
    // Check for capability checks
    if (!preg_match('/manage_options/', $admin_content)) {
        echo "Admin should check manage_options capability\n";
        return false;
    }
    
    return true;
});

// Test 12: Check admin assets exist and are valid
test("Admin assets are valid", function() {
    $css_file = __DIR__ . '/../assets/css/admin.css';
    $js_file = __DIR__ . '/../assets/js/admin.js';
    
    if (!file_exists($css_file) || filesize($css_file) == 0) {
        echo "Admin CSS file is missing or empty\n";
        return false;
    }
    
    if (!file_exists($js_file) || filesize($js_file) == 0) {
        echo "Admin JS file is missing or empty\n";
        return false;
    }
    
    // Basic CSS validation
    $css_content = file_get_contents($css_file);
    if (!preg_match('/\{/', $css_content) || !preg_match('/\}/', $css_content)) {
        echo "Admin CSS appears to be invalid\n";
        return false;
    }
    
    // Check for expected CSS classes
    if (!preg_match('/\.spb-/', $css_content)) {
        echo "Admin CSS should contain spb- prefixed classes\n";
        return false;
    }
    
    // Basic JS validation
    $js_content = file_get_contents($js_file);
    if (!preg_match('/jQuery|function|ajax/', $js_content)) {
        echo "Admin JS appears to be invalid\n";
        return false;
    }
    
    return true;
});

// Test 13: Check README completeness
test("README.md is comprehensive", function() {
    $readme = file_get_contents(__DIR__ . '/../README.md');
    
    $required_sections = [
        'Features',
        'Installation',
        'API Documentation',
        'Authentication',
        'Webhook',
        'Examples',
        'Security'
    ];
    
    foreach ($required_sections as $section) {
        if (stripos($readme, $section) === false) {
            echo "README missing section: $section\n";
            return false;
        }
    }
    
    // Check for code examples
    if (!preg_match('/```/', $readme)) {
        echo "README should include code examples\n";
        return false;
    }
    
    return true;
});

// Test 14: Check for proper singleton pattern
test("All classes use proper singleton pattern", function() {
    $classes_to_check = [
        'includes/class-spb-database.php' => 'SPB_Database',
        'includes/class-spb-api-keys.php' => 'SPB_API_Keys',
        'includes/class-spb-rate-limiter.php' => 'SPB_Rate_Limiter',
        'includes/class-spb-activity-logger.php' => 'SPB_Activity_Logger',
        'includes/class-spb-webhook.php' => 'SPB_Webhook',
        'includes/class-spb-rest-api.php' => 'SPB_REST_API',
        'admin/class-spb-admin.php' => 'SPB_Admin'
    ];
    
    foreach ($classes_to_check as $file => $class_name) {
        $content = file_get_contents(__DIR__ . '/../' . $file);
        
        // Check for singleton pattern
        if (!preg_match('/private static \$instance/', $content)) {
            echo "$class_name should have private static \$instance\n";
            return false;
        }
        
        if (!preg_match('/public static function get_instance/', $content)) {
            echo "$class_name should have get_instance method\n";
            return false;
        }
    }
    
    return true;
});

// Test 15: Check database table creation
test("Database tables are properly defined", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-database.php');
    
    $required_tables = [
        'spb_api_keys',
        'spb_activity_log',
        'spb_created_pages'
    ];
    
    foreach ($required_tables as $table) {
        if (stripos($content, $table) === false) {
            echo "Missing table definition: $table\n";
            return false;
        }
    }
    
    // Check for proper indexing
    if (!preg_match('/KEY|INDEX/', $content)) {
        echo "Database tables should have proper indexes\n";
        return false;
    }
    
    return true;
});

// Print results
echo "\n========================================\n";
echo "Test Results\n";
echo "========================================\n\n";

foreach ($test_results as $result) {
    echo $result . "\n";
}

echo "\n========================================\n";
echo "Summary: $passed_tests/$total_tests tests passed\n";
echo "========================================\n";

if ($passed_tests === $total_tests) {
    echo "\n✓ All tests passed! Plugin is ready for submission.\n\n";
    exit(0);
} else {
    $failed = $total_tests - $passed_tests;
    echo "\n✗ $failed test(s) failed. Please review the errors above.\n\n";
    exit(1);
}
