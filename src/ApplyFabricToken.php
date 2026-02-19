<?php
namespace Telebirr;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class ApplyFabricToken
{
    private $baseUrl;
    private $fabricAppId;
    private $appSecret;
    private $logger;
    
    public function __construct($config, $logger = null)
    {
        $this->baseUrl = rtrim($config['api']['base_url'], '/');
        $this->fabricAppId = $config['credentials']['fabric_app_id'];
        $this->appSecret = $config['credentials']['app_secret'];
        $this->logger = $logger ?: $this->initLogger($config['logging']);
    }
    
    private function initLogger($loggingConfig)
    {
        $log = new Logger('telebirr');
        
        // Ensure log directory exists
        $logDir = dirname($loggingConfig['path']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        
        $log->pushHandler(new StreamHandler(
            $loggingConfig['path'],
            $this->getLogLevel($loggingConfig['level'])
        ));
        return $log;
    }
    
    private function getLogLevel($level)
    {
        $levels = [
            'DEBUG' => Logger::DEBUG,
            'INFO' => Logger::INFO,
            'NOTICE' => Logger::NOTICE,
            'WARNING' => Logger::WARNING,
            'ERROR' => Logger::ERROR,
        ];
        return $levels[$level] ?? Logger::INFO;
    }
    
    public function applyToken()
    {
        try {
            $this->logger->info("Applying for fabric token");
            
            $url = $this->baseUrl . "/payment/v1/token";
            $method = "POST";
            
            $headers = [
                "Content-Type: application/json",
                "X-APP-Key: " . $this->fabricAppId,
                "Accept: application/json",
                "User-Agent: Telebirr-PHP-Client/1.0"
            ];
            
            $payload = [
                "appSecret" => $this->appSecret
            ];
            
            $this->logger->debug("Request URL: " . $url);
            $this->logger->debug("Headers: " . json_encode($headers));
            $this->logger->debug("Payload: " . json_encode($payload));
            
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HEADER => false,
                CURLOPT_VERBOSE => true
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            $curlInfo = curl_getinfo($ch);

                        // Log the API call
            if (isset($this->apiLogger)) {
                $this->apiLogger->log($url, $method, $request, $response, $httpCode);
            }
            
            curl_close($ch);
            
            $this->logger->debug("HTTP Code: " . $httpCode);
            $this->logger->debug("cURL Info: " . json_encode($curlInfo));
            
            if ($curlError) {
                throw new \Exception("cURL Error: " . $curlError);
            }
            
            if (!$response) {
                throw new \Exception("Empty response from server");
            }
            
            $this->logger->debug("Raw Response: " . $response);
            
            $result = json_decode($response, true);
            
            if (!$result) {
                throw new \Exception("Invalid JSON response: " . substr($response, 0, 200));
            }
            
            // Check for errors
            if ($httpCode != 200) {
                $errorMsg = isset($result['errorMsg']) ? $result['errorMsg'] : 
                           (isset($result['message']) ? $result['message'] : 'Unknown error');
                throw new \Exception("HTTP Error {$httpCode}: " . $errorMsg);
            }
            
            // Check if token exists in response
            if (!isset($result['token'])) {
                throw new \Exception("No token in response: " . json_encode($result));
            }
            
            $this->logger->info("Fabric token obtained successfully");
            
            return $result;
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to apply fabric token: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getToken($forceRefresh = false)
    {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $cacheKey = 'telebirr_token_' . md5($this->fabricAppId);
        
        if (!$forceRefresh && isset($_SESSION[$cacheKey]) && isset($_SESSION[$cacheKey . '_expiry'])) {
            if (time() < $_SESSION[$cacheKey . '_expiry']) {
                $this->logger->debug("Using cached token from session");
                return $_SESSION[$cacheKey];
            }
        }
        
        try {
            $result = $this->applyToken();
            
            if (isset($result['token'])) {
                $token = $result['token'];
                
                // Store in session
                $_SESSION[$cacheKey] = $token;
                
                // Set expiry (subtract 5 minutes for safety)
                if (isset($result['expirationDate'])) {
                    $expiry = \DateTime::createFromFormat('YmdHis', $result['expirationDate']);
                    if ($expiry) {
                        $_SESSION[$cacheKey . '_expiry'] = $expiry->getTimestamp() - 300;
                    } else {
                        // Default 1 hour expiry
                        $_SESSION[$cacheKey . '_expiry'] = time() + 3600;
                    }
                } else {
                    $_SESSION[$cacheKey . '_expiry'] = time() + 3600;
                }
                
                return $token;
            }
            
            throw new \Exception("No token in response");
            
        } catch (\Exception $e) {
            $this->logger->error("Failed to get token: " . $e->getMessage());
            throw $e;
        }
    }
}