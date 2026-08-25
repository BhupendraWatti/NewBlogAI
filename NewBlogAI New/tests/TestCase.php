<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = (string) $app['config']->get('database.default');
        $database = (string) $app['config']->get("database.connections.{$connection}.database");

        self::assertSafeTestingDatabase($connection, $database);

        return $app;
    }

    public static function assertSafeTestingDatabase(string $connection, string $database): void
    {
        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new LogicException(sprintf(
                'Unsafe test database configuration: connection [%s], database [%s]. Tests require SQLite [:memory:]. Run "php artisan optimize:clear" and retry through "composer test".',
                $connection,
                $database,
            ));
        }
    }
}
