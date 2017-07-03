<?php
namespace app\inc;

use \app\inc\Util;

class Route
{
    static public function add($uri, $func = "", $silent = false)
    {
        $signatureMatch = false;
        $e = [];
        $r = [];
        $time_start = Util::microtime_float();
        $uri = trim($uri, "/");
        $requestUri = trim(strtok($_SERVER["REQUEST_URI"], '?'), "/");

        $routeSignature = explode("/", $uri);
        $requestSignature = explode("/", $requestUri);

        for ($i = 0; $i < sizeof($routeSignature); $i++) {

            if ($routeSignature[$i][0] == '{' && $routeSignature[$i][strlen($routeSignature[$i]) - 1] == '}') {
                $r[trim($routeSignature[$i],"{}")] = trim($requestSignature[$i],"{}");
                $signatureMatch = $requestSignature[$i] ? true : false;

            } else if ($routeSignature[$i][0] == '[' && $routeSignature[$i][strlen($routeSignature[$i]) - 1] == ']') {
                $r[trim($routeSignature[$i],"[]")] = trim($requestSignature[$i],"[]");
            } else {
                $e[] = $requestSignature[$i];
                $signatureMatch = $requestSignature[$i] == $routeSignature[$i] ? true : false;
            }

            if (!$signatureMatch) {
                break;
            }
        }

        if ($signatureMatch) {
            if ($func) {
                $func($r);
            }
            $e[count($e) - 1] = ucfirst($e[count($e) - 1]);
            $uri = implode($e, "/");
            $n = sizeof($e);
            $className = strtr($uri, '/', '\\');
            $class = "app\\{$className}";
            $action = Input::getMethod() . "_" . Input::getPath()->part($n + 1);
            if (class_exists($class)) {
                $controller = new $class();
                if (method_exists($controller, $action)) {
                    $response = $controller->$action($r);
                } else {
                    $action = Input::getMethod() . "_index";
                    if (method_exists($controller, $action)) {
                        $response = $controller->$action($r);
                    } else {
                        self::miss();
                    }
                }
            }
            //header('charset=utf-8');
            //header('Content-Type: text/plain; charset=utf-8');
            $code = (isset($response["code"])) ? $response["code"] : "200";
            header("HTTP/1.0 {$code} " . Util::httpCodeText($code));
            if (isset($response["json"])) {
                echo Response::passthru($response["json"]);
            } elseif (isset($response["text"])) {
                echo Response::passthru($response["text"], "text/plain");
            } elseif (isset($response["csv"])) {
                header('Content-Disposition: attachment; filename="data.csv"');
                echo Response::passthru($response["csv"], "csv/plain");
            } else {
                if (!$silent) {
                    $response["_execution_time"] = round((Util::microtime_float() - $time_start), 3);
                    echo Response::toJson($response);
                }
            }
            exit();
        }
    }

    static public function miss(){
        header('HTTP/1.0 404 Not Found');
        echo "<h1>404 Not Found</h1>";
        exit();
    }
}