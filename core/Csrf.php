<?php

/**
 * CSRF Protection Helper
 *
 * Generates and validates CSRF tokens to prevent Cross-Site Request Forgery attacks.
 * Tokens are stored in the session and verified on all POST requests.
 *
 * @package    CoverLetterGenerator
 * @subpackage Core
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class Csrf
{
    /**
     * Get or generate the CSRF token for the current session
     *
     * @return string The CSRF token
     */
    public static function token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Generate an HTML hidden input field with the CSRF token
     *
     * @return string HTML hidden input element
     */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::token()) . '">';
    }

    /**
     * Verify the submitted CSRF token matches the session token
     *
     * @return bool True if the token is valid
     */
    public static function verify(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}
