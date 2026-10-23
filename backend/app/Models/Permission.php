<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Rows are created from route names by `php artisan permissions:sync`.
 */
class Permission extends SpatiePermission {}
