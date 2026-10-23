<?php

use App\Models\Permission;
use App\Models\User;
use App\Services\RoutePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

function makeSuperAdmin(string $email = 'admin@example.com'): string
{
    $user = User::create(['name' => 'Admin', 'email' => $email, 'password' => 'password123']);
    $user->forceFill(['is_super_admin' => true])->save();

    return tokenFor($user->id);
}

function adminHeaders(string $token): array
{
    return ['Authorization' => "Bearer {$token}"];
}

test('super admin manages role templates and new workspaces copy them', function () {
    $admin = adminHeaders(makeSuperAdmin());

    $templateId = $this->postJson('/api/admin/role-templates', [
        'name' => 'Staff',
        'permissions' => ['customers.view', 'customers.create'],
    ], $admin)->assertCreated()->assertJsonPath('data.slug', 'staff')->json('data.id');

    $this->putJson("/api/admin/role-templates/{$templateId}", ['permissions' => ['customers.view']], $admin)
        ->assertOk()
        ->assertJsonPath('data.permissions', ['customers.view']);

    $this->getJson('/api/admin/role-templates', $admin)->assertOk()->assertJsonCount(1, 'data');

    $owner = registerOwner($this);
    $roles = collect($this->getJson('/api/roles', headersFor($owner['token'], $owner['workspace_id']))->json('data'));

    expect($roles->pluck('slug')->all())->toBe(['owner', 'staff'])
        ->and($roles->firstWhere('slug', 'owner')['permissions'])->toHaveCount(Permission::count())
        ->and($roles->firstWhere('slug', 'staff')['permissions'])->toBe(['customers.view']);

    $this->deleteJson("/api/admin/role-templates/{$templateId}", [], $admin)->assertOk();
    $this->getJson('/api/admin/role-templates', $admin)->assertJsonCount(0, 'data');
});

test('role templates reject unknown permissions and the owner name', function () {
    $admin = adminHeaders(makeSuperAdmin());

    $this->postJson('/api/admin/role-templates', ['name' => 'Bad', 'permissions' => ['does.not-exist']], $admin)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.0']);

    $this->postJson('/api/admin/role-templates', ['name' => 'Owner', 'permissions' => []], $admin)
        ->assertUnprocessable();
});

test('permission tree is grouped by module', function () {
    $owner = registerOwner($this);

    $tree = collect($this->getJson('/api/permissions', headersFor($owner['token'], $owner['workspace_id']))
        ->assertOk()
        ->json('data'));

    $customers = $tree->firstWhere('module', 'customers');

    expect(collect($customers['permissions'])->pluck('name')->all())
        ->toBe(['customers.create', 'customers.delete', 'customers.edit', 'customers.view']);
});

test('permissions sync matches the routes and gives owners new permissions', function () {
    $owner = registerOwner($this);

    // Simulate a permission whose route was deleted and one that is new
    Permission::create(['name' => 'old.view', 'module' => 'old']);
    Permission::where('name', 'customers.delete')->delete();

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::pluck('name')->sort()->values()->all())
        ->toBe(collect(app(RoutePermissionResolver::class)->all())->sort()->values()->all());

    $ownerRole = collect($this->getJson('/api/roles', headersFor($owner['token'], $owner['workspace_id']))->json('data'))
        ->firstWhere('slug', 'owner');
    expect($ownerRole['permissions'])->toContain('customers.delete');
});

test('super admin can use any workspace without being a member', function () {
    $owner = registerOwner($this);
    $headers = headersFor(makeSuperAdmin(), $owner['workspace_id']);

    $this->getJson('/api/members', $headers)->assertOk();
    $this->postJson('/api/roles', ['name' => 'Agent', 'permissions' => ['members.view']], $headers)->assertCreated();

    $me = $this->getJson('/api/workspace/me', $headers)->assertOk();
    expect($me->json('data.is_super_admin'))->toBeTrue()
        ->and($me->json('data.role'))->toBeNull()
        ->and($me->json('data.permissions'))->toHaveCount(Permission::count());
});

test('super admin can list and suspend workspaces; suspended workspace is blocked', function () {
    $owner = registerOwner($this);
    $admin = adminHeaders(makeSuperAdmin());

    $this->getJson('/api/admin/workspaces', $admin)->assertOk()->assertJsonPath('data.0.id', $owner['workspace_id']);

    $this->patchJson("/api/admin/workspaces/{$owner['workspace_id']}/status", ['status' => 'suspended'], $admin)
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    $this->getJson('/api/members', headersFor($owner['token'], $owner['workspace_id']))
        ->assertForbidden()
        ->assertJsonPath('message', 'This workspace is suspended.');

    $this->patchJson("/api/admin/workspaces/{$owner['workspace_id']}/status", ['status' => 'wrong'], $admin)
        ->assertUnprocessable();
});

test('admin routes are closed to normal users, even owners', function () {
    $owner = registerOwner($this);

    $this->getJson('/api/admin/workspaces', adminHeaders($owner['token']))->assertForbidden();
    $this->getJson('/api/admin/role-templates', adminHeaders($owner['token']))->assertForbidden();
    $this->getJson('/api/admin/permissions', adminHeaders($owner['token']))->assertForbidden();
});

test('super admin gets the permission tree without a workspace, and auth/me shows the flag', function () {
    $admin = adminHeaders(makeSuperAdmin());

    $this->getJson('/api/admin/permissions', $admin)->assertOk()->assertJsonStructure(['data' => [['module', 'permissions']]]);
    $this->getJson('/api/auth/me', $admin)->assertOk()->assertJsonPath('data.user.is_super_admin', true);
});

test('make-super-admin command grants and revokes access', function () {
    $owner = registerOwner($this);

    $this->artisan('app:make-super-admin', ['email' => 'owner@example.com'])->assertSuccessful();
    expect(User::find($owner['user_id'])->isSuperAdmin())->toBeTrue();

    $this->artisan('app:make-super-admin', ['email' => 'owner@example.com', '--revoke' => true])->assertSuccessful();
    expect(User::find($owner['user_id'])->isSuperAdmin())->toBeFalse();

    $this->artisan('app:make-super-admin', ['email' => 'nobody@example.com'])->assertFailed();
});

test('public registration is closed: only the super admin creates shops', function () {
    $this->postJson('/api/auth/register', ['email' => 'h@example.com'])->assertNotFound();

    $owner = registerOwner($this);
    $this->postJson('/api/admin/workspaces', ['workspace_name' => 'X', 'owner_email' => 'x@example.com'], adminHeaders($owner['token']))
        ->assertForbidden();
});

test('super admin creates a shop with a new owner who can then log in', function () {
    $admin = adminHeaders(makeSuperAdmin());

    $this->postJson('/api/admin/workspaces', [
        'workspace_name' => 'Sharma Kirana',
        'owner_name' => 'Sharma',
        'owner_email' => 'sharma@example.com',
        'owner_password' => 'password123',
    ], $admin)->assertCreated()->assertJsonPath('data.name', 'Sharma Kirana')->assertJsonPath('data.members_count', 1);

    $this->postJson('/api/auth/login', ['email' => 'sharma@example.com', 'password' => 'password123'])
        ->assertOk()
        ->assertJsonPath('data.workspaces.0.name', 'Sharma Kirana')
        ->assertJsonPath('data.workspaces.0.role.is_owner', true);
});

test('an existing user can own a second shop, but a super admin cannot own one', function () {
    $admin = adminHeaders(makeSuperAdmin());
    registerOwner($this);

    $this->postJson('/api/admin/workspaces', ['workspace_name' => 'Second', 'owner_email' => 'owner@example.com'], $admin)
        ->assertCreated();

    $this->postJson('/api/admin/workspaces', ['workspace_name' => 'Nope', 'owner_email' => 'admin@example.com'], $admin)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A super admin account cannot own a shop. Use a separate email.');

    $this->postJson('/api/admin/workspaces', ['workspace_name' => 'Nope', 'owner_email' => 'new@example.com'], $admin)
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Owner name and password are needed to create a new owner account.');
});
