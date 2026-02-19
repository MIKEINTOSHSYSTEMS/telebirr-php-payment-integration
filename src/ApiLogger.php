<?php
namespace Telebirr;

use PDO;

class ApiLogger
{
    private $db;
    private $logger;
    
    public function __construct($db = null, $logger = null)
    {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    /**
     * Log API call to database
     * 
     * @param string $endpoint
     * @param string $method
     * @param mixed $requestData
     * @param mixed $responseData
     * @param int $statusCode
     */
    public function log($endpoint, $method, $requestData, $responseData, $statusCode)
    {
        // Log to file
        if ($this->logger) {
            $this->logger->debug("API Call: {$method} {$endpoint} - Status: {$statusCode}");
        }
        
        // Log to database
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
            if ($this->logger) {
                $this->logger->error("Failed to log API call to database: " . $e->getMessage());
            }
        }
    }
}