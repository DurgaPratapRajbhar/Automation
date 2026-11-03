<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Agents\Operations\OperationsRoutines;
use Illuminate\Console\Command;

class ProposeBookingFollowUpsCommand extends Command
{
    protected $signature = 'agents:propose-booking-followups';

    protected $description = 'The Operations agent suggests reminders for coming bookings and feedback requests for finished ones (sent only after the owner says yes, or at once if the owner switched on the standing yes)';

    public function handle(WorkspaceRepositoryInterface $workspaces, OperationsRoutines $routines): int
    {
        $suggested = $workspaces->allActive()->sum(fn ($workspace) => $routines->proposeFollowUps($workspace->id));

        $this->info("Follow-ups suggested: {$suggested}");

        return self::SUCCESS;
    }
}
