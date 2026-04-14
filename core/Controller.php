<?php

/**
 * Base Controller Class
 *
 * Provides common functionality for all controllers including view rendering,
 * redirects, JSON responses, and flash messages.
 *
 * @package    CoverLetterGenerator
 * @subpackage Core
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class Controller
{
    /**
     * Render a view with data
     *
     * @param string $view The view path relative to app/Views (without .php extension)
     * @param array  $data Associative array of data to pass to the view
     * @return void
     * @throws Exception If the view file is not found
     */
    protected function view(string $view, array $data = []): void
    {
        extract($data);

        $viewPath = __DIR__ . "/../app/Views/{$view}.php";

        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            throw new Exception("View '{$view}' not found");
        }
    }

    /**
     * Redirect to a URL
     *
     * @param string $url The URL path to redirect to (relative to BASE_URL)
     * @return void
     */
    protected function redirect(string $url): void
    {
        header("Location: " . BASE_URL . $url);
        exit;
    }

    /**
     * Send a JSON response
     *
     * @param array $data       The data to encode as JSON
     * @param int   $statusCode HTTP status code (default: 200)
     * @return void
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Set a flash message in the session
     *
     * @param string $type    The message type (success, danger, warning, info)
     * @param string $message The message text
     * @return void
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message,
        ];
    }

    /**
     * Get and clear the flash message from session
     *
     * @return array|null The flash message or null
     */
    protected function getFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }

    /**
     * Require user authentication
     *
     * Redirects to login page if user is not authenticated.
     *
     * @return void
     */
    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            $this->flash('warning', 'Please log in to continue.');
            $this->redirect('/login');
            exit;
        }
    }

    /**
     * Require admin privileges
     *
     * Redirects to dashboard if user is not an admin.
     *
     * @return void
     */
    protected function requireAdmin(): void
    {
        $this->requireAuth();
        if (!Auth::isAdmin()) {
            $this->flash('danger', 'Access denied. Admin privileges required.');
            $this->redirect('/dashboard');
            exit;
        }
    }

    /**
     * Get the current authenticated user's ID
     *
     * @return int The user ID
     */
    protected function currentUserId(): int
    {
        return Auth::id();
    }
}
