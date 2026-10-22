<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class MemberRemoved
{
    use Dispatchable;

    public function __construct(
        public int $workspaceId,      
        public int $actorUserId,       
        public int $memberUserId,     
        public string $roleName,   
          
    ) {}
}