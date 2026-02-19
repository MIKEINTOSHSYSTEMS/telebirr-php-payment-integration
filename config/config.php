<?php
/**
 * Telebirr Payment Gateway Configuration
 */

// Load environment variables
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

return [
    // API Configuration
    'api' => [
        'base_url' => $_ENV['BASE_URL'],
        'web_base_url' => $_ENV['WEB_BASE_URL'],
        'timeout' => 30,
        'verify_ssl' => false, // Set to true in production
    ],
    
    // App Credentials
    'credentials' => [
        'fabric_app_id' => $_ENV['FABRIC_APP_ID'],
        'app_secret' => $_ENV['APP_SECRET'],
        'merchant_app_id' => $_ENV['MERCHANT_APP_ID'],
        'merchant_code' => $_ENV['MERCHANT_CODE'],
    ],
    
    // RSA Keys
    'keys' => [
        'private_key' => file_get_contents(__DIR__ . '/keys/private_key.pem'),
        'public_key' => file_get_contents(__DIR__ . '/keys/public_key.pem'),
    ],
    
    // Application Settings
    'app' => [
        'name' => $_ENV['APP_NAME'],
        'url' => $_ENV['APP_URL'],
        'debug' => filter_var($_ENV['DEBUG_MODE'], FILTER_VALIDATE_BOOLEAN),
        'timezone' => $_ENV['TIMEZONE'],
    ],
    
    // Callback URLs
    'callbacks' => [
        'notify_url' => $_ENV['NOTIFY_URL'],
        'redirect_url' => $_ENV['REDIRECT_URL'],
        'failure_url' => $_ENV['FAILURE_URL'],
    ],
    
    // Payment Defaults
    'payment' => [
        'currency' => 'ETB',
        'timeout_express' => '120m',
        'business_type' => 'BuyGoods',
        'trade_type' => 'Checkout',
        'version' => '1.0',
        'sign_type' => 'SHA256WithRSA',
        'payee_identifier_type' => '04',
        'payee_type' => '5000',
    ],
    
    // Logging
    'logging' => [
        'path' => __DIR__ . '/../' . $_ENV['LOG_PATH'],
        'level' => $_ENV['LOG_LEVEL'],
    ],
];