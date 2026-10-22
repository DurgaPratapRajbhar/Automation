<?php

// Safety net: agar kabhi tests asli (pgsql) DB par chalne lagein to turant fail ho
test('tests run on in-memory sqlite, never on the real database', function () {
    expect(config('database.default'))->toBe('sqlite')
        ->and(config('database.connections.sqlite.database'))->toBe(':memory:');
});
