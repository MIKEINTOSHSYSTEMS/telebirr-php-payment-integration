<?php
/**
 * Telebirr Payment Demo - Home Page
 */

require_once __DIR__ . '/../vendor/autoload.php';

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
    curl_close($ch);
    
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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telebirr Payment Integration Demo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Enhanced System Status Styles */
        .status-section {
            background: linear-gradient(135deg, #011777 0%, #001234 100%);
            color: white;
            border-radius: 15px;
            padding: 30px;
            margin-top: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .status-section h3 {
            margin: 0 0 20px 0;
            font-size: 1.5em;
            font-weight: 600;
            color: white;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .status-item {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 20px;
            border: 1px solid rgba(255,255,255,0.2);
            transition: transform 0.3s ease;
        }
        
        .status-item:hover {
            transform: translateY(-5px);
            background: rgba(255,255,255,0.15);
        }
        
        .status-label {
            display: block;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
            margin-bottom: 10px;
        }
        
        .status-value {
            display: block;
            font-size: 1.2em;
            font-weight: 600;
        }
        
        .status-value.dev {
            color: #ffd700;
            text-shadow: 0 0 10px rgba(255,215,0,0.3);
        }
        
        .status-value.prod {
            color: #00ff00;
            text-shadow: 0 0 10px rgba(0,255,0,0.3);
        }
        
        .status-value.online {
            color: #00ff00;
            text-shadow: 0 0 10px rgba(0,255,0,0.3);
        }
        
        .status-value.offline {
            color: #ff6b6b;
            text-shadow: 0 0 10px rgba(255,107,107,0.3);
        }
        
        .status-value.checking {
            color: #ffd700;
            text-shadow: 0 0 10px rgba(255,215,0,0.3);
        }
        
        .status-value code {
            background: rgba(0,0,0,0.3);
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 0.9em;
            color: #fff;
        }
        
        /* Enhanced Transactions Table */
        .transactions-section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .transactions-section h2 {
            margin: 0 0 20px 0;
            color: #333;
            font-size: 1.8em;
            font-weight: 600;
        }
        
        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        .table th {
            background: #f8f9fa;
            padding: 15px;
            font-weight: 600;
            color: #555;
            text-transform: uppercase;
            font-size: 0.85em;
            letter-spacing: 0.5px;
        }
        
        .table td {
            background: white;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 8px;
        }
        
        .table tr:hover td {
            background: #f8f9fa;
            transform: scale(1.01);
            transition: all 0.3s ease;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-unknown {
            background: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        .btn-small {
            display: inline-block;
            padding: 5px 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 20px;
            font-size: 0.85em;
            transition: all 0.3s ease;
        }
        
        .btn-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        .alert {
            padding: 20px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            font-size: 1.1em;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>🇪🇹 Telebirr Payment Gateway</h1>
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
                    <a href="#" class="btn btn-info" onclick="alert('See README.md for documentation')">Read Docs →</a>
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
                                           class="btn-small">View</a>
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
</body>
</html>