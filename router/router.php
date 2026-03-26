<?php
/*
 ___      _______  _______  _______  __   __        ____   _______  _______  _______ 
|   |    |       ||       ||       ||  | |  |      |    | |       ||       ||       |
|   |    |   _   ||    ___||    ___||  |_|  |       |   | |___    ||___    ||___    |
|   |    |  | |  ||   |___ |   |___ |       |       |   |  ___|   | ___|   |    |   |
|   |___ |  |_|  ||    ___||    ___||_     _| ___   |   | |___    ||___    |    |   |
|       ||       ||   |    |   |      |   |  |   |  |   |  ___|   | ___|   |    |   |
|_______||_______||___|    |___|      |___|  |___|  |___| |_______||_______|    |___|                
*/

class Router {
    private $routes = [];
    private $debug = false;
    private $CURRENT_HTTP_METHODS = [
        "GET", "POST",
        "PUT", "PATCH",
        "DELETE", "OPTIONS"
    ];

    public function __construct($debug = false) {
        $this->debug = $debug;
    }

    // Регистрация маршрута
    public function add_route($uri, $handler, $methods) {
        $methods = $this->normalize_http_methods($methods);
        if (is_array($methods) && count($methods) > 0) {
            $uri = $this->normalize_uri($uri);
            $params = $this->extract_param_names($uri);
            $regex = $this->uri_to_regex($uri);
            array_push($this->routes, [
                "uri" => $uri,
                "methods" => $methods,
                "handler" => $handler,
                "params" => $params,
                "regex" => $regex
            ]);
        }
    }

    // Обработка маршрутов
    public function route() {
        $uri = $this->normalize_uri(parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH));
        $method = strtoupper($_SERVER["REQUEST_METHOD"] ?? "GET");
        if (is_array($this->routes) && count($this->routes) > 0) {
            foreach ($this->routes as $route) {
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
                                $this->response($result, 200);
                            }
                            return;
                        } catch (Throwable $err) {
                            $this->handle_error($err);
                            return;
                        }
                    } else {
                        header("Allow: " . implode(",", $route["methods"]));
                        $this->response([
                            "status" => "error",
                            "message" => "Method Not Allowed"
                        ], 405);
                        return;
                    }
                }
            }
        }
        $this->response([
            "status" => "error",
            "message" => "Not Found"
        ], 404);
    }

    
    // Нормализация URI
    private function normalize_uri($uri) {
        $uri = trim($uri);
        if ($uri == "") {
            return "/";
        }
        if ($uri[0] != "/") {
            $uri = "/" . $uri;
        }
        $uri = preg_replace("#/+#", "/", $uri);
        if ($uri != "/" && str_ends_with($uri, "/")) {
            $uri = rtrim($uri, "/");
        }
        return $uri;
    }

    // Нормальизация HTTP-методов
    private function normalize_http_methods($methods) {
        $methods = array_map("strtoupper", $methods);
        if (in_array("ANY", $methods)) {
            return $this->CURRENT_HTTP_METHODS;
        }
        foreach ($methods as $idx => $method) {
            if (!in_array($method, $this->CURRENT_HTTP_METHODS)) {
                unset($methods[$idx]);
            }
        }
        return $methods;
    }

    // Получение имен параметров из URI
    private function extract_param_names($uri) {
        $params = [];
        for ($i = 0; $i < strlen($uri); $i++) {
            if ($uri[$i] == "{") {
                $start = $i + 1;
                $end = strpos($uri, "}", $start);
                if ($end === false) {
                    break;
                }
                $name = substr($uri, $start, $end - $start);
                if ($name != "" && $this->is_valid_param_name($name)) {
                    array_push($params, $name);
                }
            }
        }
        return $params;
    }

    // Владиация имени параметра в URI
    private function is_valid_param_name($name) {
        $name = str_split($name);
        if (!(($name[0] >= "a" && $name[0] <= "z") || ($name[0] >= "A" && $name[0] <= "Z"))) {
            return false;
        }
        foreach ($name as $char) {
            if (!(($char >= "a" && $char <= "z") || ($char >= "A" && $char <= "Z") || ($char >= "0" && $char <= "9") || ($char == "_"))) {
                return false;
            }
        }
        return true;
    }

    // Получение регулярного выржаения для переданного URI
    private function uri_to_regex($uri) {
        $pattern = preg_replace_callback(
            "/\{([a-zA-Z][a-zA-Z0-9_]*)\}/", 
            function ($matches) {
                $name = $matches[1];
                return '(?P<' . $name . '>[^/]+)';
            },
            $uri
        );
        return '#^' . $pattern . '$#';
    }

    // Обработка ответа
    private function response($content, $code) {
        http_response_code($code);
        if (is_array($content)) {
            header("Content-Type: application/json; charset=utf-8");
            echo json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            die();
        }
        if (is_string($content)) {
            header("Content-Type: text/html; charset=utf-8");
            echo $content;
            die();
        }
        if ($content == null) {
            return;
        }
        throw new RuntimeException(
            "(response) Unsupported response type: " . gettype($content)
        );
    }

    // Логгирование ошибок
    private function handle_error($err) {
        if ($this->debug) {
            $fs = fopen("./router_errors.log", "a");
            fwrite($fs, json_encode([
                "message" => $err->getMessage(),
                "file" => $err->getFile(),
                "line" => $err->getLine(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
            fclose($fs);
        }
        http_response_code(500);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode([
            "status" => "error",
            "message" => "Internal Server Error"
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        die();
    }
}