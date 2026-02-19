<?php

/**
 * Maintenance script to clean old logs
 * Run via cron:
 * 0 0 * * * php /path/to/clean-logs.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../../config/config.php';

// Get retention days (env overrides config, fallback = 30)
$daysToKeep = (int) ($_ENV['LOG_RETENTION_DAYS']
    ?? $config['log_retention_days']
    ?? 30);

if ($daysToKeep <= 0) {
    $daysToKeep = 30;
}

echo "Cleaning logs older than {$daysToKeep} days...\n";

try {
    // Validate DB config
    if (!isset($config['db'])) {
        throw new RuntimeException("Database configuration not found.");
    }

    $dbConfig = $config['db'];

    $dsn = $dbConfig['dsn'] ?? null;
    $username = $dbConfig['username'] ?? null;
    $password = $dbConfig['password'] ?? null;
    $options = $dbConfig['options'] ?? [];

    if (!$dsn) {
        throw new RuntimeException("Invalid DSN configuration.");
    }

    // Default PDO options for safety
    $options = $options + [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);

    // Prepare safe deletion query
    $sql = "
        DELETE FROM api_logs
        WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':days', $daysToKeep, PDO::PARAM_INT);
    $stmt->execute();

    $deleted = $stmt->rowCount();

    echo "✅ Deleted {$deleted} old log entries.\n";
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Done.\n";
exit(0);
