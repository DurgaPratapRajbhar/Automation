<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase migrate:fresh chalata hai — galat DB par chala to saara data udd jayega.
     * Isliye in-memory sqlite ke alawa kisi bhi DB par tests ko yahin rok do.
     */
    protected function beforeRefreshingDatabase()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to refresh database [{$connection}:{$database}] — tests must use in-memory sqlite."
            );
        }
    }
}
