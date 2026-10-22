<?php
// app/Core/Router.php

class Router {
    private array $routes = [];

    // Register a GET route
    public function get(string $path, array $handler): void {
        $this->routes['GET'][$this->normalizePath($path)] = $handler;
    }

    // Register a POST route
    public function post(string $path, array $handler): void {
        $this->routes['POST'][$this->normalizePath($path)] = $handler;
    }

    // Dispatch the incoming request to the correct controller
    public function dispatch(string $uri, string $method): void {
        $parsedPath = parse_url($uri, PHP_URL_PATH);
        
        // Remove '/public' and '/index.php' from the URL to map routes correctly
        $parsedPath = str_replace('/public', '', $parsedPath);
        $parsedPath = str_replace('/index.php', '', $parsedPath);
        
        // If the path is empty after replacing, default to root '/'
        if (trim($parsedPath) === '') {
            $parsedPath = '/';
        }

        $path = $this->normalizePath($parsedPath);
        $method = strtoupper($method);

        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $routePath => $handler) {
                // Convert route parameters like {hash} or {id} into Regex patterns
                $pattern = preg_replace('/\{[a-zA-Z0-9_]+\}/', '([a-zA-Z0-9_-]+)', $routePath);
                $pattern = '#^' . $pattern . '$#';

                if (preg_match($pattern, $path, $matches)) {
                    array_shift($matches); // Remove full route match

                    [$controllerClass, $action] = $handler;

                    // Include the controller file dynamically
                    $controllerFile = __DIR__ . "/../Controllers/{$controllerClass}.php";
                    if (file_exists($controllerFile)) {
                        require_once $controllerFile;
                    }

                    if (class_exists($controllerClass)) {
                        $controller = new $controllerClass();
                        if (method_exists($controller, $action)) {
                            // Call controller method passing the extracted hash
                            call_user_func_array([$controller, $action], $matches);
                            return;
                        }
                    }
                }
            }
        }

        // Application-level 404 fallback
        http_response_code(404);
        echo "404 - Page Not Found (Application Router)";
    }

    // Remove leading/trailing slashes for uniform matching
    private function normalizePath(string $path): string {
        $trimmed = trim($path, '/');
        return $trimmed === '' ? '/' : $trimmed;
    }
}