<?php
namespace Telebirr;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PDO;
use PDOException;

/**
 * Main Telebirr Payment Gateway Class
 * Facade for all payment operations
 */
class TelebirrPayment
{
    private $config;
    private $applyToken;
    private $signer;
    private $verifier;
    private $createOrder;
    private $queryOrder;
    private $refundOrder;
    private $notifyHandler;
    private $logger;
    private $apiLogger;
    private $db;
    
    /**
     * Constructor
     * 
     * @param array $config Optional custom configuration
     * @param PDO $db Optional database connection
     */
    public function __construct($config = null, $db = null)
    {
        // Load configuration
        if ($config) {
            $this->config = $config;
        } else {
            $this->config = require __DIR__ . '/../config/config.php';
        }

        // Set timezone
        date_default_timezone_set($this->config['app']['timezone'] ?? 'Africa/Addis_Ababa');

        // Initialize logger
        $this->logger = $this->initLogger();

        // Initialize database (try multiple methods)
        $this->db = $db ?: $this->initDatabase();

        // Initialize API Logger (without duration parameter)
        $this->apiLogger = new ApiLogger($this->db, $this->logger, $this->config);

        // Log database status
        if ($this->db) {
            $this->logger->info("Database connected successfully");
        } else {
            $this->logger->warning("Database not connected - transactions will not be persisted");
        }

        // Initialize signer and verifier
        $this->signer = new Signer(
            $this->config['keys']['private_key'] ?? '',
            $this->logger
        );

        $this->verifier = new SignatureVerifier(
            $this->config['keys']['public_key'] ?? '',
            $this->logger
        );

        $this->applyToken = new ApplyFabricToken($this->config, $this->logger, $this->apiLogger);

        $this->createOrder = new CreateOrder(
            $this->config,
            $this->applyToken,
            $this->signer,
            $this->logger,
            $this->db,
            $this->apiLogger
        );

        $this->queryOrder = new QueryOrder(
            $this->config,
            $this->applyToken,
            $this->signer,
            $this->logger,
            $this->db,
            $this->apiLogger
        );

        $this->refundOrder = new RefundOrder(
            $this->config,
            $this->applyToken,
            $this->signer,
            $this->logger,
            $this->db,
            $this->apiLogger
        );

        $this->notifyHandler = new NotifyHandler(
            $this->config,
            $this->verifier,
            $this->logger,
            $this->db
        );
    }
    
    /**
     * Initialize logger
     */
    private function initLogger()
    {
        try {
            $log = new Logger('telebirr');
            
            // Ensure log directory exists
            $logPath = $this->config['logging']['path'] ?? __DIR__ . '/../logs/payment.log';
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                mkdir($logDir, 0777, true);
            }
            
            $logLevel = $this->getLogLevel($this->config['logging']['level'] ?? 'DEBUG');
            $log->pushHandler(new StreamHandler($logPath, $logLevel));
            
            return $log;
        } catch (\Exception $e) {
            error_log("Logger initialization failed: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Initialize database connection
     */
    private function initDatabase()
    {
        // First try to connect using database.php config
        if (file_exists(__DIR__ . '/../config/database.php')) {
            try {
                $dbConfig = require __DIR__ . '/../config/database.php';
                
                $dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['database']};charset={$dbConfig['charset']}";
                $pdo = new PDO(
                    $dsn,
                    $dbConfig['username'],
                    $dbConfig['password'],
                    $dbConfig['options'] ?? [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                
                // Test the connection
                $pdo->query("SELECT 1");
                
                return $pdo;
                
            } catch (PDOException $e) {
                $this->logger->error("Database connection failed: " . $e->getMessage());
            }
        }
        
        // Try to connect using environment variables directly
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $dbname = $_ENV['DB_NAME'] ?? 'telebirr_payments';
        $username = $_ENV['DB_USER'] ?? 'root';
        $password = $_ENV['DB_PASS'] ?? '';
        
        try {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            
            return $pdo;
            
        } catch (PDOException $e) {
            $this->logger->error("Database connection failed with env vars: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get Monolog log level
     */
    private function getLogLevel($level)
    {
        $levels = [
            'DEBUG' => Logger::DEBUG,
            'INFO' => Logger::INFO,
            'NOTICE' => Logger::NOTICE,
            'WARNING' => Logger::WARNING,
            'ERROR' => Logger::ERROR,
            'CRITICAL' => Logger::CRITICAL,
            'ALERT' => Logger::ALERT,
            'EMERGENCY' => Logger::EMERGENCY
        ];
        
        return $levels[strtoupper($level)] ?? Logger::DEBUG;
    }
    
    /**
     * Initialize payment
     * 
     * @param string $title Product title
     * @param float $amount Payment amount
     * @param array $additionalData Additional data
     * @return array Response with checkout URL
     */
    public function initializePayment($title, $amount, $additionalData = [])
    {
        try {
            $this->log("Initializing payment: {$title} - {$amount} ETB", 'INFO');
            
            // Create order
            $orderResult = $this->createOrder->createOrder($title, $amount, $additionalData);
            
            if (!$orderResult['success']) {
                throw new \Exception($orderResult['error']);
            }
            
            // Generate checkout URL
            $checkoutUrl = $this->createOrder->generateCheckoutUrl($orderResult['prepay_id']);
            
            return [
                'success' => true,
                'merch_order_id' => $orderResult['merch_order_id'],
                'prepay_id' => $orderResult['prepay_id'],
                'checkout_url' => $checkoutUrl,
                'response' => $orderResult['response'] ?? []
            ];
            
        } catch (\Exception $e) {
            $this->log("Payment initialization failed: " . $e->getMessage(), 'ERROR');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Query order status
     * 
     * @param string $merchOrderId Merchant order ID
     * @return array Order status
     */
    public function queryPayment($merchOrderId)
    {
        return $this->queryOrder->queryOrder($merchOrderId);
    }
    
    /**
     * Process refund
     * 
     * @param string $merchOrderId Original order ID
     * @param float $amount Refund amount
     * @param string $reason Refund reason
     * @return array Refund result
     */
    public function refundPayment($merchOrderId, $amount, $reason = '')
    {
        return $this->refundOrder->processRefund($merchOrderId, $amount, $reason);
    }
    
    /**
     * Handle payment notification
     * 
     * @param array $postData POST data from Telebirr
     * @return array Response
     */
    public function handleNotification($postData)
    {
        return $this->notifyHandler->handleNotification($postData);
    }
    
    /**
     * Get transaction by order ID
     * 
     * @param string $merchOrderId
     * @return array|false Transaction data
     */
    public function getTransaction($merchOrderId)
    {
        if (!$this->db) {
            return false;
        }
        
        try {
            $sql = "SELECT * FROM transactions WHERE merch_order_id = :merch_order_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':merch_order_id' => $merchOrderId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            $this->log("Failed to get transaction: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Get all transactions with pagination
     * 
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getTransactions($page = 1, $perPage = 20)
    {
        if (!$this->db) {
            return ['success' => false, 'error' => 'Database not available', 'data' => []];
        }
        
        try {
            $offset = ($page - 1) * $perPage;
            
            $sql = "SELECT * FROM transactions ORDER BY created_at DESC LIMIT :offset, :perPage";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
            $stmt->execute();
            
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM transactions";
            $countStmt = $this->db->query($countSql);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            return [
                'success' => true,
                'data' => $transactions,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ];
            
        } catch (\Exception $e) {
            $this->log("Failed to get transactions: " . $e->getMessage(), 'ERROR');
            return ['success' => false, 'error' => $e->getMessage(), 'data' => []];
        }
    }
    
    /**
     * Log API call to database
     * 
     * @param string $endpoint
     * @param string $method
     * @param array $requestData
     * @param array $responseData
     * @param int $statusCode
     */
    public function logApiCall($endpoint, $method, $requestData, $responseData, $statusCode)
    {
        if (!$this->db) {
            return;
        }
        
        try {
            $sql = "INSERT INTO api_logs (endpoint, method, request_data, response_data, status_code, ip_address, user_agent) 
                    VALUES (:endpoint, :method, :request_data, :response_data, :status_code, :ip_address, :user_agent)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':endpoint' => $endpoint,
                ':method' => $method,
                ':request_data' => is_string($requestData) ? $requestData : json_encode($requestData),
                ':response_data' => is_string($responseData) ? $responseData : json_encode($responseData),
                ':status_code' => $statusCode,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
        } catch (\Exception $e) {
            $this->log("Failed to log API call: " . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'INFO')
    {
        if ($this->logger) {
            $logLevel = $this->getLogLevel($level);
            $this->logger->log($logLevel, $message);
        } else {
            error_log("[Telebirr][$level] $message");
        }
    }
}