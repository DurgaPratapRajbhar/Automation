<?php

use App\Models\User;
use App\Services\RoutePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

test('owner can do everything and sees all permissions', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $me = $this->getJson('/api/workspace/me', $headers)->assertOk();

    expect($me->json('data.role.is_owner'))->toBeTrue()
        ->and($me->json('data.permissions'))->toHaveCount(count(app(RoutePermissionResolver::class)->all()));

    $this->getJson('/api/roles', $headers)->assertOk();
    $this->getJson('/api/members', $headers)->assertOk();
    $this->getJson('/api/permissions', $headers)->assertOk();
});

test('view-only member can only view members', function () {
    $owner = registerOwner($this);
    $viewer = memberWithPermissions($this, $owner, 'Viewer', ['members.view'], 'viewer@example.com');
    $headers = headersFor($viewer['token'], $viewer['workspace_id']);

    expect($this->getJson('/api/workspace/me', $headers)->json('data.permissions'))
        ->toBe(['members.view']);

    $this->getJson('/api/members', $headers)->assertOk();

    $this->getJson('/api/roles', $headers)->assertForbidden();
    $this->postJson('/api/members', [
        'email' => 'x@example.com', 'name' => 'X', 'password' => 'password123', 'role_id' => $viewer['role_id'],
    ], $headers)->assertForbidden();
    $this->putJson("/api/members/{$owner['user_id']}", ['role_id' => $viewer['role_id']], $headers)->assertForbidden();
    $this->deleteJson("/api/members/{$owner['user_id']}", [], $headers)->assertForbidden();
    $this->postJson('/api/channels/send-message', [], $headers)->assertForbidden();
});

test('member with edit permission can change roles but not delete', function () {
    $owner = registerOwner($this);
    $editor = memberWithPermissions($this, $owner, 'Editor', ['members.view', 'members.edit'], 'editor@example.com');
    $viewer = memberWithPermissions($this, $owner, 'Viewer', ['members.view'], 'viewer@example.com');
    $headers = headersFor($editor['token'], $editor['workspace_id']);

    // Viewer ko Editor role de sakta hai (Editor ki permissions uske paas hain)
    $this->putJson("/api/members/{$viewer['user_id']}", ['role_id' => $editor['role_id']], $headers)
        ->assertOk()
        ->assertJsonPath('data.role.slug', 'editor');

    $this->deleteJson("/api/members/{$viewer['user_id']}", [], $headers)->assertForbidden();
});

test('nobody can escalate their own privileges', function () {
    $owner = registerOwner($this);
    $ownerRoleId = $this->getJson('/api/workspace/me', headersFor($owner['token'], $owner['workspace_id']))
        ->json('data.role.id');

    $manager = memberWithPermissions(
        $this, $owner, 'Team Lead',
        ['roles.view', 'roles.create', 'roles.edit', 'members.view', 'members.edit'],
        'manager@example.com'
    );
    $headers = headersFor($manager['token'], $manager['workspace_id']);

    // Apne paas jo permission nahi, wo naye role me nahi de sakta
    $this->postJson('/api/roles', ['name' => 'Super', 'permissions' => ['members.delete']], $headers)
        ->assertForbidden();

    // Apna hi role edit nahi kar sakta
    $this->putJson("/api/roles/{$manager['role_id']}", ['permissions' => ['members.delete']], $headers)
        ->assertForbidden();

    // Owner role edit nahi kar sakta
    $this->putJson("/api/roles/{$ownerRoleId}", ['name' => 'Hacked'], $headers)
        ->assertForbidden();

    // Apna role khud nahi badal sakta
    $this->putJson("/api/members/{$manager['user_id']}", ['role_id' => $ownerRoleId], $headers)
        ->assertForbidden();

    // Owner ko demote nahi kar sakta
    $this->putJson("/api/members/{$owner['user_id']}", ['role_id' => $manager['role_id']], $headers)
        ->assertForbidden();

    // Apne paas jitni permissions hain usse kam wala role bana sakta hai
    $this->postJson('/api/roles', ['name' => 'Junior', 'permissions' => ['members.view']], $headers)
        ->assertCreated();
});

test('workspace always keeps at least one owner', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $this->deleteJson("/api/members/{$owner['user_id']}", [], $headers)->assertForbidden();

    // Doosra owner add karo -> ab pehla owner demote/remove ho sakta hai
    $ownerRoleId = $this->getJson('/api/workspace/me', $headers)->json('data.role.id');

    $secondOwnerId = $this->postJson('/api/members', [
        'email' => 'owner2@example.com', 'name' => 'Owner 2', 'password' => 'password123', 'role_id' => $ownerRoleId,
    ], $headers)->assertCreated()->json('data.id');

    $this->deleteJson("/api/members/{$owner['user_id']}", [], headersFor(tokenFor($secondOwnerId), $owner['workspace_id']))
        ->assertOk();
});

test('roles and members are isolated per workspace', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');

    $roleInB = $this->postJson('/api/roles', ['name' => 'Agent', 'permissions' => []], headersFor($ownerB['token'], $ownerB['workspace_id']))
        ->json('data.id');

    $headersA = headersFor($ownerA['token'], $ownerA['workspace_id']);

    // Doosre workspace ka role assign nahi ho sakta
    $this->postJson('/api/members', [
        'email' => 'x@example.com', 'name' => 'X', 'password' => 'password123', 'role_id' => $roleInB,
    ], $headersA)->assertUnprocessable();

    // Doosre workspace ka role dikh / edit / delete nahi hota
    $this->getJson("/api/roles/{$roleInB}", $headersA)->assertNotFound();
    $this->putJson("/api/roles/{$roleInB}", ['name' => 'X'], $headersA)->assertNotFound();
    $this->deleteJson("/api/roles/{$roleInB}", [], $headersA)->assertNotFound();

    // Doosre workspace ka member nahi hata sakta
    $this->deleteJson("/api/members/{$ownerB['user_id']}", [], $headersA)->assertNotFound();

    // Jis workspace ka member nahi, uska header bhejne par 403
    $this->getJson('/api/roles', headersFor($ownerA['token'], $ownerB['workspace_id']))->assertForbidden();
});

test('role in use cannot be deleted until members are moved', function () {
    $owner = registerOwner($this);
    $headers = headersFor($owner['token'], $owner['workspace_id']);

    $agent = memberWithPermissions($this, $owner, 'Agent', ['customers.view'], 'agent@example.com');
    $otherRoleId = $this->postJson('/api/roles', ['name' => 'Support', 'permissions' => []], $headers)->json('data.id');

    $this->deleteJson("/api/roles/{$agent['role_id']}", [], $headers)->assertUnprocessable();

    $this->putJson("/api/members/{$agent['user_id']}", ['role_id' => $otherRoleId], $headers)->assertOk();

    $this->deleteJson("/api/roles/{$agent['role_id']}", [], $headers)->assertOk();
});

test('existing user can be added to another workspace without password', function () {
    $ownerA = registerOwner($this, 'a@example.com');
    $ownerB = registerOwner($this, 'b@example.com');
    $headersB = headersFor($ownerB['token'], $ownerB['workspace_id']);

    $roleId = $this->postJson('/api/roles', ['name' => 'Viewer', 'permissions' => ['members.view']], $headersB)
        ->json('data.id');

    $this->postJson('/api/members', ['email' => 'a@example.com', 'role_id' => $roleId], $headersB)
        ->assertCreated();

    $this->postJson('/api/members', ['email' => 'a@example.com', 'role_id' => $roleId], $headersB)
        ->assertUnprocessable();

    expect(User::where('email', 'a@example.com')->count())->toBe(1);
});
