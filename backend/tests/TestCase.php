<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Stops every test before any trait (RefreshDatabase runs migrate:fresh) touches a real database.
     * Done in setUpTraits() because a trait's beforeRefreshingDatabase() would override a method here.
     */
    protected function setUpTraits()
    {
        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests on [{$connection}:{$database}]: tests must use in-memory sqlite."
            );
        }

        return parent::setUpTraits();
    }
}
