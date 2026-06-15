<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Masks personally identifiable information (PII) before it reaches logs.
 *
 * Keeps just enough signal for debugging (email domain, a couple of leading
 * characters) while never writing full email addresses or names in clear text.
 */
final class PiiMasker
{
    /**
     * Masks the local-part of an email, keeping the domain for debugging.
     *
     * Examples: "john.doe@example.com" => "j***@example.com",
     *           "a@example.com"        => "***@example.com".
     *
     * @param string|null $email The email to mask
     * @return string The masked email, or "***" when no usable value is provided
     */
    public static function maskEmail(?string $email): string
    {
        if ($email === null || $email === '') {
            return '***';
        }

        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return self::maskName($email);
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos);

        $first = mb_substr($local, 0, 1);
        $maskedLocal = mb_strlen($local) > 1 ? $first . '***' : '***';

        return $maskedLocal . $domain;
    }

    /**
     * Masks a free-text name/identifier, keeping only the first character.
     *
     * Example: "Jean Dupont" => "J***".
     *
     * @param string|null $name The name to mask
     * @return string The masked name, or "***" when no usable value is provided
     */
    public static function maskName(?string $name): string
    {
        if ($name === null || trim($name) === '') {
            return '***';
        }

        return mb_substr(trim($name), 0, 1) . '***';
    }
}
