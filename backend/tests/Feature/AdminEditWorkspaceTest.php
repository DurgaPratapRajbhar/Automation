<?php

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

// A super admin token plus one shop made through the admin API; returns [admin headers, shop id, owner user id]
function shopWithOwner(TestCase $test, string $ownerEmail = 'owner@shop.test'): array
{
    $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password123']);
    $admin->forceFill(['is_super_admin' => true])->save();
    $headers = ['Authorization' => 'Bearer '.tokenFor($admin->id)];

    $shopId = $test->postJson('/api/admin/workspaces', [
        'workspace_name' => 'Old Name',
        'owner_email' => $ownerEmail,
        'owner_name' => 'Owner',
        'owner_password' => 'first-password-1',
    ], $headers)->assertCreated()->json('data.id');

    return [$headers, $shopId, User::where('email', $ownerEmail)->value('id')];
}

test('admin sees a shop with its people and can rename it', function () {
    [$admin, $shopId, $ownerId] = shopWithOwner($this);

    $this->getJson("/api/admin/workspaces/{$shopId}", $admin)
        ->assertOk()
        ->assertJsonPath('data.name', 'Old Name')
        ->assertJsonPath('data.members.0.id', $ownerId)
        ->assertJsonPath('data.members.0.email', 'owner@shop.test')
        ->assertJsonPath('data.members.0.role', 'Owner')
        ->assertJsonMissingPath('data.members.0.password');

    $this->putJson("/api/admin/workspaces/{$shopId}", ['workspace_name' => 'Indian Solar Light Centre'], $admin)
        ->assertOk()
        ->assertJsonPath('data.name', 'Indian Solar Light Centre');

    $this->putJson("/api/admin/workspaces/{$shopId}", ['workspace_name' => ''], $admin)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('workspace_name');

    $this->getJson('/api/admin/workspaces/99999', $admin)->assertNotFound();
});

test('admin sets a new owner password: the new one works, the old one and old sessions do not', function () {
    [$admin, $shopId, $ownerId] = shopWithOwner($this);

    $login = fn (string $password) => $this->postJson('/api/auth/login', ['email' => 'owner@shop.test', 'password' => $password]);

    $loggedIn = $login('first-password-1')->assertOk();
    $oldRefresh = $loggedIn->json('data.refresh_token');
    expect(RefreshToken::where('user_id', $ownerId)->whereNull('revoked_at')->count())->toBeGreaterThan(0);

    $this->putJson("/api/admin/workspaces/{$shopId}/members/{$ownerId}/password", [
        'password' => 'brand-new-password-2',
        'password_confirmation' => 'brand-new-password-2',
    ], $admin)->assertOk()->assertJsonPath('message', 'Password changed. The user must log in again.');

    // The app answers a wrong password with a validation error on the email field
    $login('first-password-1')->assertUnprocessable()->assertJsonValidationErrors('email');
    $login('brand-new-password-2')->assertOk();

    $this->postJson('/api/auth/refresh', ['refresh_token' => $oldRefresh])->assertUnauthorized();
});

test('the new password must be long enough and typed twice the same', function () {
    [$admin, $shopId, $ownerId] = shopWithOwner($this);
    $url = "/api/admin/workspaces/{$shopId}/members/{$ownerId}/password";

    $this->putJson($url, ['password' => 'short', 'password_confirmation' => 'short'], $admin)
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->putJson($url, ['password' => 'long-enough-password', 'password_confirmation' => 'another-long-password'], $admin)
        ->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->postJson('/api/auth/login', ['email' => 'owner@shop.test', 'password' => 'first-password-1'])->assertOk();
});

test('only a member of that shop can be changed, never a super admin', function () {
    [$admin, $shopId] = shopWithOwner($this, 'owner@shop.test');
    $otherOwnerId = (function () use ($admin) {
        $this->postJson('/api/admin/workspaces', [
            'workspace_name' => 'Other', 'owner_email' => 'other@shop.test', 'owner_name' => 'Other', 'owner_password' => 'other-password-1',
        ], $admin)->assertCreated();

        return User::where('email', 'other@shop.test')->value('id');
    })->call($this);
    $adminId = User::where('email', 'admin@example.com')->value('id');
    $body = ['password' => 'long-enough-password', 'password_confirmation' => 'long-enough-password'];

    // A user of another shop is not found in this one
    $this->putJson("/api/admin/workspaces/{$shopId}/members/{$otherOwnerId}/password", $body, $admin)->assertNotFound();
    $this->postJson('/api/auth/login', ['email' => 'other@shop.test', 'password' => 'other-password-1'])->assertOk();

    // The super admin is not a member, so it cannot be reached through a shop either
    $this->putJson("/api/admin/workspaces/{$shopId}/members/{$adminId}/password", $body, $admin)->assertNotFound();
});

test('a shop owner cannot use the admin edit routes', function () {
    [, $shopId, $ownerId] = shopWithOwner($this);
    $owner = ['Authorization' => 'Bearer '.tokenFor($ownerId)];

    $this->getJson("/api/admin/workspaces/{$shopId}", $owner)->assertForbidden();
    $this->putJson("/api/admin/workspaces/{$shopId}", ['workspace_name' => 'Hacked'], $owner)->assertForbidden();
    $this->putJson("/api/admin/workspaces/{$shopId}/members/{$ownerId}/password", ['password' => 'long-enough-password', 'password_confirmation' => 'long-enough-password'], $owner)->assertForbidden();
});
