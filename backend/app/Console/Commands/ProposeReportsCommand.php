<?php

namespace App\Console\Commands;

use App\Repositories\Contracts\WorkspaceRepositoryInterface;
use App\Services\Agents\Operations\OperationsRoutines;
use Illuminate\Console\Command;

class ProposeReportsCommand extends Command
{
    protected $signature = 'agents:propose-reports';

    protected $description = 'The Operations agent asks every business to send its daily report to the owner (nothing is sent before the owner says yes)';

    public function handle(WorkspaceRepositoryInterface $workspaces, OperationsRoutines $routines): int
    {
        $asked = $workspaces->allActive()->filter(fn ($workspace) => $routines->proposeReport($workspace->id))->count();

        $this->info("Reports proposed: {$asked}");

        return self::SUCCESS;
    }
}
