<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    User::create(['name' => 'Ramesh', 'email' => 'ramesh@shop.test', 'password' => 'a-long-password-1']);
});

// A failed login, as sent by a given address (the frontend container, or someone on the internet)
function failedLogin($test, string $remoteAddr, string $forwardedFor)
{
    return $test->withServerVariables(['REMOTE_ADDR' => $remoteAddr])
        ->withHeader('X-Forwarded-For', $forwardedFor)
        ->postJson('/api/auth/login', ['email' => 'ramesh@shop.test', 'password' => 'wrong-password']);
}

test('through our frontend, each visitor has their own login limit', function () {
    // Login allows 5 tries a minute
    foreach (range(1, 5) as $try) {
        failedLogin($this, '172.19.0.7', '1.1.1.1')->assertUnprocessable();
    }
    failedLogin($this, '172.19.0.7', '1.1.1.1')->assertTooManyRequests();

    // Another visitor behind the same frontend is not blocked
    failedLogin($this, '172.19.0.7', '2.2.2.2')->assertUnprocessable();
});

test('from the internet, a faked X-Forwarded-For does not get around the limit', function () {
    foreach (range(1, 5) as $try) {
        failedLogin($this, '203.0.113.9', "9.9.9.{$try}")->assertUnprocessable();
    }

    failedLogin($this, '203.0.113.9', '9.9.9.99')->assertTooManyRequests();
});
