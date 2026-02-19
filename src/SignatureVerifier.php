<?php

namespace Telebirr;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

/**
 * Telebirr Signature Verifier
 */
class SignatureVerifier
{
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

    public function verify(array $params): bool
    {
        try {
            $signature = $params['sign'] ?? '';
            $signType = $params['sign_type'] ?? '';

            if (empty($signature) || empty($signType)) {
                $this->log("Missing signature or sign_type", 'ERROR');
                return false;
            }

            $canonicalString = $this->buildCanonicalString($params);

            $this->log("Verifying signature for string: " . $canonicalString, 'DEBUG');
            $this->log("Signature: " . $signature, 'DEBUG');

            return $this->verifySignature($canonicalString, $signature);
        } catch (\Exception $e) {
            $this->log("Verification Error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    private function buildCanonicalString(array $params): string
    {
        $fields = [];
        $fieldMap = [];

        foreach ($params as $key => $value) {
            if (in_array($key, self::$excludeFields, true)) continue;
            if (is_array($value)) $value = json_encode($value);
            $fields[] = $key;
            $fieldMap[$key] = (string)$value;
        }

        sort($fields, SORT_STRING);

        $parts = [];
        foreach ($fields as $key) {
            $parts[] = $key . '=' . $fieldMap[$key];
        }

        return implode('&', $parts);
    }

    private function verifySignature(string $data, string $signature): bool
    {
        try {
            $signatureBinary = $this->decodeSignature($signature);
            if ($signatureBinary === false) {
                $this->log("Failed to decode signature", 'ERROR');
                return false;
            }

            $publicKey = PublicKeyLoader::loadPublicKey($this->publicKey);

            if (!$publicKey instanceof RSA\PublicKey) {
                $this->log("Invalid key type", 'ERROR');
                return false;
            }

            $publicKey = $publicKey
                ->withPadding(RSA::SIGNATURE_PSS)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            return $publicKey->verify($data, $signatureBinary);
        } catch (\Exception $e) {
            $this->log("Verification error: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    private function decodeSignature(string $signature)
    {
        $attempts = [
            $signature,
            str_replace(' ', '+', $signature),
            urldecode($signature),
            str_replace(' ', '+', urldecode($signature)),
        ];

        foreach ($attempts as $attempt) {
            $decoded = base64_decode($attempt, true);
            if ($decoded !== false && strlen($decoded) > 0) return $decoded;

            $paddingNeeded = 4 - (strlen($attempt) % 4);
            if ($paddingNeeded !== 4) {
                $attemptWithPadding = $attempt . str_repeat('=', $paddingNeeded);
                $decoded = base64_decode($attemptWithPadding, true);
                if ($decoded !== false && strlen($decoded) > 0) return $decoded;
            }
        }

        return false;
    }

    private function log($message, $level = 'INFO')
    {
        if ($this->logger && method_exists($this->logger, strtolower($level))) {
            $this->logger->{strtolower($level)}($message);
        }
    }
}
