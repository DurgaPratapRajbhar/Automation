<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// Owner register karo -> naya workspace + Owner role
function registerOwner(TestCase $test, string $email = 'owner@example.com'): array
{
    $response = $test->postJson('/api/auth/register', [
        'name' => 'Owner',
        'email' => $email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'workspace_name' => 'Acme ' . $email,
    ])->assertCreated();

    return [
        'token' => $response->json('data.access_token'),
        'workspace_id' => $response->json('data.workspace.id'),
        'user_id' => $response->json('data.user.id'),
    ];
}

function headersFor(string $token, int $workspaceId): array
{
    return [
        'Authorization' => "Bearer {$token}",
        'X-Workspace-Id' => (string) $workspaceId,
    ];
}

function tokenFor(int $userId): string
{
    return app(App\Services\JwtService::class)->generateAccessToken($userId);
}

// Owner se role banwao, phir us role ke saath naya member add karo
function memberWithPermissions(TestCase $test, array $owner, string $roleName, array $permissions, string $email): array
{
    $roleId = $test->postJson('/api/roles', [
        'name' => $roleName,
        'permissions' => $permissions,
    ], headersFor($owner['token'], $owner['workspace_id']))
        ->assertCreated()
        ->json('data.id');

    $userId = $test->postJson('/api/members', [
        'email' => $email,
        'name' => $roleName . ' User',
        'password' => 'password123',
        'role_id' => $roleId,
    ], headersFor($owner['token'], $owner['workspace_id']))
        ->assertCreated()
        ->json('data.id');

    return [
        'token' => tokenFor($userId),
        'workspace_id' => $owner['workspace_id'],
        'user_id' => $userId,
        'role_id' => $roleId,
    ];
}
