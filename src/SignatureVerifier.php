<?php
namespace Telebirr;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

/**
 * Telebirr Signature Verifier
 * 
 * This class verifies signatures from Telebirr's return URLs and notifications.
 */
class SignatureVerifier
{
    /**
     * Fields excluded from signature calculation
     */
    private static array $excludeFields = [
        'sign',
        'sign_type',
    ];

    private string $publicKey;
    private $logger;

    public function __construct(string $publicKey, $logger = null)
    {
        $this->publicKey = $publicKey;
        $this->logger = $logger;
    }

    /**
     * Verify Telebirr signature from return URL or notification
     * 
     * @param array $params All parameters (including sign and sign_type)
     * @return bool True if signature is valid, false otherwise
     */
    public function verify(array $params): bool
    {
        try {
            $signature = $params['sign'] ?? '';
            $signType = $params['sign_type'] ?? '';

            if (empty($signature) || empty($signType)) {
                $this->log("Missing signature or sign_type", 'ERROR');
                return false;
            }

            // Build canonical string (same process as signing)
            $canonicalString = $this->buildCanonicalString($params);
            
            $this->log("Verifying signature for string: " . $canonicalString, 'DEBUG');
            $this->log("Signature: " . $signature, 'DEBUG');

            // Verify signature
            return $this->verifySignature($canonicalString, $signature);
            
        } catch (\Exception $e) {
            $this->log("Verification Error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Build canonical string from parameters (same process as signing)
     * 
     * @param array $params All parameters
     * @return string Canonical string: "key1=value1&key2=value2&..."
     */
    private function buildCanonicalString(array $params): string
    {
        $fields = [];
        $fieldMap = [];

        // Collect all fields except excluded ones
        foreach ($params as $key => $value) {
            if (in_array($key, self::$excludeFields, true)) {
                continue;
            }
            // Handle array values (convert to JSON string if needed)
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $fields[] = $key;
            $fieldMap[$key] = (string)$value;
        }

        // Sort fields alphabetically
        sort($fields, SORT_STRING);

        // Build canonical string
        $parts = [];
        foreach ($fields as $key) {
            $parts[] = $key . '=' . $fieldMap[$key];
        }

        return implode('&', $parts);
    }

    /**
     * Verify signature using RSA-PSS SHA256
     * 
     * @param string $data The canonical string that was signed
     * @param string $signature Base64-encoded signature
     * @return bool True if signature is valid
     */
    private function verifySignature(string $data, string $signature): bool
    {
        try {
            // Decode signature (handle URL encoding issues)
            $signatureBinary = $this->decodeSignature($signature);
            
            if ($signatureBinary === false) {
                $this->log("Failed to decode signature", 'ERROR');
                return false;
            }

            // Load public key
            $pub = PublicKeyLoader::load($this->publicKey);
            
            // Configure for RSA-PSS with SHA256
            $pub = $pub->withPadding(RSA::SIGNATURE_PSS)
                ->withHash('sha256')
                ->withMGFHash('sha256')
                ->withSaltLength(32);

            // Verify signature
            return $pub->verify($data, $signatureBinary);
            
        } catch (\Exception $e) {
            $this->log("Verification error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Decode base64 signature, handling URL encoding issues
     * 
     * @param string $signature
     * @return string|false
     */
    private function decodeSignature(string $signature)
    {
        // Try different decoding approaches
        $attempts = [
            $signature,  // As-is
            str_replace(' ', '+', $signature),  // Replace spaces with +
            urldecode($signature),  // URL decode
            str_replace(' ', '+', urldecode($signature)),  // URL decode then fix spaces
        ];

        foreach ($attempts as $attempt) {
            $decoded = base64_decode($attempt, true);
            if ($decoded !== false && strlen($decoded) > 0) {
                return $decoded;
            }

            // Try adding padding if needed
            $paddingNeeded = 4 - (strlen($attempt) % 4);
            if ($paddingNeeded !== 4) {
                $attemptWithPadding = $attempt . str_repeat('=', $paddingNeeded);
                $decoded = base64_decode($attemptWithPadding, true);
                if ($decoded !== false && strlen($decoded) > 0) {
                    return $decoded;
                }
            }
        }

        return false;
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