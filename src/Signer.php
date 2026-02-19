<?php
namespace Telebirr;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

/**
 * Request Signature Handler - Telebirr H5 C2B Request Signature Process
 *
 * This class implements the signature generation process for Telebirr API requests:
 *
 * Signature Process:
 * 1. Collect all fields from the request object (excluding excluded fields)
 * 2. Flatten fields from biz_content into the main field list
 * 3. Sort all fields alphabetically (ASCII order)
 * 4. Build canonical string: "key1=value1&key2=value2&..."
 * 5. Sign the canonical string using RSA-PSS SHA256
 * 6. Return base64-encoded signature
 */
class Signer
{
    /**
     * Fields that are excluded from signature calculation.
     * 
     * Note: While 'biz_content' itself is excluded, all fields WITHIN biz_content
     * are included in the signature (they are flattened into the main field list).
     *
     * @var string[]
     */
    private static array $excludeFields = [
        'sign',
        'sign_type',
        'header',
        'refund_info',
        'openType',
        'raw_request',
        'biz_content',
    ];

    private string $privateKey;
    private $logger;

    public function __construct(string $privateKey, $logger = null)
    {
        $this->privateKey = $privateKey;
        $this->logger = $logger;
    }

    /**
     * Build the canonical string from request object and sign it with RSA-PSS SHA256.
     *
     * Process:
     * 1. Collect all top-level fields (excluding excluded fields)
     * 2. Flatten and collect all fields from biz_content (excluding excluded fields)
     * 3. Sort all collected fields alphabetically
     * 4. Build canonical string: "key1=value1&key2=value2&..."
     * 5. Sign the canonical string using RSA-PSS SHA256
     * 6. Return base64-encoded signature
     *
     * @param array $requestObject The request object to sign (may contain biz_content)
     * @return string Base64-encoded RSA-PSS SHA256 signature
     */
    public function signRequestObject(array $requestObject): string
    {
        $fields   = [];
        $fieldMap = [];

        // Add main request parameters (excluding biz_content and excluded fields)
        foreach ($requestObject as $key => $value) {
            if (in_array($key, self::$excludeFields, true)) {
                continue;
            }
            if (!is_array($value) && !is_object($value)) {
                $fields[] = $key;
                $fieldMap[$key] = $value;
            }
        }

        // Add biz_content parameters (flattened)
        if (isset($requestObject['biz_content']) && is_array($requestObject['biz_content'])) {
            foreach ($requestObject['biz_content'] as $key => $value) {
                if (in_array($key, self::$excludeFields, true)) {
                    continue;
                }
                if (!is_array($value) && !is_object($value)) {
                    $fields[] = $key;
                    $fieldMap[$key] = $value;
                }
            }
        }

        // Sort fields alphabetically
        sort($fields, SORT_STRING);

        // Build canonical string
        $parts = [];
        foreach ($fields as $key) {
            $parts[] = $key . '=' . $fieldMap[$key];
        }

        $signOriginStr = implode('&', $parts);
        
        $this->log("Signature String: " . $signOriginStr, 'DEBUG');

        return $this->signString($signOriginStr);
    }

    /**
     * Sign a string using RSA-PSS SHA256.
     *
     * Signature Algorithm: RSA-PSS with SHA256
     * - Padding: PSS (Probabilistic Signature Scheme)
     * - Hash: SHA256
     * - MGF: MGF1 with SHA256
     * - Salt Length: 32 bytes (equal to hash length)
     *
     * @param string $text The canonical string to sign
     * @return string Base64-encoded signature
     * @throws \RuntimeException if signing fails
     */
    public function signString(string $text): string
    {
        try {
            // Load private key
            $private = PublicKeyLoader::load($this->privateKey);
            
            // Configure for RSA-PSS with SHA256
            $private = $private->withPadding(RSA::SIGNATURE_PSS)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->withSaltLength(32);

            // Sign the text
            $signature = $private->sign($text);
            
            $this->log("Generated Signature: " . base64_encode($signature), 'DEBUG');

            return base64_encode($signature);
            
        } catch (\Exception $e) {
            $this->log("Signing Error: " . $e->getMessage(), 'ERROR');
            throw new \RuntimeException("Failed to sign string: " . $e->getMessage());
        }
    }

    /**
     * Generate signature for raw request (checkout URL)
     * 
     * @param array $params
     * @return string
     */
    public function generateRawRequestSignature(array $params): string
    {
        // Sort parameters alphabetically
        ksort($params);
        
        // Build signature string (exclude sign and sign_type)
        $signParts = [];
        foreach ($params as $key => $value) {
            if (!in_array($key, ['sign', 'sign_type'])) {
                $signParts[] = $key . '=' . $value;
            }
        }
        $signString = implode('&', $signParts);
        
        return $this->signString($signString);
    }

    /**
     * Log message
     */
    private function log($message, $level = 'INFO')
    {
        if ($this->logger) {
            $this->logger->$level($message);
        }
    }
}