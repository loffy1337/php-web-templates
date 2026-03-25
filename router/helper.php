<?php
declare(strict_types=1);

$CURRENT_HTTP_METHODS = [
    "GET", "POST",
    "PUT", "PATCH",
    "DELETE", "OPTIONS"
];

// Нормализация URI
function normalize_uri($uri) {
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
function normalize_http_methods($methods) {
    global $CURRENT_HTTP_METHODS;
    $methods = array_map("strtoupper", $methods);
    if (in_array("ANY", $methods)) {
        return $CURRENT_HTTP_METHODS;
    }
    foreach ($methods as $idx => $method) {
        if (!in_array($method, $CURRENT_HTTP_METHODS)) {
            unset($methods[$idx]);
        }
    }
    return $methods;
}

// Получение имен параметров из URI
function extract_param_names($uri) {
    $params = [];
    for ($i = 0; $i < strlen($uri); $i++) {
        if ($uri[$i] == "{") {
            $start = $i + 1;
            $end = strpos($uri, "}", $start);
            if ($end === false) {
                break;
            }
            $name = substr($uri, $start, $end - $start);
            if ($name != "" && is_valid_param_name($name)) {
                array_push($params, $name);
            }
        }
    }
    return $params;
}

// Владиация имени параметра в URI
function is_valid_param_name($name) {
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
function uri_to_regex($uri) {
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
function response($content, $code) {
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
function handle_error($err) {
    global $debug;
    if ($debug) {
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