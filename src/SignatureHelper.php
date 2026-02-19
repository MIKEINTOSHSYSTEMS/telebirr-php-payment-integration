<?php
namespace Telebirr;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

/**
 * Signature Helper for Telebirr API
 * Handles request signing and verification
 */
class SignatureHelper
{
    private string $privateKey;
    private ?string $publicKey;
    private $logger;

    private array $excludeFields = [
        'sign',
        'sign_type',
        'header',
        'refund_info',
        'openType',
        'raw_request',
        'wallet_reference_data'
    ];

    public function __construct(string $privateKey, ?string $publicKey = null, $logger = null)
    {
        $this->privateKey = $privateKey;
        $this->publicKey = $publicKey;
        $this->logger = $logger;
    }

    /**
     * Sign request object according to Telebirr spec
     */
    public function signRequest(array $requestObject): string
    {
        $params = [];

        // Main request parameters
        foreach ($requestObject as $key => $value) {
            if ($key === 'biz_content' || in_array($key, $this->excludeFields, true)) continue;
            if (!is_array($value) && !is_object($value)) {
                $params[$key] = $value;
            }
        }

        // biz_content parameters
        if (isset($requestObject['biz_content']) && is_array($requestObject['biz_content'])) {
            foreach ($requestObject['biz_content'] as $key => $value) {
                if (!in_array($key, $this->excludeFields, true) && !is_array($value) && !is_object($value)) {
                    if ($value !== null && $value !== '') {
                        $params[$key] = $value;
                    }
                }
            }
        }

        ksort($params);

        $signParts = [];
        foreach ($params as $key => $value) {
            $signParts[] = $key . '=' . (string)$value;
        }

        $signString = implode('&', $signParts);

        $this->log("Signature String: $signString", 'DEBUG');

        return $this->signWithRSA($signString);
    }

    /**
     * Sign string using RSA PSS + SHA256
     */
    public function signWithRSA(string $data): string
    {
        try {
            $privateKey = PublicKeyLoader::loadPrivateKey($this->privateKey);

            if (!$privateKey instanceof RSA\PrivateKey) {
                throw new \RuntimeException("Invalid private key provided");
            }

            // Set padding and hash
            $privateKey = $privateKey
                ->withPadding(RSA::SIGNATURE_PSS)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            $signature = $privateKey->sign($data);

            $this->log("Generated Signature: " . base64_encode($signature), 'DEBUG');

            return base64_encode($signature);
        } catch (\Exception $e) {
            $this->log("RSA Sign Error: " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Verify a signature with RSA PSS + SHA256
     */
    public function verifySignature(string $data, string $signature): bool
    {
        if (!$this->publicKey) {
            $this->log("Public key not set for verification", 'ERROR');
            return false;
        }

        try {
            $publicKey = PublicKeyLoader::loadPublicKey($this->publicKey);

            if (!$publicKey instanceof RSA\PublicKey) {
                $this->log("Invalid public key provided", 'ERROR');
                return false;
            }

            $publicKey = $publicKey
                ->withPadding(RSA::SIGNATURE_PSS)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            return $publicKey->verify($data, base64_decode($signature));
        } catch (\Exception $e) {
            $this->log("Verification Error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Generate signature for raw request array
     */
    public function generateRawRequestSignature(array $params): string
    {
        ksort($params);
        $signParts = [];
        foreach ($params as $key => $value) {
            if (!in_array($key, ['sign', 'sign_type'], true)) {
                $signParts[] = $key . '=' . (string)$value;
            }
        }
        $signString = implode('&', $signParts);
        return $this->signWithRSA($signString);
    }

    /**
     * Simple logging
     */
    private function log(string $message, string $level = 'INFO'): void
    {
        if ($this->logger && method_exists($this->logger, strtolower($level))) {
            $this->logger->{strtolower($level)}($message);
        } else {
            error_log("[Telebirr][$level] $message");
        }
    }
}
