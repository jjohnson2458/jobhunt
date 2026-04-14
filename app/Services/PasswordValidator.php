<?php

/**
 * Password Validator Service
 *
 * Validates passwords against security requirements and generates secure passwords.
 *
 * @package    CoverLetterGenerator
 * @subpackage Services
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class PasswordValidator
{
    /**
     * Validate a password against all requirements
     *
     * Requirements:
     * - At least 8 characters
     * - At least one letter
     * - At least one number
     * - At least one uppercase letter
     * - At least one special character
     *
     * @param string $password The password to validate
     * @return array Array of error messages (empty if valid)
     */
    public static function validate(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        }

        if (!preg_match('/[a-zA-Z]/', $password)) {
            $errors[] = 'Password must contain at least one letter.';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        }

        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Password must contain at least one special character.';
        }

        return $errors;
    }

    /**
     * Generate a secure random password meeting all requirements
     *
     * @param int $length The desired password length (minimum 12)
     * @return string The generated password
     */
    public static function generateSecure(int $length = 12): string
    {
        $length = max(12, $length);

        $lowercase = 'abcdefghijklmnopqrstuvwxyz';
        $uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $numbers = '0123456789';
        $special = '!@#$%^&*()-_=+';

        // Ensure at least one of each type
        $password = '';
        $password .= $lowercase[random_int(0, strlen($lowercase) - 1)];
        $password .= $uppercase[random_int(0, strlen($uppercase) - 1)];
        $password .= $numbers[random_int(0, strlen($numbers) - 1)];
        $password .= $special[random_int(0, strlen($special) - 1)];

        // Fill remaining length with random characters from all pools
        $all = $lowercase . $uppercase . $numbers . $special;
        for ($i = strlen($password); $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        // Shuffle the password
        return str_shuffle($password);
    }

    /**
     * Get human-readable password requirements
     *
     * @return string The requirements text
     */
    public static function getRequirements(): string
    {
        return 'Password must be at least 8 characters and include a number, a letter, an uppercase letter, and a special character.';
    }
}
