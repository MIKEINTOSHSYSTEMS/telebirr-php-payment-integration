<?php
namespace Telebirr;

use phpseclib\Crypt\RSA;

/**
 * Signature Helper for Telebirr API
 * Handles request signing and verification
 */
class SignatureHelper
{
    private $privateKey;
    private $publicKey;
    private $logger;
    
    // Fields excluded from signature (as per Telebirr documentation)
    private $excludeFields = [
        'sign',
        'sign_type',
        'header',
        'refund_info',
        'openType',
        'raw_request',
        'wallet_reference_data'
    ];
    
    public function __construct($privateKey, $publicKey = null, $logger = null)
    {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
        $this->logger = $logger;
    }
    
    /**
     * Sign request object exactly as per Telebirr documentation
     * 
     * @param array $requestObject The request data to sign
     * @return string Base64 encoded signature
     */
    public function signRequest($requestObject)
    {
        try {
            // Collect all parameters to be signed
            $params = [];
            
            // Add main request parameters (including sign_type but excluding biz_content)
            foreach ($requestObject as $key => $value) {
                if ($key === 'biz_content' || in_array($key, $this->excludeFields)) {
                    continue;
                }
                if (!is_array($value) && !is_object($value)) {
                    // Include sign_type in signature
                    $params[$key] = $value;
                }
            }
            
            // Add biz_content parameters
            if (isset($requestObject['biz_content']) && is_array($requestObject['biz_content'])) {
                foreach ($requestObject['biz_content'] as $key => $value) {
                    if (!in_array($key, $this->excludeFields) && !is_array($value) && !is_object($value)) {
                        // Skip empty values
                        if ($value !== null && $value !== '') {
                            $params[$key] = $value;
                        }
                    }
                }
            }
            
            // Sort parameters alphabetically by key
            ksort($params);
            
            // Build signature string: key1=value1&key2=value2&...
            $signParts = [];
            foreach ($params as $key => $value) {
                // Ensure value is string
                $value = (string)$value;
                $signParts[] = $key . '=' . $value;
            }
            $signString = implode('&', $signParts);
            
            $this->log("Signature String: " . $signString, 'DEBUG');
            
            // Sign the string
            $signature = $this->signWithRSA($signString);
            
            $this->log("Generated Signature: " . $signature, 'DEBUG');
            
            return $signature;
            
        } catch (\Exception $e) {
            $this->log("Signature Error: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Sign raw string with RSA private key using SHA256withRSA
     * 
     * @param string $data Data to sign
     * @return string Base64 encoded signature
     */
    public function signWithRSA($data)
    {
        try {
            // Initialize RSA
            $rsa = new RSA();
            
            // Clean private key
            $privateKey = $this->cleanPrivateKey($this->privateKey);
            
            // Load private key
            if (!$rsa->loadKey($privateKey)) {
                throw new \Exception("Failed to load private key");
            }
            
            // Set encryption mode and hash
            $rsa->setEncryptionMode(RSA::ENCRYPTION_PKCS1);
            $rsa->setSignatureMode(RSA::SIGNATURE_PKCS1);
            $rsa->setHash("sha256");
            $rsa->setMGFHash("sha256");
            
            // Sign the data
            $signature = $rsa->sign($data);
            
            // Return base64 encoded signature
            return base64_encode($signature);
            
        } catch (\Exception $e) {
            $this->log("RSA Sign Error: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
    
    /**
     * Clean private key by removing headers, footers, and whitespace
     * 
     * @param string $key
     * @return string
     */
    private function cleanPrivateKey($key)
    {
        // Remove headers and footers
        $key = preg_replace('/-----BEGIN PRIVATE KEY-----/', '', $key);
        $key = preg_replace('/-----END PRIVATE KEY-----/', '', $key);
        $key = preg_replace('/-----BEGIN RSA PRIVATE KEY-----/', '', $key);
        $key = preg_replace('/-----END RSA PRIVATE KEY-----/', '', $key);
        
        // Remove all whitespace
        $key = preg_replace('/\s+/', '', $key);
        
        // Reconstruct with proper PEM format
        $key = chunk_split($key, 64, "\n");
        $key = "-----BEGIN PRIVATE KEY-----\n" . $key . "-----END PRIVATE KEY-----";
        
        return $key;
    }
    
    /**
     * Generate signature for raw request (checkout URL)
     * 
     * @param array $params
     * @return string
     */
    public function generateRawRequestSignature($params)
    {
        // Sort parameters alphabetically
        ksort($params);
        
        // Build signature string
        $signParts = [];
        foreach ($params as $key => $value) {
            if (!in_array($key, ['sign', 'sign_type'])) {
                $signParts[] = $key . '=' . $value;
            }
        }
        $signString = implode('&', $signParts);
        
        return $this->signWithRSA($signString);
    }
    
    /**
     * Verify signature
     * 
     * @param string $data Original data
     * @param string $signature Base64 encoded signature
     * @return bool
     */
    public function verifySignature($data, $signature)
    {
        try {
            $rsa = new RSA();
            
            // Clean public key
            $publicKey = $this->cleanPublicKey($this->publicKey);
            
            // Load public key
            if (!$rsa->loadKey($publicKey)) {
                throw new \Exception("Failed to load public key");
            }
            
            // Set verification mode
            $rsa->setSignatureMode(RSA::SIGNATURE_PKCS1);
            $rsa->setHash("sha256");
            $rsa->setMGFHash("sha256");
            
            // Verify signature
            return $rsa->verify($data, base64_decode($signature));
            
        } catch (\Exception $e) {
            $this->log("Verification Error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
    
    /**
     * Clean public key
     * 
     * @param string $key
     * @return string
     */
    private function cleanPublicKey($key)
    {
        // Remove headers and footers
        $key = preg_replace('/-----BEGIN PUBLIC KEY-----/', '', $key);
        $key = preg_replace('/-----END PUBLIC KEY-----/', '', $key);
        
        // Remove all whitespace
        $key = preg_replace('/\s+/', '', $key);
        
        // Reconstruct with proper PEM format
        $key = chunk_split($key, 64, "\n");
        $key = "-----BEGIN PUBLIC KEY-----\n" . $key . "-----END PUBLIC KEY-----";
        
        return $key;
    }
    
    /**
     * Log message
     */
    private function log($message, $level = 'INFO')
    {
        if ($this->logger) {
            $this->logger->$level($message);
        } else {
            error_log("[Telebirr][$level] $message");
        }
    }
}