<?php

namespace Telebirr;

use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;

/**
 * Request Signature Handler - Telebirr H5 C2B Request Signature Process
 */
class Signer
{
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

    public function signRequestObject(array $requestObject): string
    {
        $fields = [];
        $fieldMap = [];

        // Main request parameters
        foreach ($requestObject as $key => $value) {
            if (in_array($key, self::$excludeFields, true)) continue;
            if (!is_array($value) && !is_object($value)) {
                $fields[] = $key;
                $fieldMap[$key] = $value;
            }
        }

        // biz_content parameters
        if (isset($requestObject['biz_content']) && is_array($requestObject['biz_content'])) {
            foreach ($requestObject['biz_content'] as $key => $value) {
                if (in_array($key, self::$excludeFields, true)) continue;
                if (!is_array($value) && !is_object($value)) {
                    $fields[] = $key;
                    $fieldMap[$key] = $value;
                }
            }
        }

        sort($fields, SORT_STRING);

        $parts = [];
        foreach ($fields as $key) {
            $parts[] = $key . '=' . $fieldMap[$key];
        }

        $signOriginStr = implode('&', $parts);
        $this->log("Signature String: " . $signOriginStr, 'DEBUG');

        return $this->signString($signOriginStr);
    }

    public function signString(string $text): string
    {
        try {
            $privateKey = PublicKeyLoader::loadPrivateKey($this->privateKey);

            if (!$privateKey instanceof RSA\PrivateKey) {
                throw new \RuntimeException("Invalid key type: Expected RSA private key");
            }

            // Set padding and hash
            $privateKey = $privateKey
                ->withPadding(RSA::SIGNATURE_PSS)
                ->withHash('sha256')
                ->withMGFHash('sha256');

            $signature = $privateKey->sign($text);

            $this->log("Generated Signature: " . base64_encode($signature), 'DEBUG');

            return base64_encode($signature);
        } catch (\Exception $e) {
            $this->log("Signing Error: " . $e->getMessage(), 'ERROR');
            throw new \RuntimeException("Failed to sign string: " . $e->getMessage());
        }
    }

    public function generateRawRequestSignature(array $params): string
    {
        ksort($params);

        $signParts = [];
        foreach ($params as $key => $value) {
            if (!in_array($key, ['sign', 'sign_type'], true)) {
                $signParts[] = $key . '=' . $value;
            }
        }
        $signString = implode('&', $signParts);

        return $this->signString($signString);
    }

    private function log($message, $level = 'INFO')
    {
        if ($this->logger && method_exists($this->logger, strtolower($level))) {
            $this->logger->{strtolower($level)}($message);
        }
    }
}
