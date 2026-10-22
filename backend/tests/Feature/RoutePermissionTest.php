<?php

use App\Services\RoutePermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
});

test('route names map to permissions', function () {
    $permissions = app(RoutePermissionResolver::class)->all();

    expect($permissions)->toContain(
        'customers.view',      // customers.index and customers.show
        'customers.create',    // customers.store
        'customers.edit',      // customers.update
        'customers.delete',    // customers.destroy
        'conversations.reply', // custom action keeps its name
        'workspace.view',
    )->not->toContain('customers.index', 'customers.store');
});

test('every protected route has a name, so none is left open by mistake', function () {
    $resolver = app(RoutePermissionResolver::class);

    foreach (Route::getRoutes() as $route) {
        if (in_array('permission', $route->gatherMiddleware(), true)) {
            expect($resolver->forRoute($route))->not->toBeNull("Route {$route->uri()} needs a name");
        }
    }
});

test('a protected route without a usable name is denied, even for the owner', function () {
    Route::middleware(['api', 'jwt.auth', 'workspace', 'permission'])->get('/api/unnamed-test-route', fn () => 'open');

    $owner = registerOwner($this);

    $this->getJson('/api/unnamed-test-route', headersFor($owner['token'], $owner['workspace_id']))
        ->assertForbidden();
});
