<?php

use App\Enums\WorkspaceModule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['trial.days' => 7]);
});

// A shopkeeper signs up by himself; returns the headers for his shop and the shop id
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

test('a shop that signs up by itself starts with Inbox, WhatsApp and Knowledge only', function () {
    [$shop] = selfSignedUpShop($this);

    $response = $this->getJson('/api/workspace/me', $shop)->assertOk()
        ->assertJsonPath('data.modules', ['inbox', 'whatsapp', 'knowledge'])
        ->assertJsonPath('data.available_modules', WorkspaceModule::values());

    $permissions = $response->json('data.permissions');

    expect($permissions)->toContain('conversations.view', 'channels.view', 'knowledge-sources.view', 'workspace.view')
        ->and($permissions)->not->toContain('customers.view')
        ->and($permissions)->not->toContain('members.view')
        ->and($permissions)->not->toContain('roles.view');
});

test('a module that is switched off is closed at the API, not only hidden in the menu', function () {
    [$shop] = selfSignedUpShop($this);

    $this->getJson('/api/customers', $shop)
        ->assertForbidden()
        ->assertJsonPath('module', 'customers');
    $this->getJson('/api/members', $shop)->assertForbidden()->assertJsonPath('module', 'team');
    $this->getJson('/api/roles', $shop)->assertForbidden()->assertJsonPath('module', 'team');

    // The three modules that are on, and the parts that belong to no module, still work
    $this->getJson('/api/knowledge-sources', $shop)->assertOk();
    $this->getJson('/api/conversations', $shop)->assertOk();
    $this->getJson('/api/channels', $shop)->assertOk();
    $this->getJson('/api/workspace/profile', $shop)->assertOk();
});

test('the super admin switches a module on for a shop and the owner can use it at once', function () {
    [$shop, $shopId] = selfSignedUpShop($this);
    $admin = superAdminHeaders();

    $this->putJson("/api/admin/workspaces/{$shopId}", [
        'workspace_name' => 'Ramesh Kirana',
        'modules' => ['inbox', 'whatsapp', 'knowledge', 'customers'],
    ], $admin)
        ->assertOk()
        ->assertJsonPath('data.modules', ['inbox', 'whatsapp', 'knowledge', 'customers']);

    $this->getJson('/api/customers', $shop)->assertOk();
    $this->getJson('/api/workspace/me', $shop)
        ->assertJsonPath('data.modules', ['inbox', 'whatsapp', 'knowledge', 'customers'])
        ->assertJsonFragment(['customers.view'])
        ->assertJsonMissing(['members.view']);
    $this->getJson('/api/members', $shop)->assertForbidden();

    // ...and off again
    $this->putJson("/api/admin/workspaces/{$shopId}", [
        'workspace_name' => 'Ramesh Kirana',
        'modules' => ['inbox'],
    ], $admin)->assertOk();

    $this->getJson('/api/customers', $shop)->assertForbidden();
    $this->getJson('/api/knowledge-sources', $shop)->assertForbidden()->assertJsonPath('module', 'knowledge');
    $this->getJson('/api/conversations', $shop)->assertOk();
});

test('editing a shop without sending modules keeps the modules as they are', function () {
    [, $shopId] = selfSignedUpShop($this);
    $admin = superAdminHeaders();

    $this->putJson("/api/admin/workspaces/{$shopId}", ['workspace_name' => 'New Name'], $admin)
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.modules', ['inbox', 'whatsapp', 'knowledge']);
});

test('an unknown module name is refused', function () {
    [, $shopId] = selfSignedUpShop($this);

    $this->putJson("/api/admin/workspaces/{$shopId}", [
        'workspace_name' => 'Ramesh Kirana',
        'modules' => ['inbox', 'spaceship'],
    ], superAdminHeaders())
        ->assertUnprocessable()
        ->assertJsonValidationErrors('modules.1');
});

test('a shop made by the admin without choosing modules has every module, so old shops keep working', function () {
    $owner = registerOwner($this, 'old-shop@example.com');
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->getJson('/api/workspace/me', $headers)
        ->assertOk()
        ->assertJsonPath('data.modules', WorkspaceModule::values())
        ->assertJsonFragment(['customers.view'])
        ->assertJsonFragment(['members.view']);
    $this->getJson('/api/customers', $headers)->assertOk();
    $this->getJson('/api/members', $headers)->assertOk();
});

test('the admin can choose the modules when making a shop', function () {
    $admin = superAdminHeaders();

    $shopId = $this->postJson('/api/admin/workspaces', [
        'workspace_name' => 'Only Inbox',
        'owner_email' => 'inbox@shop.test',
        'owner_name' => 'Owner',
        'owner_password' => 'first-password-1',
        'modules' => ['inbox'],
    ], $admin)->assertCreated()->assertJsonPath('data.modules', ['inbox'])->json('data.id');

    $token = $this->postJson('/api/auth/login', ['email' => 'inbox@shop.test', 'password' => 'first-password-1'])
        ->assertOk()->json('data.access_token');

    $this->getJson('/api/conversations', headersFor($token, $shopId))->assertOk();
    $this->getJson('/api/channels', headersFor($token, $shopId))->assertForbidden();
});

test('the super admin is never blocked by the modules of a shop', function () {
    [, $shopId] = selfSignedUpShop($this);
    $admin = superAdminHeaders() + ['X-Workspace-Id' => (string) $shopId];

    $this->getJson('/api/customers', $admin)->assertOk();
    $this->getJson('/api/workspace/me', $admin)->assertJsonPath('data.modules', WorkspaceModule::values());
});
