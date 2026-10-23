<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function createSuperAdmin(): User
{
    $user = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password123']);
    $user->forceFill(['is_super_admin' => true])->save();

    return $user;
}

test('shop users log in through the shop login only', function () {
    registerOwner($this);
    $credentials = ['email' => 'owner@example.com', 'password' => 'password123'];

    $this->postJson('/api/auth/login', $credentials)->assertOk()->assertJsonStructure(['data' => ['access_token', 'workspaces']]);

    $this->postJson('/api/admin/auth/login', $credentials)
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Invalid email or password.');
});

test('super admins log in through the admin login only', function () {
    createSuperAdmin();
    $credentials = ['email' => 'admin@example.com', 'password' => 'password123'];

    $this->postJson('/api/admin/auth/login', $credentials)->assertOk()->assertJsonStructure(['data' => ['access_token']]);

    // Same message as a wrong password: the shop login does not reveal admin accounts
    $this->postJson('/api/auth/login', $credentials)
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Invalid email or password.');
});

test('wrong password fails on both logins', function () {
    createSuperAdmin();

    $this->postJson('/api/admin/auth/login', ['email' => 'admin@example.com', 'password' => 'wrong-pass'])->assertUnprocessable();
    $this->postJson('/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'wrong-pass'])->assertUnprocessable();
});
