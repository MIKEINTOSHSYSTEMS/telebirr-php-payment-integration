<?php

/**
 * Telebirr Payment Demo - Home Page
 */

$page_title = 'Home - Telebirr Payment Gateway';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/includes/header.php';

use Telebirr\TelebirrPayment;

// Load configuration
$config = require __DIR__ . '/../config/config.php';

// Initialize payment gateway
$telebirr = new TelebirrPayment($config);

// Get recent transactions
$transactions = $telebirr->getTransactions(1, 10);

// Test database connection properly
$dbStatus = 'Not Connected';
$dbClass = 'offline';

if ($telebirr->getTransaction('test') !== false) {
    $dbStatus = 'Connected';
    $dbClass = 'online';
} else {
    // Try a direct query to check if database exists
    try {
        if (file_exists(__DIR__ . '/../config/database.php')) {
            $dbConfig = require __DIR__ . '/../config/database.php';
            $dsn = "mysql:host={$dbConfig['host']};charset={$dbConfig['charset']}";
            $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password']);
            $pdo->query("USE {$dbConfig['database']}");
            $dbStatus = 'Connected (No transactions)';
            $dbClass = 'online';
        }
    } catch (Exception $e) {
        $dbStatus = 'Not Connected';
        $dbClass = 'offline';
    }
}

// Get API endpoint status
$apiStatus = 'Checking...';
$apiClass = 'checking';

try {
    $ch = curl_init($config['api']['base_url'] . '/payment/v1/token');
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    unset($ch);

    if ($httpCode > 0) {
        $apiStatus = 'Reachable';
        $apiClass = 'online';
    } else {
        $apiStatus = 'Unreachable';
        $apiClass = 'offline';
    }
} catch (Exception $e) {
    $apiStatus = 'Error';
    $apiClass = 'offline';
}
?>

<div class="container">
    <header class="page-header">
        <h1>Telebirr Payment Gateway</h1>
        <p class="subtitle">Complete C2B Web Payment Integration Demo for PHP</p>
    </header>

    <div class="card-grid">
        <!-- New Payment Card -->
        <div class="card">
            <div class="card-header">
                <h2>💰 New Payment</h2>
            </div>
            <div class="card-body">
                <p>Initialize a new Telebirr payment with custom amount and description.</p>
                <a href="demo.php" class="btn btn-primary">Make Payment →</a>
            </div>
        </div>

        <!-- Query Payment Card -->
        <div class="card">
            <div class="card-header">
                <h2>🔍 Query Payment</h2>
            </div>
            <div class="card-body">
                <p>Check the status of an existing payment using order ID.</p>
                <a href="query-order.php" class="btn btn-secondary">Query Payment →</a>
            </div>
        </div>

        <!-- Refund Payment Card -->
        <div class="card">
            <div class="card-header">
                <h2>↩️ Refund Payment</h2>
            </div>
            <div class="card-body">
                <p>Process refunds for completed transactions.</p>
                <a href="refund-order.php" class="btn btn-warning">Process Refund →</a>
            </div>
        </div>

        <!-- Documentation Card -->
        <div class="card">
            <div class="card-header">
                <h2>📚 Documentation</h2>
            </div>
            <div class="card-body">
                <p>View integration guide and API documentation.</p>
                <a href="#" class="btn btn-info" onclick="window.open('https://github.com/MIKEINTOSHSYSTEMS/telebirr-php-payment-integration', '_blank')">Read Docs →</a>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="transactions-section">
        <h2>📊 Recent Transactions</h2>

        <?php if ($transactions['success'] && !empty($transactions['data'])): ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Title</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions['data'] as $tx): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($tx['merch_order_id'] ?? '') ?></code></td>
                                <td><?= htmlspecialchars($tx['title'] ?? '') ?></td>
                                <td><strong><?= htmlspecialchars($tx['amount'] ?? '0') ?> ETB</strong></td>
                                <td>
                                    <span class="status-badge status-<?= strtolower($tx['status'] ?? 'unknown') ?>">
                                        <?= htmlspecialchars($tx['status'] ?? 'UNKNOWN') ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($tx['created_at'] ?? 'now')) ?></td>
                                <td>
                                    <a href="query-order.php?order_id=<?= urlencode($tx['merch_order_id'] ?? '') ?>"
                                        class="btn btn-small">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                No transactions yet. Make your first payment!
            </div>
        <?php endif; ?>
    </div>

    <!-- System Status -->
    <div class="status-section">
        <h3>⚙️ System Status</h3>
        <div class="status-grid">
            <div class="status-item">
                <span class="status-label">Environment</span>
                <span class="status-value <?= $config['app']['debug'] ? 'dev' : 'prod' ?>">
                    <?= $config['app']['debug'] ? '🚀 Development' : '🏢 Production' ?>
                </span>
            </div>
            <div class="status-item">
                <span class="status-label">Merchant Code</span>
                <span class="status-value"><code><?= htmlspecialchars($config['credentials']['merchant_code'] ?? '') ?></code></span>
            </div>
            <div class="status-item">
                <span class="status-label">API Base URL</span>
                <span class="status-value"><code><?= htmlspecialchars($config['api']['base_url'] ?? '') ?></code></span>
            </div>
            <div class="status-item">
                <span class="status-label">API Status</span>
                <span class="status-value <?= $apiClass ?>">
                    <?= $apiStatus === 'Reachable' ? '✅ ' . $apiStatus : ($apiStatus === 'Checking...' ? '⏳ ' . $apiStatus : '❌ ' . $apiStatus) ?>
                </span>
            </div>
            <div class="status-item">
                <span class="status-label">Database</span>
                <span class="status-value <?= $dbClass ?>">
                    <?= $dbStatus === 'Connected' ? '✅ ' . $dbStatus : ($dbStatus === 'Connected (No transactions)' ? '⚠️ ' . $dbStatus : '❌ ' . $dbStatus) ?>
                </span>
            </div>
            <div class="status-item">
                <span class="status-label">Current Time</span>
                <span class="status-value">
                    <code><?= date('Y-m-d H:i:s') ?></code>
                </span>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>