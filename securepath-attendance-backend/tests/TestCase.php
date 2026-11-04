<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Docker injects the development MySQL variables at process level. Force
        // every test case onto disposable in-memory services before traits run.
        $app->make('config')->set([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'session.driver' => 'array',
        ]);

        return $app;
    }
}
