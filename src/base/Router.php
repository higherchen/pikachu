<?php

namespace bilibili\pikachu\base;

use bilibili\pikachu\middleware\clockwork\Monitor;

class Router
{
    /**
     * @var static router instance, for global call
     */
    protected static $_instance;

    /**
     * @var array The route patterns and their handling functions
     */
    protected static $routes = array();

    /**
     * @var object|callable The function to be executed when no route has been matched
     */
    protected $notFound;

    /**
     * @var string The current route name
     */
    protected $routeName = '';

    /**
     * @var array The result of current route
     */
    protected $routeInfo = [];

    public function __call($method, $params)
    {
        $accept_method = array('get', 'post', 'patch', 'delete', 'put', 'options');
        if (in_array($method, $accept_method) && count($params) >= 2) {
            $this->match(strtoupper($method), $params[0], $params[1]);
        }
    }

    public static function getInstance()
    {
        if (static::$_instance == null) {
            static::$_instance = new static();
        }

        return static::$_instance;
    }

    /**
     * Set the 404 handling function.
     *
     * @param object|callable $fn The function to be executed
     */
    public function set404($fn)
    {
        if (is_string($fn) && strstr($fn, '@')) {
            $fn = explode('@', $fn);
        }
        $this->notFound = $fn;
    }

    public function match($methods, $pattern, $argvs)
    {
        $pattern = '/'.trim($pattern, '/');
        $fn = $argvs;
        $name = $middleware = $final = null;
        if (is_array($argvs) && isset($argvs['uses'])) {
            $fn = $argvs['uses'];
            $name = isset($argvs['as']) ? $argvs['as'] : '';
            $middleware = isset($argvs['middleware']) ? $argvs['middleware'] : '';
            $final = (isset($argvs['final']) && is_bool($argvs['final'])) ? $argvs['final'] : true;
        }
        if (is_string($fn) && strstr($fn, '@')) {
            $fn = explode('@', $fn);
        }

        foreach (explode('|', $methods) as $method) {
            $method = strtoupper($method);
            static::$routes[$method][] = [
                'as' => $name,
                'pattern' => $pattern,
                'middleware' => $middleware,
                'fn' => $fn,
                'final' => $final,
            ];
        }
    }

    public function getRouteName()
    {
        return $this->routeName;
    }

    public function getInfo()
    {
        return $this->routeInfo;
    }

    /**
     * Execute the router: Loop all defined before middlewares and routes, and execute the handling function if a match was found.
     *
     * @param object|callable $callback Function to be executed after a matching route was handled (= after router middleware)
     */
    public function run($callback = null)
    {
        $method = Request::getInstance()->getMethod();

        $handled = false;
        if (isset(static::$routes[$method])) {
            $handled = $this->handle(static::$routes[$method]);
        }

        if (!$handled) {
            // Handle 404
            $notFound = $this->notFound;
            if (!$notFound) {
                Response::getInstance()->abort(404);
            }
            if (is_array($notFound)) {
                $notFound[0] = new $notFound[0]();
            }
            if (!is_callable($notFound)) {
                Response::getInstance()->abort(404);
            }
            call_user_func($notFound);
        } else {
            // After router middleware
            if (is_string($callback) && strstr($callback, '@')) {
                $callback = explode('@', $callback);
                $callback[0] = new $callback[0]();
                call_user_func($callback);
            } elseif ($callback) {
                $callback();
            }
        }

        // If it originally was a HEAD request, clean up after ourselves by emptying the output buffer
        if ($_SERVER['REQUEST_METHOD'] == 'HEAD') {
            ob_end_clean();
        }

        if (Registry::getInstance()->debug) {
            Monitor::getInstance()->endEvent('App Request');
        }
    }

    /**
     * Handle a a set of routes: if a match is found, execute the relating handling function.
     *
     * @param array $routes Collection of route patterns and their handling functions
     *
     * @return int The number of routes handled
     */
    protected function handle($routes)
    {
        // The current page URL
        $currentUri = Request::getInstance()->getUrlPath();

        // Loop all routes
        foreach ($routes as $route) {

            // we have a match!
            if (preg_match_all('#^'.$route['pattern'].'$#', $currentUri, $matches, PREG_OFFSET_CAPTURE)) {

                // Rework matches to only contain the matches, not the orig string
                $matches = array_slice($matches, 1);

                // Extract the matched URL parameters (and only the parameters)
                $params = array_map(
                    function ($match, $index) use ($matches) {
                        // We have a following parameter: take the substring from the current param position until the next one's position (thank you PREG_OFFSET_CAPTURE)
                        if (isset($matches[$index + 1]) && isset($matches[$index + 1][0]) && is_array($matches[$index + 1][0])) {
                            return trim(substr($match[0][0], 0, $matches[$index + 1][0][1] - $match[0][1]), '/');
                        } else {
                            // We have no following parameters: return the whole lot
                            return isset($match[0][0]) ? trim($match[0][0], '/') : null;
                        }

                    }, $matches, array_keys($matches)
                );
                $params = array_merge([Request::getInstance()], $params);

                // call the handling function with the URL parameters
                if ($route['as']) {
                    $this->routeName = $route['as'];
                }
                if ($route['middleware']) {
                    $before = $route['middleware'];
                    if (is_string($before) && strstr($before, '@')) {
                        $before = explode('@', $before);
                        $before[0] = $before[0]::getInstance();
                    }
                    call_user_func_array($before, $params);
                }
                if (is_array($route['fn'])) {
                    $this->routeInfo = ['controller' => $route['fn'][0], 'method' => $route['fn'][1]];
                    $route['fn'][0] = new $route['fn'][0]();
                } else {
                    $this->routeInfo['controller'] = 'Anonymous';
                    $this->routeInfo['method'] = is_string($route['fn']) ? $route['fn'] : 'Anonymous';
                }
                call_user_func_array($route['fn'], $params);

                // check if quit after deal with the route
                if (isset($route['final']) && $route['final'] === false) {
                    continue;
                }

                return true;
            }
        }

        return false;
    }
}
