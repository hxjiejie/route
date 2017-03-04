<?php

namespace component\route;

class RouteException extends \Exception {

}

/**
 * @method static Route get(string $route, Callable $callback)
 * @method static Route post(string $route, Callable $callback)
 * @method static Route put(string $route, Callable $callback)
 * @method static Route delete(string $route, Callable $callback)
 * @method static Route options(string $route, Callable $callback)
 * @method static Route head(string $route, Callable $callback)
 */
class Route
{
    public static $halts        = false;
    public static $routes       = [];
    public static $methods      = [];
    public static $callbacks    = [];
    public static $patterns     = [
        ':any' => '[^/]+',
        ':num' => '[0-9]+',
        ':all' => '.*'
    ];
    public static $error_callback;
    public static $namespace;

    /**
     * Defines a route w/ callback and method
     * @param string $method
     * @param array $params
     */
    public static function __callstatic($method, $params)
    {
        $uri        = trim($params[0]);
        $callback   = $params[1];

        array_push(self::$routes, $uri);
        array_push(self::$methods, strtoupper($method));
        array_push(self::$callbacks, $callback);
    }

    /**
     * Defines callback if route is not found
     * @param mixed $callback
     */
    public static function error($callback)
    {
        self::$error_callback = $callback;
    }

    public static function haltOnMatch($flag = true)
    {
        self::$halts = $flag;
    }

    public static function setNamespace($namespace) {
        self::$namespace = $namespace;
    }

    /**
     * Runs the callback for the given request
     */
    public static function bootstrap()
    {
        $uri            = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method         = $_SERVER['REQUEST_METHOD'];

        $searches       = array_keys(static::$patterns);
        $replaces       = array_values(static::$patterns);

        $found_route    = false;

        // Check if route is defined without regex
        if (in_array($uri, self::$routes)) {
            $route_pos = array_keys(self::$routes, $uri);

            foreach ($route_pos as $route) {
                // Using an ANY option to match both GET and POST requests
                if (self::$methods[$route] == $method || self::$methods[$route] == 'ANY') {
                    $found_route = true;

                    // If route is not an object
                    if (!is_object(self::$callbacks[$route])) {
                        $segments   = explode('@', self::$callbacks[$route]);
                        $class      = self::$namespace . '\\' . $segments[0];
                        $controller = new $class;

                        // Call method
                        $controller->{$segments[1]}();

                        if (self::$halts) {
                            return;
                        }
                    } else {
                        // Call closure
                        call_user_func(self::$callbacks[$route]);

                        if (self::$halts) {
                            return;
                        }
                    }
                }
            }
        } else {
            // Check if defined with regex
            $pos = 0;
            foreach (self::$routes as $route) {
                if (strpos($route, ':') !== false) {
                    $route = str_replace($searches, $replaces, $route);
                }

                if (preg_match('#^' . $route . '$#', $uri, $matched)) {
                    if (self::$methods[$pos] == $method || self::$methods[$pos] == 'ANY') {
                        $found_route = true;

                        // Remove $matched[0] as [1] is the first parameter.
                        array_shift($matched);

                        if (!is_object(self::$callbacks[$pos])) {
                            // Grab the controller name and method call
                            $segments   = explode('@', self::$callbacks[$pos]);

                            // Instanitate controller
                            $controller = new $segments[0]();

                            // Fix multi parameters
                            if (!method_exists($controller, $segments[1])) {
                                echo "controller and action not found";
                            } else {
                                call_user_func_array(array($controller, $segments[1]), $matched);
                            }

                            if (self::$halts) {
                                return;
                            }
                        } else {
                            call_user_func_array(self::$callbacks[$pos], $matched);

                            if (self::$halts) {
                                return;
                            }
                        }
                    }
                }
                $pos++;
            }
        }

        // Run the error callback if the route was not found
        if ($found_route == false) {

            if (!self::$error_callback) {
                self::$error_callback = function () {
                    header($_SERVER['SERVER_PROTOCOL'] . " 404 Not Found");
                    echo '404';
                };
            }

            call_user_func(self::$error_callback);
        }

    }

}
