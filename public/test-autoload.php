<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/includes/header.php';

echo "<h1>Testing Autoloader</h1>";

$autoloadPath = __DIR__ . '/../vendor/autoload.php';
echo "Autoload path: $autoloadPath<br>";

if (file_exists($autoloadPath)) {
    echo "✅ Autoload file exists<br>";
    require_once $autoloadPath;
    echo "✅ Autoload file loaded<br>";
    
    echo "<pre>";
    echo "Loaded classes:\n";
    print_r(get_declared_classes());
    echo "</pre>";
} else {
    echo "❌ Autoload file not found!<br>";
    echo "Please run: composer install<br>";
}

require_once __DIR__ . '/includes/footer.php';
?>