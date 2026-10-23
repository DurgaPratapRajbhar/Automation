<?php

use App\Http\Controllers\Api\AdminRoleTemplateController;
use App\Http\Controllers\Api\AdminWorkspaceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\WorkspaceAccessController;
use App\Http\Controllers\Api\WorkspaceProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:10,1');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('throttle:10,1');
    Route::get('/me', [AuthController::class, 'me'])->middleware('jwt.auth');
});

Route::middleware(['jwt.auth', 'workspace'])->group(function () {

    // What the user can do here (frontend menu); needs no permission
    Route::get('/workspace/me', [WorkspaceAccessController::class, 'show']);

    /*
    | Every route here needs a NAME: the permission is taken from it
    | (index/show -> view, store -> create, update -> edit, destroy -> delete).
    | Run `php artisan permissions:sync` after adding routes.
    */
    Route::middleware('permission')->whereNumber(['roleId', 'userId', 'channelId', 'customerId'])->group(function () {

        Route::get('/workspace/profile', [WorkspaceProfileController::class, 'show'])->name('workspace.show');
        Route::put('/workspace/profile', [WorkspaceProfileController::class, 'update'])->name('workspace.update');

        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{roleId}', [RoleController::class, 'show'])->name('roles.show');
        Route::put('/roles/{roleId}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{roleId}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('/members', [MemberController::class, 'index'])->name('members.index');
        Route::post('/members', [MemberController::class, 'store'])->name('members.store');
        Route::put('/members/{userId}', [MemberController::class, 'update'])->name('members.update');
        Route::delete('/members/{userId}', [MemberController::class, 'destroy'])->name('members.destroy');

        Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
        Route::post('/channels', [ChannelController::class, 'store'])->name('channels.store');
        Route::get('/channels/{channelId}', [ChannelController::class, 'show'])->name('channels.show');
        Route::put('/channels/{channelId}', [ChannelController::class, 'update'])->name('channels.update');
        Route::delete('/channels/{channelId}', [ChannelController::class, 'destroy'])->name('channels.destroy');
        Route::post('/channels/send-message', [ChannelController::class, 'sendMessage'])
            ->middleware('throttle:30,1')
            ->name('conversations.reply');

        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customerId}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('/customers/{customerId}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customerId}', [CustomerController::class, 'destroy'])->name('customers.destroy');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});

// Super admin login (separate from shop login)
Route::post('/admin/auth/login', [AuthController::class, 'adminLogin'])->middleware('throttle:5,1');

// Platform super admin only
Route::prefix('admin')->middleware(['jwt.auth', 'super.admin'])->whereNumber(['workspaceId', 'templateId'])->group(function () {
    Route::get('/workspaces', [AdminWorkspaceController::class, 'index']);
    Route::post('/workspaces', [AdminWorkspaceController::class, 'store']);
    Route::patch('/workspaces/{workspaceId}/status', [AdminWorkspaceController::class, 'updateStatus']);

    // Permission tree for the role template form
    Route::get('/permissions', [PermissionController::class, 'index']);

    // Roles copied into every new workspace
    Route::get('/role-templates', [AdminRoleTemplateController::class, 'index']);
    Route::post('/role-templates', [AdminRoleTemplateController::class, 'store']);
    Route::put('/role-templates/{templateId}', [AdminRoleTemplateController::class, 'update']);
    Route::delete('/role-templates/{templateId}', [AdminRoleTemplateController::class, 'destroy']);
});

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);
