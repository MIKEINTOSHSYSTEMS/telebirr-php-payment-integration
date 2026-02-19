<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/header.php';

use Telebirr\ApplyFabricToken;

echo "<h1>🔑 Telebirr Token Diagnostic</h1>";

// Load configuration
$config = require __DIR__ . '/../config/config.php';

echo "<h2>Configuration</h2>";
echo "<pre>";
echo "Base URL: " . $config['api']['base_url'] . "\n";
echo "Fabric App ID: " . substr($config['credentials']['fabric_app_id'], 0, 5) . "..." . substr($config['credentials']['fabric_app_id'], -5) . "\n";
echo "App Secret: " . substr($config['credentials']['app_secret'], 0, 5) . "..." . substr($config['credentials']['app_secret'], -5) . "\n";
echo "Merchant App ID: " . substr($config['credentials']['merchant_app_id'], 0, 5) . "..." . substr($config['credentials']['merchant_app_id'], -5) . "\n";
echo "Merchant Code: " . $config['credentials']['merchant_code'] . "\n";
echo "</pre>";

// Test direct API connection
echo "<h2>Testing API Connection</h2>";

$tokenUrl = $config['api']['base_url'] . "/payment/v1/token";
echo "Token URL: " . $tokenUrl . "<br>";

// Test with cURL
$ch = curl_init($tokenUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-APP-Key: " . $config['credentials']['fabric_app_id']
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "appSecret" => $config['credentials']['app_secret']
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_VERBOSE, true);

$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

echo "HTTP Code: " . $httpCode . "<br>";

if ($curlError) {
    echo "cURL Error: " . $curlError . "<br>";
}

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
echo "<h3>cURL Verbose Log:</h3>";
echo "<pre>" . htmlspecialchars($verboseLog) . "</pre>";

if ($response) {
    echo "<h3>Response:</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $data = json_decode($response, true);
    if (isset($data['token'])) {
        echo "<p style='color: green'>✅ Token received successfully!</p>";
        echo "Token: " . substr($data['token'], 0, 20) . "...<br>";
    } else {
        echo "<p style='color: red'>❌ No token in response</p>";
    }
}

unset($ch);

// Test with your ApplyFabricToken class
echo "<h2>Testing ApplyFabricToken Class</h2>";

try {
    $applyToken = new ApplyFabricToken($config);
    $result = $applyToken->applyToken();
    
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if (isset($result['token'])) {
        echo "<p style='color: green'>✅ ApplyFabricToken class working!</p>";
    } else {
        echo "<p style='color: red'>❌ ApplyFabricToken class failed</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red'>Error: " . $e->getMessage() . "</p>";
}

// Test network connectivity
echo "<h2>Network Connectivity Test</h2>";

$host = parse_url($config['api']['base_url'], PHP_URL_HOST);
$port = parse_url($config['api']['base_url'], PHP_URL_PORT) ?: 38443;

echo "Testing connection to {$host}:{$port}...<br>";

$connection = @fsockopen($host, $port, $errno, $errstr, 10);
if ($connection) {
    echo "<p style='color: green'>✅ Connection successful!</p>";
    fclose($connection);
} else {
    echo "<p style='color: red'>❌ Connection failed: $errstr ($errno)</p>";
    
    // Try with different ports
    $portsToTry = [38443, 443, 80, 8080];
    foreach ($portsToTry as $testPort) {
        if ($testPort != $port) {
            $connection = @fsockopen($host, $testPort, $errno, $errstr, 5);
            if ($connection) {
                echo "<p style='color: orange'>⚠️ Port {$testPort} is open (but configured port is {$port})</p>";
                fclose($connection);
            }
        }
    }
}

require_once __DIR__ . '/includes/footer.php';
?>