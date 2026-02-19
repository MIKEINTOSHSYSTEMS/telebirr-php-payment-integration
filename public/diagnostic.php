<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Telebirr Payment Diagnostic</h1>";

// PHP Version
echo "<h2>PHP Information</h2>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Display Errors: " . ini_get('display_errors') . "<br>";
echo "Error Reporting: " . error_reporting() . "<br>";

// Check required extensions
echo "<h2>Required Extensions</h2>";
$required = ['curl', 'json', 'openssl', 'pdo_mysql'];
foreach ($required as $ext) {
    echo "$ext: " . (extension_loaded($ext) ? '✅' : '❌') . "<br>";
}

// Check directory structure
echo "<h2>Directory Structure</h2>";
$dirs = [
    __DIR__,
    __DIR__ . '/../config',
    __DIR__ . '/../config/keys',
    __DIR__ . '/../logs',
    __DIR__ . '/../vendor',
];

foreach ($dirs as $dir) {
    echo "$dir: " . (is_dir($dir) ? '✅' : '❌') . "<br>";
}

// Check file permissions
echo "<h2>File Permissions</h2>";
$files = [
    __DIR__ . '/../.env',
    __DIR__ . '/../config/config.php',
    __DIR__ . '/../config/keys/private_key.pem',
    __DIR__ . '/../config/keys/public_key.pem',
    __DIR__ . '/../logs/payment.log',
];

foreach ($files as $file) {
    if (file_exists($file)) {
        echo "$file: ✅ (exists, " . substr(sprintf('%o', fileperms($file)), -4) . ")<br>";
    } else {
        echo "$file: ❌ (not found)<br>";
    }
}

// Test autoloader
echo "<h2>Autoloader Test</h2>";
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
    echo "✅ Autoloader loaded<br>";
    
    // Try to load config
    try {
        $config = require __DIR__ . '/../config/config.php';
        echo "✅ Config loaded<br>";
        echo "<pre>";
        print_r(array_keys($config));
        echo "</pre>";
    } catch (Exception $e) {
        echo "❌ Config error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Autoloader not found. Run: composer install<br>";
}

// Test database connection (if configured)
echo "<h2>Database Test</h2>";
if (file_exists(__DIR__ . '/../config/database.php')) {
    try {
        $dbConfig = require __DIR__ . '/../config/database.php';
        $dsn = "mysql:host={$dbConfig['host']};charset={$dbConfig['charset']}";
        $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        echo "✅ Database connection successful<br>";
    } catch (Exception $e) {
        echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
    }
} else {
    echo "ℹ️ No database configuration (optional)<br>";
}