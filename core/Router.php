<?php

/**
 * URL Router Class
 *
 * Handles URL routing by mapping paths to controller actions.
 * Supports dynamic route parameters like {id}.
 *
 * @package    CoverLetterGenerator
 * @subpackage Core
 * @author     J.J.Johnson <email4johnson@gmail.com>
 * @copyright  2026 VisionQuest Services LLC
 */
class Router
{
    /**
     * @var array Registered routes
     */
    private array $routes = [];

    /**
     * Register a GET route
     *
     * @param string $path       The URL path pattern
     * @param string $controller The controller class name
     * @param string $action     The controller method to call
     * @return void
     */
    public function get(string $path, string $controller, string $action): void
    {
        $this->addRoute('GET', $path, $controller, $action);
    }

    /**
     * Register a POST route
     *
     * @param string $path       The URL path pattern
     * @param string $controller The controller class name
     * @param string $action     The controller method to call
     * @return void
     */
    public function post(string $path, string $controller, string $action): void
    {
        $this->addRoute('POST', $path, $controller, $action);
    }

    /**
     * Add a route to the routes array
     *
     * @param string $method     HTTP method (GET, POST)
     * @param string $path       The URL path pattern
     * @param string $controller The controller class name
     * @param string $action     The controller method to call
     * @return void
     */
    private function addRoute(string $method, string $path, string $controller, string $action): void
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'controller' => $controller,
            'action' => $action,
        ];
    }

    /**
     * Dispatch a request to the appropriate controller action
     *
     * @param string $uri    The request URI
     * @param string $method The HTTP method
     * @return void
     */
    public function dispatch(string $uri, string $method): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');

        foreach ($this->routes as $route) {
            $pattern = $this->convertToRegex($route['path']);

            if ($route['method'] === $method && preg_match($pattern, $uri, $matches)) {
                array_shift($matches);
                $this->callAction($route['controller'], $route['action'], $matches);
                return;
            }
        }

        http_response_code(404);
        require __DIR__ . '/../app/Views/errors/404.php';
    }

    /**
     * Convert a route path to a regular expression
     *
     * @param string $path The route path pattern
     * @return string The regex pattern
     */
    private function convertToRegex(string $path): string
    {
        $pattern = preg_replace('/\{id\}/', '(\d+)', $path);
        $pattern = preg_replace('/\{(\w+)\}/', '([^\/]+)', $pattern);
        return '#^' . $pattern . '$#';
    }

    /**
     * Call a controller action with parameters
     *
     * @param string $controllerName The controller class name
     * @param string $action         The method to call
     * @param array  $params         Parameters extracted from the URL
     * @return void
     * @throws Exception If controller or action not found
     */
    private function callAction(string $controllerName, string $action, array $params): void
    {
        $controllerFile = __DIR__ . "/../app/Controllers/{$controllerName}.php";

        if (!file_exists($controllerFile)) {
            throw new Exception("Controller '{$controllerName}' not found");
        }

        require_once $controllerFile;

        $controller = new $controllerName();

        if (!method_exists($controller, $action)) {
            throw new Exception("Action '{$action}' not found in controller '{$controllerName}'");
        }

        call_user_func_array([$controller, $action], $params);
    }
}
