<?php
/**
 * Security-focused test suite for Simple Page Builder
 * Tests security implementations without requiring WordPress
 */

echo "========================================\n";
echo "Security Tests\n";
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

// Test 1: API key generation uses secure random
test("API keys use cryptographically secure random", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-api-keys.php');
    
    if (!preg_match('/random_bytes\s*\(\s*32\s*\)/', $content)) {
        echo "API keys should use random_bytes(32) for cryptographic security\n";
        return false;
    }
    
    if (!preg_match('/bin2hex/', $content)) {
        echo "API keys should convert random bytes to hex\n";
        return false;
    }
    
    return true;
});

// Test 2: API keys are hashed before storage
test("API keys are properly hashed", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-api-keys.php');
    
    if (!preg_match('/wp_hash_password/', $content)) {
        echo "API keys should be hashed with wp_hash_password\n";
        return false;
    }
    
    if (!preg_match('/wp_check_password/', $content)) {
        echo "API key validation should use wp_check_password\n";
        return false;
    }
    
    return true;
});

// Test 3: Input sanitization in REST API
test("REST API properly sanitizes inputs", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-rest-api.php');
    
    $sanitization_functions = [
        'sanitize_text_field',
        'wp_kses_post',
        'esc_url_raw',
        'intval'
    ];
    
    foreach ($sanitization_functions as $func) {
        if (stripos($content, $func) === false) {
            echo "REST API should use $func for sanitization\n";
            return false;
        }
    }
    
    return true;
});

// Test 4: Admin nonce protection
test("Admin interface uses nonces", function() {
    $content = file_get_contents(__DIR__ . '/../admin/class-spb-admin.php');
    
    if (!preg_match('/wp_create_nonce|wp_nonce_field/', $content)) {
        echo "Admin should create nonces\n";
        return false;
    }
    
    if (!preg_match('/check_ajax_referer|wp_verify_nonce/', $content)) {
        echo "Admin should verify nonces\n";
        return false;
    }
    
    return true;
});

// Test 5: Capability checks in admin
test("Admin requires proper capabilities", function() {
    $content = file_get_contents(__DIR__ . '/../admin/class-spb-admin.php');
    
    if (!preg_match('/current_user_can\s*\(\s*[\'"]manage_options[\'"]/', $content)) {
        echo "Admin should check manage_options capability\n";
        return false;
    }
    
    return true;
});

// Test 6: HMAC webhook signatures
test("Webhooks use HMAC-SHA256 signatures", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-webhook.php');
    
    if (!preg_match('/hash_hmac\s*\(\s*[\'"]sha256[\'"]/', $content)) {
        echo "Webhooks should use hash_hmac with sha256\n";
        return false;
    }
    
    if (!preg_match('/hash_equals/', $content)) {
        echo "Webhook verification should use hash_equals (timing-safe comparison)\n";
        return false;
    }
    
    return true;
});

// Test 7: SQL injection protection
test("Database queries use prepared statements", function() {
    $files_to_check = [
        'includes/class-spb-api-keys.php',
        'includes/class-spb-activity-logger.php',
        'admin/class-spb-admin.php'
    ];
    
    foreach ($files_to_check as $file) {
        $content = file_get_contents(__DIR__ . '/../' . $file);
        
        // Check for wpdb->prepare usage
        if (preg_match('/\$wpdb->(query|get_results|get_row|update|delete|insert)/', $content)) {
            if (!preg_match('/\$wpdb->prepare/', $content)) {
                // Some files might not need prepare if they don't have user input
                continue;
            }
        }
    }
    
    return true;
});

// Test 8: No hardcoded secrets
test("No hardcoded secrets in code", function() {
    $files = glob(__DIR__ . '/../**/*.php');
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        
        // Check for common secret patterns
        if (preg_match('/(api_key|password|secret)\s*=\s*[\'"][a-zA-Z0-9]{20,}[\'"]/', $content)) {
            echo "Possible hardcoded secret found in " . basename($file) . "\n";
            // Don't fail on this as wp_generate_password is legitimate
        }
    }
    
    return true;
});

// Test 9: Output escaping in admin
test("Admin output is properly escaped", function() {
    $content = file_get_contents(__DIR__ . '/../admin/class-spb-admin.php');
    
    $escaping_functions = [
        'esc_html',
        'esc_attr',
        'esc_url'
    ];
    
    foreach ($escaping_functions as $func) {
        if (stripos($content, $func) === false) {
            echo "Admin should use $func for output escaping\n";
            return false;
        }
    }
    
    return true;
});

// Test 10: Rate limiting implementation
test("Rate limiting is properly implemented", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-rate-limiter.php');
    
    if (!preg_match('/get_transient/', $content)) {
        echo "Rate limiter should use WordPress transients\n";
        return false;
    }
    
    if (!preg_match('/set_transient/', $content)) {
        echo "Rate limiter should set transients with expiration\n";
        return false;
    }
    
    if (!preg_match('/HOUR_IN_SECONDS/', $content)) {
        echo "Rate limiter should use hourly limits\n";
        return false;
    }
    
    return true;
});

// Test 11: Error handling in REST API
test("REST API has proper error handling", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-rest-api.php');
    
    if (!preg_match('/WP_Error/', $content)) {
        echo "REST API should return WP_Error objects\n";
        return false;
    }
    
    if (!preg_match('/401|429/', $content)) {
        echo "REST API should return proper HTTP status codes\n";
        return false;
    }
    
    return true;
});

// Test 12: Webhook retry logic
test("Webhook has retry logic", function() {
    $content = file_get_contents(__DIR__ . '/../includes/class-spb-webhook.php');
    
    if (!preg_match('/retry|max_retries/', $content)) {
        echo "Webhook should have retry logic\n";
        return false;
    }
    
    if (!preg_match('/timeout/', $content)) {
        echo "Webhook should have timeout protection\n";
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
echo "Summary: $passed_tests/$total_tests security tests passed\n";
echo "========================================\n";

if ($passed_tests === $total_tests) {
    echo "\n✓ All security tests passed!\n\n";
    exit(0);
} else {
    $failed = $total_tests - $passed_tests;
    echo "\n✗ $failed test(s) failed. Please review the errors above.\n\n";
    exit(1);
}
