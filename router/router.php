<?php
declare(strict_types=1);

require_once "./helper.php";

// Режим разработки (для полного логгирования ошибок)
$debug = true;
// Хранилище маршрутов
$routes = [];

// Регистрация маршрута
function add_route($uri, $handler, $methods) {
    global $routes;
    $methods = normalize_http_methods($methods);
    if (is_array($methods) && count($methods) > 0) {
        $uri = normalize_uri($uri);
        $params = extract_param_names($uri);
        $regex = uri_to_regex($uri);
        array_push($routes, [
            "uri" => $uri,
            "methods" => $methods,
            "handler" => $handler,
            "params" => $params,
            "regex" => $regex
        ]);
    }
}

// Обработка маршрутов
function route() {
    global $routes;
    $uri = normalize_uri(parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH));
    $method = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");
    if (is_array($routes) && count($routes) > 0) {
        foreach ($routes as $route) {
            if (preg_match($route["regex"], $uri, $matches)) {
                if (in_array($method, $route["methods"], true)) {
                    $params = [];
                    if (isset($route["params"]) && is_array($route["params"]) && count($route["params"]) > 0) {
                        foreach ($route["params"] as $param) {
                            $params[$param] = $matches[$param] ?? null;
                        }
                    }
                    try {
                        $result = call_user_func($route["handler"], $params);
                        if ($result != null) {
                            response($result, 200);
                        }
                        return;
                    } catch (Throwable $err) {
                        handle_error($err);
                        return;
                    }
                } else {
                    header("Allow: " . implode(",", $route["methods"]));
                    response([
                        "status" => "error",
                        "message" => "Method Not Allowed"
                    ], 405);
                    return;
                }
            }
        }
    }
    response([
        "status" => "error",
        "message" => "Not Found"
    ], 404);
}