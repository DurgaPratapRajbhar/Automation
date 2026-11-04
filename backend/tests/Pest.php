<?php

use App\Enums\WorkspaceModule;
use App\Models\Channel;
use App\Models\ChannelCredential;
use App\Models\User;
use App\Services\JwtService;
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

// Super admin creates a shop with every module switched on and a new owner, then the owner logs in
function registerOwner(TestCase $test, string $email = 'owner@example.com'): array
{
    $admin = User::firstOrCreate(
        ['email' => 'setup-admin@example.com'],
        ['name' => 'Setup Admin', 'password' => 'password123']
    );
    $admin->forceFill(['is_super_admin' => true])->save();

    $test->postJson('/api/admin/workspaces', [
        'workspace_name' => 'Acme '.$email,
        'owner_name' => 'Owner',
        'owner_email' => $email,
        'owner_password' => 'password123',
        'modules' => WorkspaceModule::values(),
    ], ['Authorization' => 'Bearer '.tokenFor($admin->id)])->assertCreated();

    $response = $test->postJson('/api/auth/login', ['email' => $email, 'password' => 'password123'])->assertOk();

    return [
        'token' => $response->json('data.access_token'),
        'workspace_id' => $response->json('data.workspaces.0.id'),
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
    return app(JwtService::class)->generateAccessToken($userId);
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
        'name' => $roleName.' User',
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

// A business signs up by itself (starts with the starter modules); returns the headers for it and its id
function selfSignedUpShop(TestCase $test, string $email = 'ramesh@shop.test'): array
{
    $response = $test->postJson('/api/auth/signup', [
        'name' => 'Ramesh',
        'shop_name' => 'Ramesh Kirana',
        'email' => $email,
        'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1',
        'accepted_terms' => true,
    ])->assertCreated();

    $shopId = $response->json('data.workspaces.0.id');

    return [headersFor($response->json('data.access_token'), $shopId), $shopId];
}

function superAdminHeaders(): array
{
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password123']);
    $admin->forceFill(['is_super_admin' => true])->save();

    return ['Authorization' => 'Bearer '.tokenFor($admin->id)];
}

// A connected WhatsApp number of the workspace, with the account id and token Meta calls need
function whatsappTestChannel(int $workspaceId, string $phoneNumberId = '444000111', string $wabaId = '555666777'): Channel
{
    $channel = Channel::create([
        'workspace_id' => $workspaceId,
        'type' => 'whatsapp',
        'provider' => 'meta',
        'name' => 'Business WA',
        'external_account_id' => $wabaId,
        'status' => 'connected',
        'phone_number_id' => $phoneNumberId,
    ]);

    ChannelCredential::create([
        'channel_id' => $channel->id,
        'access_token' => 'BUSINESS_TOKEN',
        'config' => ['phone_number_id' => $phoneNumberId, 'waba_id' => $wabaId],
    ]);

    return $channel;
}
