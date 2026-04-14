<?php

/**
 * Authentication Helper Class
 *
 * Static helper for session-based authentication management.
 * Handles login, logout, and user session state.
 *
 * @package    CoverLetterGenerator
 * @subpackage Core
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class Auth
{
    /**
     * @var array|null Cached user record for the current request
     */
    private static ?array $cachedUser = null;

    /**
     * Log a user in by setting session variables
     *
     * @param array $user The user record from the database
     * @return void
     */
    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = trim($user['first_name'] . ' ' . $user['last_name']);
        self::$cachedUser = null;
    }

    /**
     * Log the current user out
     *
     * @return void
     */
    public static function logout(): void
    {
        self::$cachedUser = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Check if a user is currently authenticated
     *
     * @return bool True if logged in
     */
    public static function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    /**
     * Get the current user's ID
     *
     * @return int|null The user ID or null
     */
    public static function id(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    /**
     * Get the full user record from the database
     *
     * Cached per request to avoid repeated queries.
     *
     * @return array|null The user record or null
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        if (self::$cachedUser === null) {
            $userModel = new User();
            self::$cachedUser = $userModel->find(self::id());
        }

        return self::$cachedUser;
    }

    /**
     * Check if the current user has admin role
     *
     * @return bool True if admin
     */
    public static function isAdmin(): bool
    {
        return ($_SESSION['user_role'] ?? '') === 'admin';
    }

    /**
     * Get the current user's display name
     *
     * @return string The user name or empty string
     */
    public static function name(): string
    {
        return $_SESSION['user_name'] ?? '';
    }
}
