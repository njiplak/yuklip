<?php

namespace App\Utils;

class PhoneFormatter
{
    /**
     * Normalize a phone number to E.164 format.
     *
     * Handles:
     * - Moroccan local formats: 06xxxxxxxx, 07xxxxxxxx → +212...
     * - Already-prefixed numbers: +33..., 00212...
     * - Strips spaces, dashes, parentheses, dots
     * - Returns null if the result doesn't look like a valid phone number
     */
    public static function toE164(string $phone, string $defaultCountryCode = '212'): ?string
    {
        // Strip all formatting characters
        $cleaned = preg_replace('/[\s\-\(\)\.\x{00A0}\x{200B}]+/u', '', $phone);

        if ($cleaned === '' || $cleaned === null) {
            return null;
        }

        // Already in E.164 format
        if (preg_match('/^\+\d{8,15}$/', $cleaned)) {
            return $cleaned;
        }

        // International format with 00 prefix (e.g. 00212661234567)
        if (str_starts_with($cleaned, '00')) {
            $cleaned = '+' . substr($cleaned, 2);
            if (preg_match('/^\+\d{8,15}$/', $cleaned)) {
                return $cleaned;
            }

            return null;
        }

        // Moroccan local numbers: 06xxxxxxxx or 07xxxxxxxx → +2126xxxxxxxx or +2127xxxxxxxx
        if (preg_match('/^0([67]\d{8})$/', $cleaned, $matches)) {
            return '+' . $defaultCountryCode . $matches[1];
        }

        // Other local formats starting with 0 (generic: drop leading 0, prepend country code)
        if (str_starts_with($cleaned, '0') && strlen($cleaned) >= 9) {
            return '+' . $defaultCountryCode . substr($cleaned, 1);
        }

        // Number without any prefix but looks like it has a country code (8+ digits starting with non-zero)
        if (preg_match('/^[1-9]\d{7,14}$/', $cleaned)) {
            return '+' . $cleaned;
        }

        return null;
    }

    /**
     * Check if a phone number is valid E.164 format.
     */
    public static function isValid(?string $phone): bool
    {
        if (!$phone) {
            return false;
        }

        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $phone);
    }
}
