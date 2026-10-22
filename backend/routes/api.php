<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\WorkspaceAccessController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuditLogController;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::post('/refresh', [AuthController::class, 'refresh'])
        ->middleware('throttle:10,1');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->middleware('throttle:10,1');

    Route::get('/me', [AuthController::class, 'me'])
        ->middleware('jwt.auth');
});

/*
| Workspace ke andar ke saare routes.
| Har route par permission middleware lagana ZAROORI hai (sirf /workspace/me ko chhod ke).
*/
Route::middleware(['jwt.auth', 'workspace'])->group(function () {

    // Frontend: kaunse pages / buttons dikhane hain
    Route::get('/workspace/me', [WorkspaceAccessController::class, 'show']);

    // Permissions (role form ke checkboxes)
    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('permission:roles.view');

    // Roles
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('permission:roles.view');
    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware('permission:roles.create');
    Route::get('/roles/{roleId}', [RoleController::class, 'show'])
        ->middleware('permission:roles.view')
        ->whereNumber('roleId');
    Route::put('/roles/{roleId}', [RoleController::class, 'update'])
        ->middleware('permission:roles.edit')
        ->whereNumber('roleId');
    Route::delete('/roles/{roleId}', [RoleController::class, 'destroy'])
        ->middleware('permission:roles.delete')
        ->whereNumber('roleId');

    // Members
    Route::get('/members', [MemberController::class, 'index'])
        ->middleware('permission:members.view');
    Route::post('/members', [MemberController::class, 'store'])
        ->middleware('permission:members.create');
    Route::put('/members/{userId}', [MemberController::class, 'update'])
        ->middleware('permission:members.edit')
        ->whereNumber('userId');
    Route::delete('/members/{userId}', [MemberController::class, 'destroy'])
        ->middleware('permission:members.delete')
        ->whereNumber('userId');

    // Channels
    Route::get('/channels', [ChannelController::class, 'index'])
        ->middleware('permission:channels.view');
    Route::post('/channels', [ChannelController::class, 'store'])
        ->middleware('permission:channels.create');
    Route::get('/channels/{channelId}', [ChannelController::class, 'show'])
        ->middleware('permission:channels.view')
        ->whereNumber('channelId');
    Route::put('/channels/{channelId}', [ChannelController::class, 'update'])
        ->middleware('permission:channels.edit')
        ->whereNumber('channelId');
    Route::delete('/channels/{channelId}', [ChannelController::class, 'destroy'])
        ->middleware('permission:channels.delete')
        ->whereNumber('channelId');

    Route::post('/channels/send-message', [ChannelController::class, 'sendMessage'])
        ->middleware(['permission:conversations.reply', 'throttle:30,1']);

            // Audit log (history)
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('permission:workspace.view');
});

Route::get(
    '/webhooks/whatsapp',
    [WhatsAppWebhookController::class, 'verify']
);

Route::post(
    '/webhooks/whatsapp',
    [WhatsAppWebhookController::class, 'receive']
);

