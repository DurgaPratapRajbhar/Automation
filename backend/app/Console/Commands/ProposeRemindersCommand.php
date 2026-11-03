<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Agents\FinanceAgent;
use Illuminate\Console\Command;

class ProposeRemindersCommand extends Command
{
    protected $signature = 'agents:propose-reminders';

    protected $description = 'The Finance agent asks every business to send reminders for late payments (nothing is sent before the owner says yes)';

    public function handle(WorkspaceRepositoryInterface $workspaces, FinanceAgent $finance): int
    {
        $asked = $workspaces->allActive()->sum(fn ($workspace) => $finance->proposeReminders($workspace->id));

        $this->info("Reminders proposed: {$asked}");

        return self::SUCCESS;
    }
}
