<?php

/**
 * Moves Code | TOTP
 *
 * Gera, valida e protege segredos de autenticação TOTP.
 *
 * @author Djalma Martins
 * @package Moves\Support
 */

namespace Moves\Support;

use RuntimeException;

class Totp
{
    /** Gera um segredo TOTP compatível com RFC 6238. */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    /** Criptografa o segredo usando uma chave de 32 bytes configurada no ambiente. */
    public function encrypt(string $secret): string
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, 'Moves Code TOTP v1');
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('TOTP encryption is unavailable.');
        }

        return base64_encode("\x01" . $nonce . $tag . $ciphertext);
    }

    /** Descriptografa um segredo previamente protegido. */
    public function decrypt(string $encrypted): string
    {
        $payload = base64_decode($encrypted, true);
        if (!is_string($payload) || strlen($payload) <= 29 || ord($payload[0]) !== 1) {
            throw new RuntimeException('TOTP secret is unavailable.');
        }

        $nonce = substr($payload, 1, 12);
        $tag = substr($payload, 13, 16);
        $plain = openssl_decrypt(substr($payload, 29), 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $nonce, $tag, 'Moves Code TOTP v1');
        if (!is_string($plain)) {
            throw new RuntimeException('TOTP secret is unavailable.');
        }

        return $plain;
    }

    /** Valida um código de seis dígitos e retorna seu contador para replay-prevention. */
    public function counter(string $secret, string $code, ?int $now = null): ?int
    {
        if (!preg_match('/^\d{6}$/', $code)) {
            return null;
        }

        $counter = intdiv($now ?? time(), 30);
        foreach ([$counter - 1, $counter, $counter + 1] as $candidate) {
            $binary = pack('N2', intdiv($candidate, 0x100000000), $candidate % 0x100000000);
            $hash = hash_hmac('sha1', $binary, $this->base32Decode($secret), true);
            $offset = ord($hash[19]) & 0x0f;
            $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;
            if (hash_equals(str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT), $code)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Cria a URI de configuração para aplicativos autenticadores. */
    public function provisioningUri(string $secret, string $email): string
    {
        return 'otpauth://totp/' . rawurlencode('Moves Code:' . $email)
            . '?secret=' . $secret . '&issuer=' . rawurlencode('Moves Code') . '&algorithm=SHA1&digits=6&period=30';
    }

    /** Decodifica base32 sem dependências externas. */
    private function base32Decode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $output = '';
        foreach (str_split(strtoupper(rtrim($input, '='))) as $char) {
            $value = strpos($alphabet, $char);
            if ($value === false) {
                throw new RuntimeException('TOTP secret is unavailable.');
            }
            $buffer = ($buffer << 5) | $value;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xff);
                $buffer &= (1 << $bits) - 1;
            }
        }

        return $output;
    }

    private function base32Encode(string $input): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $output = '';
        foreach (str_split($input) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $output .= $alphabet[($buffer >> $bits) & 31];
                $buffer &= (1 << $bits) - 1;
            }
        }
        if ($bits > 0) {
            $output .= $alphabet[($buffer << (5 - $bits)) & 31];
        }

        return $output;
    }

    private function key(): string
    {
        $encoded = getenv('MOVES_2FA_ENCRYPTION_KEY');
        $key = is_string($encoded) ? base64_decode($encoded, true) : false;
        if (!is_string($key) || strlen($key) !== 32 || !function_exists('openssl_encrypt')) {
            throw new RuntimeException('TOTP encryption is not configured.');
        }

        return $key;
    }
}
