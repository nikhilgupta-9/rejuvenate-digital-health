<?php

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * AbdmCrypto — ABDM Fidelius End-to-End Encryption Protocol.
 *
 * Implements ABDM Health Data Flow Cryptography:
 *   1. Ephemeral Curve25519 (X25519) ECDH Key Exchange
 *   2. 32-byte Cryptographic Nonce Exchange & Bitwise XOR
 *   3. 20-byte Salt & 12-byte IV Slicing
 *   4. HKDF-SHA256 Key Derivation for AES-256 Key
 *   5. AES-256-GCM Authenticated Encryption with 16-byte Tag
 *
 * Handles both HIP (encrypt and push to HIU) and HIU (decrypt incoming push).
 */
class AbdmCrypto
{
    /**
     * Encrypt a FHIR R4 Bundle for an ABDM HIU.
     *
     * @param string $fhirJson Raw JSON string of the FHIR Bundle
     * @param string $hiuPublicKeyB64 Base64-encoded HIU public key
     * @param string $hiuNonceB64 Base64-encoded HIU nonce (32 bytes)
     * @return array{
     *   encryptedData: string,
     *   checksum: string,
     *   keyMaterial: array
     * }
     */
    public static function encryptBundle(string $fhirJson, string $hiuPublicKeyB64, string $hiuNonceB64): array
    {
        $hiuPubKey = self::normalizePublicKey(base64_decode($hiuPublicKeyB64));
        $hiuNonce  = base64_decode($hiuNonceB64);

        if (strlen($hiuPubKey) !== 32) {
            throw new InvalidArgumentException("HIU public key must be 32 bytes (got " . strlen($hiuPubKey) . " bytes)");
        }
        if (strlen($hiuNonce) !== 32) {
            throw new InvalidArgumentException("HIU nonce must be 32 bytes (got " . strlen($hiuNonce) . " bytes)");
        }

        // Generate ephemeral keypair for HIP sender
        $keypair    = sodium_crypto_box_keypair();
        $senderPriv = sodium_crypto_box_secretkey($keypair);
        $senderPub  = sodium_crypto_box_publickey($keypair);
        $senderNonce = random_bytes(32);

        // ECDH Scalarmult -> 32-byte shared secret
        $sharedSecret = sodium_crypto_scalarmult($senderPriv, $hiuPubKey);

        // Nonce XOR & Slicing: 20 bytes salt, 12 bytes IV
        $xorNonce = $senderNonce ^ $hiuNonce;
        $salt     = substr($xorNonce, 0, 20);
        $iv       = substr($xorNonce, 20, 12);

        // HKDF-SHA256 key derivation -> 32-byte AES key
        $aesKey = hash_hkdf('sha256', $sharedSecret, 32, '', $salt);

        // AES-256-GCM encryption
        $tag = '';
        $ciphertext = openssl_encrypt($fhirJson, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false) {
            throw new RuntimeException("AES-256-GCM encryption failed: " . openssl_error_string());
        }

        // Payload format: base64(ciphertext . tag)
        $encryptedPayload = base64_encode($ciphertext . $tag);
        $checksum = md5($encryptedPayload);

        return [
            'encryptedData' => $encryptedPayload,
            'checksum'      => $checksum,
            'keyMaterial'   => [
                'cryptoAlg'   => 'ECDH',
                'curve'       => 'Curve25519',
                'dhPublicKey' => [
                    'expiry'     => gmdate('Y-m-d\TH:i:s.000\Z', strtotime('+1 day')),
                    'parameters' => 'Curve25519/32byte',
                    'keyValue'   => base64_encode($senderPub),
                ],
                'nonce'       => base64_encode($senderNonce),
            ]
        ];
    }

    /**
     * Decrypt an ABDM encrypted payload received from a HIP or HIU.
     *
     * @param string $encryptedDataB64 Base64-encoded (ciphertext . tag)
     * @param string $senderPublicKeyB64 Base64-encoded sender public key
     * @param string $senderNonceB64 Base64-encoded sender nonce (32 bytes)
     * @param string $myPrivateKeyB64 Base64-encoded receiver private key
     * @param string $myNonceB64 Base64-encoded receiver nonce (32 bytes)
     * @return string Plaintext FHIR JSON
     */
    public static function decryptBundle(
        string $encryptedDataB64,
        string $senderPublicKeyB64,
        string $senderNonceB64,
        string $myPrivateKeyB64,
        string $myNonceB64
    ): string {
        $senderPubKey = self::normalizePublicKey(base64_decode($senderPublicKeyB64));
        $senderNonce  = base64_decode($senderNonceB64);
        $myPrivKey    = base64_decode($myPrivateKeyB64);
        $myNonce      = base64_decode($myNonceB64);

        $sharedSecret = sodium_crypto_scalarmult($myPrivKey, $senderPubKey);

        $xorNonce = $senderNonce ^ $myNonce;
        $salt     = substr($xorNonce, 0, 20);
        $iv       = substr($xorNonce, 20, 12);

        $aesKey = hash_hkdf('sha256', $sharedSecret, 32, '', $salt);

        $raw = base64_decode($encryptedDataB64);
        if (strlen($raw) < 16) {
            throw new InvalidArgumentException("Encrypted data is too short to contain an authentication tag");
        }

        $tag    = substr($raw, -16);
        $cipher = substr($raw, 0, -16);

        $plaintext = openssl_decrypt($cipher, 'aes-256-gcm', $aesKey, OPENSSL_RAW_DATA, $iv, $tag);
        if ($plaintext === false) {
            throw new RuntimeException("Decryption or authentication tag verification failed");
        }

        return $plaintext;
    }

    /**
     * Strips ASN.1 / SubjectPublicKeyInfo prefix if present (common in BouncyCastle 44-byte exports).
     */
    private static function normalizePublicKey(string $keyBytes): string
    {
        if (strlen($keyBytes) === 44) {
            return substr($keyBytes, -32);
        }
        return $keyBytes;
    }
}
