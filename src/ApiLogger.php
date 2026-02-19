<?php
namespace Telebirr;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PDO;

/**
 * API Logger for Telebirr Payment Gateway
 * Logs API calls to both file and database
 */
class ApiLogger
{
    private $db;
    private $logger;
    private $config;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     * @param Logger $logger Monolog instance
     * @param array $config Configuration
     */
    public function __construct($db = null, $logger = null, $config = null)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->config = $config;
        
        // Initialize logger if not provided
        if (!$this->logger && $config) {
            $this->logger = $this->initLogger($config['logging'] ?? []);
        }
    }
    
    /**
     * Initialize file logger
     * 
     * @param array $loggingConfig
     * @return Logger
     */
    private function initLogger($loggingConfig)
    {
        $log = new Logger('telebirr-api');
        
        // Ensure log directory exists
        $logPath = $loggingConfig['path'] ?? __DIR__ . '/../logs/payment.log';
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $logLevel = $this->getLogLevel($loggingConfig['level'] ?? 'DEBUG');
        $log->pushHandler(new StreamHandler($logPath, $logLevel));
        
        return $log;
    }
    
    /**
     * Get Monolog log level
     * 
     * @param string $level
     * @return int
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
     * Log API call to both file and database
     * 
     * @param string $endpoint API endpoint URL
     * @param string $method HTTP method (GET, POST, etc.)
     * @param mixed $requestData Request data (array or string)
     * @param mixed $responseData Response data (array or string)
     * @param int $statusCode HTTP status code
     * @param float $duration Request duration in seconds
     * @return bool Success status
     */
    public function log($endpoint, $method, $requestData, $responseData, $statusCode, $duration = null)
    {
        $success = true;
        
        // Log to file
        if ($this->logger) {
            $this->logToFile($endpoint, $method, $requestData, $responseData, $statusCode, $duration);
        }
        
        // Log to database
        if ($this->db) {
            $dbSuccess = $this->logToDatabase($endpoint, $method, $requestData, $responseData, $statusCode, $duration);
            $success = $success && $dbSuccess;
        }
        
        return $success;
    }
    
    /**
     * Log to file using Monolog
     * 
     * @param string $endpoint
     * @param string $method
     * @param mixed $requestData
     * @param mixed $responseData
     * @param int $statusCode
     * @param float $duration
     */
    private function logToFile($endpoint, $method, $requestData, $responseData, $statusCode, $duration = null)
    {
        $logMessage = sprintf(
            "API Call: %s %s - Status: %d",
            $method,
            $endpoint,
            $statusCode
        );
        
        if ($duration !== null) {
            $logMessage .= sprintf(" - Duration: %.4fs", $duration);
        }
        
        if ($statusCode >= 200 && $statusCode < 300) {
            $this->logger->info($logMessage);
        } elseif ($statusCode >= 400 && $statusCode < 500) {
            $this->logger->warning($logMessage);
        } elseif ($statusCode >= 500) {
            $this->logger->error($logMessage);
        } else {
            $this->logger->debug($logMessage);
        }
        
        // Log request and response in debug mode
        if ($this->logger->isHandling(Logger::DEBUG)) {
            $this->logger->debug("Request: " . $this->formatData($requestData));
            $this->logger->debug("Response: " . $this->formatData($responseData));
        }
    }
    
    /**
     * Log to database
     * 
     * @param string $endpoint
     * @param string $method
     * @param mixed $requestData
     * @param mixed $responseData
     * @param int $statusCode
     * @param float $duration
     * @return bool
     */
    private function logToDatabase($endpoint, $method, $requestData, $responseData, $statusCode, $duration = null)
    {
        if (!$this->db) {
            return false;
        }
        
        try {
            // Check if api_logs table exists
            $tableCheck = $this->db->query("SHOW TABLES LIKE 'api_logs'");
            if ($tableCheck->rowCount() === 0) {
                // Table doesn't exist, create it
                $this->createApiLogsTable();
            }
            
            $sql = "INSERT INTO api_logs (
                endpoint, 
                method, 
                request_data, 
                response_data, 
                status_code, 
                duration,
                ip_address, 
                user_agent,
                created_at
            ) VALUES (
                :endpoint, 
                :method, 
                :request_data, 
                :response_data, 
                :status_code, 
                :duration,
                :ip_address, 
                :user_agent,
                NOW()
            )";
            
            $stmt = $this->db->prepare($sql);
            
            $result = $stmt->execute([
                ':endpoint' => $endpoint,
                ':method' => $method,
                ':request_data' => $this->formatData($requestData),
                ':response_data' => $this->formatData($responseData),
                ':status_code' => $statusCode,
                ':duration' => $duration,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            if ($result) {
                $this->logToFile(
                    'Database',
                    'INSERT',
                    ['log_id' => $this->db->lastInsertId()],
                    'API log stored successfully',
                    200,
                    null
                );
            }
            
            return $result;
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to log API call to database: " . $e->getMessage());
            }
            
            // Try to create table if it doesn't exist
            if (strpos($e->getMessage(), "Table '.*\.api_logs' doesn't exist") !== false) {
                try {
                    $this->createApiLogsTable();
                    // Retry the insert
                    return $this->logToDatabase($endpoint, $method, $requestData, $responseData, $statusCode, $duration);
                } catch (\Exception $retryException) {
                    if ($this->logger) {
                        $this->logger->error("Failed to create api_logs table: " . $retryException->getMessage());
                    }
                }
            }
            
            return false;
        }
    }
    
    /**
     * Create api_logs table if it doesn't exist
     * 
     * @return bool
     */
    private function createApiLogsTable()
    {
        if (!$this->db) {
            return false;
        }
        
        try {
            $sql = "CREATE TABLE IF NOT EXISTS api_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                endpoint VARCHAR(255),
                method VARCHAR(10),
                request_data TEXT,
                response_data TEXT,
                status_code INT,
                duration DECIMAL(10,4),
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_endpoint (endpoint),
                INDEX idx_status_code (status_code),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
            
            if ($this->logger) {
                $this->logger->info("api_logs table created successfully");
            }
            
            return true;
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to create api_logs table: " . $e->getMessage());
            }
            return false;
        }
    }
    
    /**
     * Format data for logging
     * 
     * @param mixed $data
     * @return string
     */
    private function formatData($data)
    {
        if (is_string($data)) {
            // Truncate very long strings
            if (strlen($data) > 10000) {
                return substr($data, 0, 10000) . '... [truncated]';
            }
            return $data;
        }
        
        if (is_array($data) || is_object($data)) {
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (strlen($json) > 10000) {
                return substr($json, 0, 10000) . '... [truncated]';
            }
            return $json;
        }
        
        return (string)$data;
    }
    
    /**
     * Get API logs from database with pagination
     * 
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public function getLogs($page = 1, $perPage = 50, $filters = [])
    {
        if (!$this->db) {
            return ['success' => false, 'error' => 'Database not available', 'data' => []];
        }
        
        try {
            $offset = ($page - 1) * $perPage;
            
            $where = [];
            $params = [];
            
            if (!empty($filters['endpoint'])) {
                $where[] = "endpoint LIKE :endpoint";
                $params[':endpoint'] = '%' . $filters['endpoint'] . '%';
            }
            
            if (!empty($filters['method'])) {
                $where[] = "method = :method";
                $params[':method'] = $filters['method'];
            }
            
            if (!empty($filters['status_code'])) {
                $where[] = "status_code = :status_code";
                $params[':status_code'] = $filters['status_code'];
            }
            
            if (!empty($filters['date_from'])) {
                $where[] = "created_at >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where[] = "created_at <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
            
            $whereClause = empty($where) ? "" : "WHERE " . implode(" AND ", $where);
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM api_logs $whereClause";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
            
            // Get logs
            $sql = "SELECT * FROM api_logs $whereClause ORDER BY created_at DESC LIMIT :offset, :perPage";
            $stmt = $this->db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':perPage', $perPage, PDO::PARAM_INT);
            $stmt->execute();
            
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'success' => true,
                'data' => $logs,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                ]
            ];
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to get logs: " . $e->getMessage());
            }
            return ['success' => false, 'error' => $e->getMessage(), 'data' => []];
        }
    }
    
    /**
     * Clean old logs
     * 
     * @param int $days Keep logs for X days
     * @return int Number of deleted rows
     */
    public function cleanOldLogs($days = 30)
    {
        if (!$this->db) {
            return 0;
        }
        
        try {
            $sql = "DELETE FROM api_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':days' => $days]);
            
            $deleted = $stmt->rowCount();
            
            if ($this->logger) {
                $this->logger->info("Cleaned {$deleted} old log entries");
            }
            
            return $deleted;
            
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to clean old logs: " . $e->getMessage());
            }
            return 0;
        }
    }
}