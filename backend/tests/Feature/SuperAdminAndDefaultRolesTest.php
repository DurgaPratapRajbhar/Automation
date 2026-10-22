<?php

use App\Enums\Permission;
use App\Models\Permission as PermissionModel;
use App\Models\User;
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

test('new workspace gets owner, manager and staff roles from config', function () {
    $owner = registerOwner($this);

    $roles = collect($this->getJson('/api/roles', headersFor($owner['token'], $owner['workspace_id']))->json('data'));

    expect($roles->pluck('slug')->all())->toBe(['owner', 'manager', 'staff'])
        ->and($roles->firstWhere('slug', 'owner')['permissions'])->toHaveCount(count(Permission::cases()))
        ->and($roles->firstWhere('slug', 'manager')['permissions'])->not->toContain(Permission::RolesDelete->value)
        ->and($roles->firstWhere('slug', 'staff')['permissions'])->toContain(Permission::ConversationsReply->value);
});

test('unknown permission names are rejected when creating a role', function () {
    $owner = registerOwner($this);

    $this->postJson('/api/roles', ['name' => 'Bad', 'permissions' => ['does.not-exist']], headersFor($owner['token'], $owner['workspace_id']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['permissions.0']);
});

test('permissions sync command matches the enum and gives owners new permissions', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    // Simulate a permission that was removed from the enum and one that is new
    PermissionModel::create(['name' => 'old.permission']);
    PermissionModel::where('name', Permission::CustomersDelete->value)->delete();

    $this->artisan('permissions:sync')->assertSuccessful();

    expect(PermissionModel::pluck('name')->sort()->values()->all())
        ->toBe(collect(Permission::values())->sort()->values()->all());

    $ownerRole = collect($this->getJson('/api/roles', $headers)->json('data'))->firstWhere('slug', 'owner');
    expect($ownerRole['permissions'])->toContain(Permission::CustomersDelete->value);
});

test('super admin can use any workspace without being a member', function () {
    $owner = registerOwner($this);
    $headers = headersFor(makeSuperAdmin(), $owner['workspace_id']);

    $this->getJson('/api/members', $headers)->assertOk();
    $this->postJson('/api/roles', ['name' => 'Agent', 'permissions' => [Permission::MembersView->value]], $headers)
        ->assertCreated();

    $me = $this->getJson('/api/workspace/me', $headers)->assertOk();
    expect($me->json('data.is_super_admin'))->toBeTrue()
        ->and($me->json('data.role'))->toBeNull()
        ->and($me->json('data.permissions'))->toHaveCount(count(Permission::cases()));
});

test('super admin can list and suspend workspaces; suspended workspace is blocked', function () {
    $owner = registerOwner($this);
    $adminHeaders = ['Authorization' => 'Bearer ' . makeSuperAdmin()];

    $this->getJson('/api/admin/workspaces', $adminHeaders)
        ->assertOk()
        ->assertJsonPath('data.0.id', $owner['workspace_id']);

    $this->patchJson("/api/admin/workspaces/{$owner['workspace_id']}/status", ['status' => 'suspended'], $adminHeaders)
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    $this->getJson('/api/members', headersFor($owner['token'], $owner['workspace_id']))
        ->assertForbidden()
        ->assertJsonPath('message', 'This workspace is suspended.');

    $this->patchJson("/api/admin/workspaces/{$owner['workspace_id']}/status", ['status' => 'wrong'], $adminHeaders)
        ->assertUnprocessable();
});

test('admin routes are closed to normal users, even owners', function () {
    $owner = registerOwner($this);

    $this->getJson('/api/admin/workspaces', ['Authorization' => "Bearer {$owner['token']}"])
        ->assertForbidden();
});

test('make-super-admin command grants and revokes access', function () {
    $owner = registerOwner($this);

    $this->artisan('app:make-super-admin', ['email' => 'owner@example.com'])->assertSuccessful();
    expect(User::find($owner['user_id'])->isSuperAdmin())->toBeTrue();

    $this->artisan('app:make-super-admin', ['email' => 'owner@example.com', '--revoke' => true])->assertSuccessful();
    expect(User::find($owner['user_id'])->isSuperAdmin())->toBeFalse();

    $this->artisan('app:make-super-admin', ['email' => 'nobody@example.com'])->assertFailed();
});

test('is_super_admin cannot be set through registration', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Hacker',
        'email' => 'h@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'workspace_name' => 'H',
        'is_super_admin' => true,
    ])->assertCreated();

    expect(User::where('email', 'h@example.com')->first()->isSuperAdmin())->toBeFalse();
});
