<?php

/**
 * Environment Variable Loader
 *
 * Loads variables from a .env file into $_ENV superglobal and putenv().
 * Supports quoted values, comments, and boolean conversion.
 *
 * @package    CoverLetterGenerator
 * @subpackage Core
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class Env
{
    /**
     * @var bool Whether the environment file has been loaded
     */
    private static bool $loaded = false;

    /**
     * Load environment variables from .env file
     *
     * @param string $path Directory path containing the .env file
     * @return void
     */
    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        $envFile = rtrim($path, '/\\') . '/.env';

        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                if (preg_match('/^"(.*)"$/', $value, $matches)) {
                    $value = $matches[1];
                } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                    $value = $matches[1];
                }

                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with optional default
     *
     * @param string $key     The environment variable name
     * @param mixed  $default Default value if variable is not set
     * @return mixed The environment value or default
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        if (strtolower($value) === 'true') {
            return true;
        }
        if (strtolower($value) === 'false') {
            return false;
        }

        return $value;
    }
}

/**
 * Helper function to get environment variables
 *
 * @param string $key     The environment variable name
 * @param mixed  $default Default value if variable is not set
 * @return mixed The environment value or default
 */
function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}
