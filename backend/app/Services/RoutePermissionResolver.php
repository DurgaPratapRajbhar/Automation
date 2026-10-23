<?php

namespace App\Services;

use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * Permissions come from route names, so no list is kept in code.
 * "customers.index" -> "customers.view", "customers.store" -> "customers.create",
 * any other action stays as it is: "conversations.reply" -> "conversations.reply".
 */
class RoutePermissionResolver
{
    private const ACTIONS = [
        'index' => 'view',
        'show' => 'view',
        'store' => 'create',
        'update' => 'edit',
        'destroy' => 'delete',
    ];

    public function __construct(
        private Router $router
    ) {}

    // The permission a route needs; null if the route is not protected or has no usable name
    public function forRoute(Route $route): ?string
    {
        if (! in_array('permission', $route->gatherMiddleware(), true)) {
            return null;
        }

        $name = $route->getName();
        $dot = $name ? strrpos($name, '.') : false;

        if ($dot === false) {
            return null;
        }

        $action = substr($name, $dot + 1);

        return substr($name, 0, $dot).'.'.(self::ACTIONS[$action] ?? $action);
    }

    // Every permission used by the registered routes
    public function all(): array
    {
        $permissions = [];

        foreach ($this->router->getRoutes() as $route) {
            if ($permission = $this->forRoute($route)) {
                $permissions[$permission] = true;
            }
        }

        return array_keys($permissions);
    }
}
