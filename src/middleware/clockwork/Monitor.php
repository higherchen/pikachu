<?php

namespace bilibili\pikachu\middleware\clockwork;

use Clockwork\Clockwork;
use Clockwork\Storage\FileStorage;
use bilibili\pikachu\middleware\Middleware;

class Monitor implements Middleware
{
    protected static $_clockwork;
    protected static $_instance;
    protected static $_datasource;

    public static function getInstance()
    {
        if (static::$_instance == null) {
            static::$_instance = new static();
        }

        return static::$_instance;
    }

    public static function getDatasource()
    {
        if (static::$_datasource == null) {
            static::$_datasource = new DataSource();
        }

        return static::$_datasource;
    }

    public static function getClockwork($storage = null)
    {
        if (static::$_clockwork == null) {
            static::$_clockwork = new Clockwork();

            static::$_clockwork->addDataSource(static::getDatasource());

            $_storage = $storage ? $storage : new FileStorage('/tmp/');

            static::$_clockwork->setStorage($_storage);

            header('X-Clockwork-Id: '.static::$_clockwork->getRequest()->id);
            header('X-Clockwork-Version: '.Clockwork::VERSION);
        }

        return static::$_clockwork;
    }

    public function dbQuery($query, $time)
    {
        $clockwork = static::getClockwork();
        static::getDatasource()->addDatabaseQuery($query, $time);

        $clockwork->resolveRequest();
        $clockwork->storeRequest();
    }

    public function httpRequest($url, $time, $query = '')
    {
        $clockwork = static::getClockwork();
        $info = [];
        $info[] = "curl: {$url}";
        if ($query) {
            $info[] = "query: {$query}";
        }
        static::getDatasource()->addDatabaseQuery(implode(' , ', $info), $time);

        $clockwork->resolveRequest();
        $clockwork->storeRequest();
    }

    public function startEvent($event, $description)
    {
        static::getDatasource()->startEvent($event, $description);
    }

    public function endEvent($event)
    {
        static::getDatasource()->endEvent($event);
    }
}
