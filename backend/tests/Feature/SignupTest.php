<?php

use App\Models\User;
use App\Models\Workspace;
use App\Services\Contracts\AiResponderInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    config(['services.google.client_id' => 'GOOGLE_CLIENT', 'trial.days' => 7, 'trial.ai_daily_limit' => 2]);
});

function signupData(array $override = []): array
{
    return [
        'name' => 'Ramesh',
        'shop_name' => 'Ramesh Kirana',
        'email' => 'ramesh@shop.test',
        'password' => 'a-long-password-1',
        'password_confirmation' => 'a-long-password-1',
        'accepted_terms' => true,
        ...$override,
    ];
}

// What Google's tokeninfo says about the person behind the token
function googleSays(array $claims = []): void
{
    Http::fake(['oauth2.googleapis.com/*' => Http::response([
        'aud' => 'GOOGLE_CLIENT',
        'iss' => 'https://accounts.google.com',
        'sub' => 'google-sub-1',
        'email' => 'Sita@Gmail.com',
        'email_verified' => 'true',
        'name' => 'Sita Devi',
        ...$claims,
    ])]);
}

test('signing up needs the Terms and Privacy Policy to be accepted', function () {
    $this->postJson('/api/auth/signup', signupData(['accepted_terms' => false]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('accepted_terms');

    $this->postJson('/api/auth/signup', array_diff_key(signupData(), ['accepted_terms' => 1]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('accepted_terms');

    expect(User::count())->toBe(0);
});

test('a shopkeeper signs up, gets a shop with a 7 day trial and can log in', function () {
    $response = $this->postJson('/api/auth/signup', signupData())
        ->assertCreated()
        ->assertJsonPath('data.user.email', 'ramesh@shop.test')
        ->assertJsonPath('data.workspaces.0.name', 'Ramesh Kirana')
        ->assertJsonPath('data.workspaces.0.role.is_owner', true);

    $shop = Workspace::first();
    expect((int) round(now()->diffInDays($shop->trial_ends_at)))->toBe(7)
        ->and($shop->trialDaysLeft())->toBe(7);

    $this->postJson('/api/auth/login', ['email' => 'ramesh@shop.test', 'password' => 'a-long-password-1'])->assertOk();

    $headers = headersFor($response->json('data.access_token'), $shop->id);
    $this->getJson('/api/workspace/me', $headers)
        ->assertOk()
        ->assertJsonPath('data.workspace.trial_days_left', 7);
});

test('signup refuses a taken email, a weak password and a mismatch', function () {
    $this->postJson('/api/auth/signup', signupData())->assertCreated();

    $this->postJson('/api/auth/signup', signupData())->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->postJson('/api/auth/signup', signupData(['email' => 'new@shop.test', 'password' => 'short', 'password_confirmation' => 'short']))
        ->assertUnprocessable()->assertJsonValidationErrors('password');
    $this->postJson('/api/auth/signup', signupData(['email' => 'new@shop.test', 'password_confirmation' => 'different-password']))
        ->assertUnprocessable()->assertJsonValidationErrors('password');

    expect(Workspace::count())->toBe(1);
});

test('the old way still works: a shop made by the admin has no trial', function () {
    $owner = registerOwner($this);

    expect(Workspace::find($owner['workspace_id'])->trial_ends_at)->toBeNull();

    $this->getJson('/api/workspace/me', headersFor($owner['token'], $owner['workspace_id']))
        ->assertOk()
        ->assertJsonPath('data.workspace.trial_days_left', null);
});

test('a new person signing in with Google gets an account, a shop and a trial', function () {
    googleSays();

    $this->postJson('/api/auth/google', ['credential' => 'TOKEN'])
        ->assertOk()
        ->assertJsonPath('data.user.email', 'sita@gmail.com')
        ->assertJsonPath('data.workspaces.0.name', "Sita Devi's shop")
        ->assertJsonPath('data.workspaces.0.role.is_owner', true);

    $user = User::where('email', 'sita@gmail.com')->first();
    expect($user->google_id)->toBe('google-sub-1')
        ->and($user->has_password)->toBeFalse()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Workspace::first()->trialDaysLeft())->toBe(7);

    // The next time it is the same account, not a second shop
    $this->postJson('/api/auth/google', ['credential' => 'TOKEN'])->assertOk();
    expect(User::count())->toBe(1)->and(Workspace::count())->toBe(1);
});

test('Google sign-in is refused when the token is not ours, not verified or not valid', function () {
    foreach ([['aud' => 'SOMEONE_ELSES_APP'], ['email_verified' => 'false'], ['iss' => 'https://evil.example']] as $claims) {
        googleSays($claims);
        $this->postJson('/api/auth/google', ['credential' => 'TOKEN'])->assertUnauthorized();
    }

    Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_token'], 400)]);
    $this->postJson('/api/auth/google', ['credential' => 'TOKEN'])->assertUnauthorized();

    config(['services.google.client_id' => null]);
    $this->postJson('/api/auth/google', ['credential' => 'TOKEN'])->assertStatus(503);

    expect(User::count())->toBe(0);
});

test('Google joins an existing account by email; an account nobody verified loses its password', function () {
    // Someone registered this email with a password, never proving it is theirs
    $this->postJson('/api/auth/signup', signupData(['email' => 'sita@gmail.com']))->assertCreated();
    $oldLogin = $this->postJson('/api/auth/login', ['email' => 'sita@gmail.com', 'password' => 'a-long-password-1'])->assertOk();

    googleSays();
    $this->postJson('/api/auth/google', ['credential' => 'TOKEN'])->assertOk();

    // Same account and shop, and the unverified password stopped working
    expect(User::count())->toBe(1)->and(Workspace::count())->toBe(1);
    $this->postJson('/api/auth/login', ['email' => 'sita@gmail.com', 'password' => 'a-long-password-1'])->assertUnprocessable();
    $this->postJson('/api/auth/refresh', ['refresh_token' => $oldLogin->json('data.refresh_token')])->assertUnauthorized();
});

test('a super admin cannot come in through Google', function () {
    $admin = User::create(['name' => 'Admin', 'email' => 'sita@gmail.com', 'password' => 'password123']);
    $admin->forceFill(['is_super_admin' => true])->save();

    googleSays();
    $this->postJson('/api/auth/google', ['credential' => 'TOKEN'])->assertUnprocessable();
});

test('change password: needs the current one, a Google-only user can set a first one', function () {
    $session = $this->postJson('/api/auth/signup', signupData())->json('data');
    $headers = ['Authorization' => 'Bearer '.$session['access_token']];
    $new = ['password' => 'another-long-pass-2', 'password_confirmation' => 'another-long-pass-2'];

    $this->putJson('/api/auth/password', ['current_password' => 'wrong', ...$new], $headers)
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->putJson('/api/auth/password', ['current_password' => 'a-long-password-1', 'password' => 'short', 'password_confirmation' => 'short'], $headers)
        ->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->putJson('/api/auth/password', ['current_password' => 'a-long-password-1', ...$new], $headers)->assertOk();
    $this->postJson('/api/auth/login', ['email' => 'ramesh@shop.test', 'password' => 'another-long-pass-2'])->assertOk();
    $this->postJson('/api/auth/login', ['email' => 'ramesh@shop.test', 'password' => 'a-long-password-1'])->assertUnprocessable();

    // A Google-only account has no password yet, so no current one is asked
    googleSays();
    $google = $this->postJson('/api/auth/google', ['credential' => 'TOKEN'])->json('data');
    $this->putJson('/api/auth/password', $new, ['Authorization' => 'Bearer '.$google['access_token']])->assertOk();
    $this->postJson('/api/auth/login', ['email' => 'sita@gmail.com', 'password' => 'another-long-pass-2'])->assertOk();
});

test('when the trial ends the shop is locked with a clear message, and its key stops working', function () {
    $session = $this->postJson('/api/auth/signup', signupData())->json('data');
    $shop = Workspace::first();
    $headers = headersFor($session['access_token'], $shop->id);
    $key = $this->postJson('/api/workspace/api-key', [], $headers)->assertCreated()->json('data.key');

    $this->getJson('/api/workspace/me', $headers)->assertOk();

    $shop->update(['trial_ends_at' => now()->subMinute()]);

    $this->getJson('/api/workspace/me', $headers)
        ->assertForbidden()
        ->assertJsonPath('message', 'Your free trial has ended. Message ScriptDP on WhatsApp 83569 74324 to continue.');
    $this->postJson('/api/v1/ask', ['question' => 'hi'], ['X-Api-Key' => $key])->assertUnauthorized();

    // The admin can give more time, or clear the trial, and the shop opens again
    $shop->update(['trial_ends_at' => null]);
    $this->getJson('/api/workspace/me', $headers)->assertOk();
});

test('a shop on trial gets a limited number of AI answers a day, a paid shop does not', function () {
    app()->bind(AiResponderInterface::class, fn () => new class implements AiResponderInterface
    {
        public function answer(int $workspaceId, string $question, string $replyStyle = 'auto'): ?string
        {
            return 'ok';
        }
    });

    $session = $this->postJson('/api/auth/signup', signupData())->json('data');
    $shop = Workspace::first();
    $key = $this->postJson('/api/workspace/api-key', [], headersFor($session['access_token'], $shop->id))->json('data.key');
    $ask = fn () => $this->postJson('/api/v1/ask', ['question' => 'hi'], ['X-Api-Key' => $key]);

    // The limit is 2 a day in this test
    $ask()->assertOk();
    $ask()->assertOk();
    $ask()->assertStatus(429)->assertJsonPath('message', 'The daily limit of answers is reached. Please try again tomorrow.');

    // No trial = paid: no limit
    $shop->update(['trial_ends_at' => null]);
    $ask()->assertOk();
    $ask()->assertOk();
    $ask()->assertOk();
});
