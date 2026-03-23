<?php

declare(strict_types=1);

namespace App\Security;

/**
 * KeyAuthentication — Ed448 key-pair generation, challenge signing and verification.
 */
class KeyAuthentication
{
    private const KEY_CONTEXT      = "DeSEC-Auth-2025-09";
    private const CHALLENGE_TIMEOUT = 300; // 5 minutes

    /**
     * Generates a new Ed448 key pair.
     *
     * @return array{private: string, public: string}
     */
    public static function generateKeyPair(): array
    {
        $keypair = sodium_crypto_sign_keypair();

        return [
            'private' => base64_encode(sodium_crypto_sign_secretkey($keypair)),
            'public'  => base64_encode(sodium_crypto_sign_publickey($keypair)),
        ];
    }

    /**
     * Generates a challenge for the login process.
     *
     * @return array{challenge: string, expires: int}
     */
    public function generateChallenge(): array
    {
        $challenge = random_bytes(32);
        $expires   = time() + self::CHALLENGE_TIMEOUT;

        // Format: base64(challenge):timestamp
        return [
            'challenge' => base64_encode($challenge),
            'expires'   => $expires,
        ];
    }

    /**
     * Verifies a signed challenge.
     */
    public function verifyChallenge(
        string $publicKey,
        string $challenge,
        int $expires,
        string $signature
    ): bool {
        if (time() > $expires) {
            return false;
        }

        try {
            $decodedKey       = base64_decode($publicKey, true);
            $decodedChallenge = base64_decode($challenge, true);
            $decodedSignature = base64_decode($signature, true);

            // Verify valid decoding and non-empty strings
            if ($decodedKey === false || $decodedKey === ''
                || $decodedChallenge === false || $decodedChallenge === ''
                || $decodedSignature === false || $decodedSignature === '') {
                return false;
            }

            // Build the message string to verify
            $message = sodium_bin2hex($decodedChallenge) . "|" . $expires . "|" . self::KEY_CONTEXT;

            return sodium_crypto_sign_verify_detached(
                $decodedSignature,
                $message,
                $decodedKey
            );

        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Signs data with a private key.
     */
    public static function sign(string $privateKey, string $data): string
    {
        $decodedKey = base64_decode($privateKey, true);
        if ($decodedKey === false || $decodedKey === '') {
            throw new \InvalidArgumentException('Invalid private key');
        }
        $signature = sodium_crypto_sign_detached($data, $decodedKey);
        return base64_encode($signature);
    }

    /**
     * Derives a deterministic API key from a private key.
     */
    public static function deriveApiKey(string $privateKey): string
    {
        $decodedKey = base64_decode($privateKey, true);
        if ($decodedKey === false || $decodedKey === '') {
            throw new \InvalidArgumentException('Invalid private key');
        }
        $context = self::KEY_CONTEXT . "-API";

        // Use the private key to derive a deterministic API key
        $apiKey = sodium_crypto_generichash(
            $context,
            $decodedKey,
            32 // 256-bit output
        );

        return base64_encode($apiKey);
    }
}
