<?php
declare(strict_types=1);
namespace Binprof\Services;

final class Crypto
{
    private array $keyring;

    public function __construct(string $file)
    {
        if (!is_file($file) || ($contents = @file_get_contents($file)) === false) {
            throw new \RuntimeException('Keyring missing');
        }
        $keyring = json_decode($contents,true,16,JSON_THROW_ON_ERROR);
        $active = $keyring['active'] ?? null;
        if ((!is_string($active) && !is_int($active)) || (string) $active === '' || str_contains((string) $active,':')
            || !is_array($keyring['keys'] ?? null) || !is_string($keyring['keys'][$active] ?? null)
            || !is_string($keyring['digest'] ?? null)) {
            throw new \RuntimeException('Invalid keyring');
        }
        $this->key($keyring['keys'][$active]);
        $this->key($keyring['digest']);
        $this->keyring = $keyring;
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
        [$version,$encoded] = array_pad(explode(':',$envelope,2),2,null);
        $key = $this->keyring['keys'][$version] ?? null;
        if ($encoded === null || !is_string($key)) {
            throw new \RuntimeException('Invalid encrypted envelope or unavailable key');
        }
        $bytes = sodium_base642bin($encoded, SODIUM_BASE64_VARIANT_ORIGINAL);
        $size = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        if (strlen($bytes) < $size + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
            throw new \RuntimeException('Invalid encrypted content length');
        }
        $value = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(substr($bytes, $size), $context,
            substr($bytes, 0, $size), $this->key($key));
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
