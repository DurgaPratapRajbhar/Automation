<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class MemberRoleChanged
{
    use Dispatchable;

    public function __construct(
        public int $workspaceId,      // kis workspace me hua
        public int $actorUserId,      // KISNE badla (jaise aap, Owner)
        public int $memberUserId,     // KISKA badla (jaise Priya)
        public string $oldRoleName,   // pehle kya tha (Viewer)
        public string $newRoleName,   // ab kya hai (Sales Staff)
    ) {}
}
