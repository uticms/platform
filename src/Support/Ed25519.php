<?php

namespace Uticms\Platform\Support;

use RuntimeException;
use SodiumException;

final class Ed25519
{
    /**
     * @return array{public_key: string, secret_key: string}
     */
    public static function generateKeyPair(): array
    {
        self::assertAvailable();

        $keyPair = sodium_crypto_sign_keypair();

        return [
            'public_key' => sodium_crypto_sign_publickey($keyPair),
            'secret_key' => sodium_crypto_sign_secretkey($keyPair),
        ];
    }

    public static function sign(string $message, string $secretKey): string
    {
        self::assertAvailable();

        $secretKey = self::decodeKey($secretKey, SODIUM_CRYPTO_SIGN_SECRETKEYBYTES, 'secret key');

        try {
            return sodium_crypto_sign_detached($message, $secretKey);
        } catch (SodiumException $exception) {
            throw new RuntimeException('Ed25519 signing failed.', 0, $exception);
        }
    }

    public static function verify(string $message, string $signature, string $publicKey): bool
    {
        self::assertAvailable();

        $publicKey = self::decodeKey($publicKey, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, 'public key');
        $signature = self::decodeBinary($signature);

        if ($signature === false || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        try {
            return sodium_crypto_sign_verify_detached($signature, $message, $publicKey);
        } catch (SodiumException) {
            return false;
        }
    }

    public static function encodeBase64(string $binary): string
    {
        return base64_encode($binary);
    }

    public static function decodeBase64(string $encoded): string|false
    {
        return self::decodeBinary($encoded);
    }

    private static function decodeKey(string $key, int $expectedLength, string $label): string
    {
        $binary = self::decodeBinary($key);

        if ($binary === false || strlen($binary) !== $expectedLength) {
            throw new RuntimeException("Invalid Ed25519 {$label}.");
        }

        return $binary;
    }

    private static function decodeBinary(string $value): string|false
    {
        $value = trim($value);

        if ($value === '') {
            return false;
        }

        $decoded = base64_decode($value, true);

        return $decoded === false ? false : $decoded;
    }

    private static function assertAvailable(): void
    {
        if (! extension_loaded('sodium') && ! function_exists('sodium_crypto_sign_keypair')) {
            throw new RuntimeException('The sodium extension is required.');
        }
    }
}
