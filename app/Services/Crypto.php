<?php
declare(strict_types=1);
namespace Binprof\Services;

final class Crypto
{
    private array $keyring;

    public function __construct(string $file)
    {
        if (!is_file($file)) {
            throw new \RuntimeException('Keyring missing');
        }
        $this->keyring = json_decode((string) file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        $this->key($this->keyring['keys'][$this->keyring['active']]);
        $this->key($this->keyring['digest']);
    }

    private function key(string $encoded): string
    {
        $key = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_ORIGINAL);
        if (strlen($key) !== 32) {
            throw new \RuntimeException('Invalid key length');
        }
        return $key;
    }

    public function seal(mixed $value, string $context): string
    {
        return $this->encrypt(json_encode($value, JSON_THROW_ON_ERROR), $context);
    }

    public function open(string $envelope, string $context): mixed
    {
        return json_decode($this->decrypt($envelope, $context), true, 32, JSON_THROW_ON_ERROR);
    }

    public function encrypt(string $value, string $context): string
    {
        $version = $this->keyring['active'];
        $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
        $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($value, $context, $nonce,
            $this->key($this->keyring['keys'][$version]));
        return $version . ':' . sodium_bin2base64($nonce . $cipher, SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    public function decrypt(string $envelope, string $context): string
    {
        [$version, $encoded] = explode(':', $envelope, 2);
        $bytes = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_ORIGINAL);
        $size = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $value = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($bytes, $size), $context,
            substr($bytes, 0, $size), $this->key($this->keyring['keys'][$version]));
        if ($value === false) {
            throw new \RuntimeException('Unable to authenticate stored content');
        }
        return $value;
    }

    public function identifier(int $type, string $normalized): string
    {
        return sodium_crypto_generichash($type . ':' . $normalized, $this->key($this->keyring['digest']), 32);
    }
}
