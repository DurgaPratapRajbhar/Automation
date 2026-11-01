<?php

namespace App\Services;

use App\Repositories\Contracts\RefreshTokenRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// A shopkeeper makes their own account and shop, and gets a free trial. Admin-made shops stay as they were.
class SignupService
{
    public function __construct(
        private UserRepositoryInterface $users,
        private WorkspaceSetupService $setup,
        private AuthService $auth,
        private GoogleIdentity $google,
        private RefreshTokenRepositoryInterface $refreshTokens
    ) {}

    public function register(array $data): array
    {
        if ($this->users->findByEmail($data['email'])) {
            throw ValidationException::withMessages(['email' => [__('auth.email_taken')]]);
        }

        $this->setup->create([
            'workspace_name' => $data['shop_name'],
            'owner_email' => $data['email'],
            'owner_name' => $data['name'],
            'owner_password' => $data['password'],
            'trial_ends_at' => $this->trialEnd(),
        ]);

        return $this->auth->issueSession($this->users->findByEmail($data['email']));
    }

    // Signs in with Google; a person we have not seen yet gets an account, a shop and a trial
    public function withGoogle(string $credential): array
    {
        $person = $this->google->verify($credential);

        $user = $this->users->findByGoogleId($person['sub']) ?? $this->users->findByEmail($person['email']);

        if ($user) {
            // The admin account has its own door
            if ($user->isSuperAdmin()) {
                throw ValidationException::withMessages(['credential' => [__('auth.failed')]]);
            }

            if (! $user->google_id) {
                $this->users->linkGoogle($user, $person['sub']);
                $this->refreshTokens->revokeAllForUser($user->id);
            }

            return $this->auth->issueSession($user);
        }

        $user = $this->users->create([
            'name' => $person['name'],
            'email' => $person['email'],
            'password' => Str::random(40),
            'google_id' => $person['sub'],
            'has_password' => false,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->setup->create([
            'workspace_name' => __('auth.default_shop_name', ['name' => $person['name']]),
            'owner_email' => $person['email'],
            'trial_ends_at' => $this->trialEnd(),
        ]);

        return $this->auth->issueSession($user);
    }

    private function trialEnd(): \Illuminate\Support\Carbon
    {
        return now()->addDays(config('trial.days'));
    }
}
