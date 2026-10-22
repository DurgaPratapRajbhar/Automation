<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

// Super admins are created only from the server, never through the API
class MakeSuperAdminCommand extends Command
{
    protected $signature = 'app:make-super-admin {email} {--revoke : Remove super admin access}';

    protected $description = 'Give (or remove) super admin access for an existing user';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('email'))->first();

        if (!$user) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $user->forceFill(['is_super_admin' => !$this->option('revoke')])->save();

        $this->info($this->option('revoke') ? 'Super admin access removed.' : 'User is now a super admin.');

        return self::SUCCESS;
    }
}
