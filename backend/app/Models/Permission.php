<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Rows come from App\Enums\Permission via `php artisan permissions:sync`.
 */
class Permission extends SpatiePermission
{
}
