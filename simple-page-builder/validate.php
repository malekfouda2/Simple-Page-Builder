<?php
/**
 * Simple validation script for the Simple Page Builder plugin
 * This script checks PHP syntax and provides basic validation
 */

echo "================================\n";
echo "Simple Page Builder - Validation\n";
echo "================================\n\n";

$files_to_check = [
    'simple-page-builder.php',
    'includes/class-spb-database.php',
    'includes/class-spb-api-keys.php',
    'includes/class-spb-rate-limiter.php',
    'includes/class-spb-activity-logger.php',
    'includes/class-spb-webhook.php',
    'includes/class-spb-rest-api.php',
    'admin/class-spb-admin.php'
];

$all_valid = true;

foreach ($files_to_check as $file) {
    $filepath = __DIR__ . '/' . $file;
    
    if (!file_exists($filepath)) {
        echo "❌ File not found: $file\n";
        $all_valid = false;
        continue;
    }
    
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($filepath) . " 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "✓ $file - OK\n";
    } else {
        echo "❌ $file - SYNTAX ERROR\n";
        echo implode("\n", $output) . "\n";
        $all_valid = false;
    }
}

echo "\n";

if ($all_valid) {
    echo "✓ All files validated successfully!\n";
    echo "\nPlugin Structure:\n";
    echo "- Main plugin file: simple-page-builder.php\n";
    echo "- Core classes: 7 PHP files\n";
    echo "- Admin assets: CSS and JavaScript\n";
    echo "\nThis plugin is ready to be installed in WordPress!\n";
    exit(0);
} else {
    echo "❌ Validation failed. Please fix the errors above.\n";
    exit(1);
}
