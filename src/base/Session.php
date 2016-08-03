<?php

namespace bilibili\pikachu\base;

class Session
{
    public static $_instance;

    public function __construct()
    {
        if (!session_id()) {
            session_start();
        }
    }

    public static function __callstatic($method, $params)
    {
        if (!self::$_instance) {
            self::$_instance = new self();
        }

        return call_user_func_array([self::$_instance, '_'.$method], $params);
    }

    public function _has($name)
    {
        return isset($_SESSION[$name]);
    }

    public function _get($name = '')
    {
        if (!$name) {
            return $_SESSION;
        }

        return isset($_SESSION[$name]) ? $_SESSION[$name] : '';
    }

    public function _set($name, $value)
    {
        $_SESSION[$name] = $value;

        return self::$_instance;
    }
}
