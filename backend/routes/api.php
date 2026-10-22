<?php

use App\Enums\Permission;
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
| Routes inside a workspace.
| Every route needs a permission middleware (except /workspace/me).
*/
Route::middleware(['jwt.auth', 'workspace'])->group(function () {

    // Frontend: which pages / buttons to show
    Route::get('/workspace/me', [WorkspaceAccessController::class, 'show']);

    // Shop details: contact, GST, UPI
    Route::get('/workspace/profile', [WorkspaceProfileController::class, 'show'])
        ->middleware(Permission::WorkspaceView->middleware());
    Route::put('/workspace/profile', [WorkspaceProfileController::class, 'update'])
        ->middleware(Permission::WorkspaceEdit->middleware());

    // Permission checkboxes for the role form
    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware(Permission::RolesView->middleware());

    // Roles
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware(Permission::RolesView->middleware());
    Route::post('/roles', [RoleController::class, 'store'])
        ->middleware(Permission::RolesCreate->middleware());
    Route::get('/roles/{roleId}', [RoleController::class, 'show'])
        ->middleware(Permission::RolesView->middleware())
        ->whereNumber('roleId');
    Route::put('/roles/{roleId}', [RoleController::class, 'update'])
        ->middleware(Permission::RolesEdit->middleware())
        ->whereNumber('roleId');
    Route::delete('/roles/{roleId}', [RoleController::class, 'destroy'])
        ->middleware(Permission::RolesDelete->middleware())
        ->whereNumber('roleId');

    // Members
    Route::get('/members', [MemberController::class, 'index'])
        ->middleware(Permission::MembersView->middleware());
    Route::post('/members', [MemberController::class, 'store'])
        ->middleware(Permission::MembersCreate->middleware());
    Route::put('/members/{userId}', [MemberController::class, 'update'])
        ->middleware(Permission::MembersEdit->middleware())
        ->whereNumber('userId');
    Route::delete('/members/{userId}', [MemberController::class, 'destroy'])
        ->middleware(Permission::MembersDelete->middleware())
        ->whereNumber('userId');

    // Channels
    Route::get('/channels', [ChannelController::class, 'index'])
        ->middleware(Permission::ChannelsView->middleware());
    Route::post('/channels', [ChannelController::class, 'store'])
        ->middleware(Permission::ChannelsCreate->middleware());
    Route::get('/channels/{channelId}', [ChannelController::class, 'show'])
        ->middleware(Permission::ChannelsView->middleware())
        ->whereNumber('channelId');
    Route::put('/channels/{channelId}', [ChannelController::class, 'update'])
        ->middleware(Permission::ChannelsEdit->middleware())
        ->whereNumber('channelId');
    Route::delete('/channels/{channelId}', [ChannelController::class, 'destroy'])
        ->middleware(Permission::ChannelsDelete->middleware())
        ->whereNumber('channelId');

    Route::post('/channels/send-message', [ChannelController::class, 'sendMessage'])
        ->middleware([Permission::ConversationsReply->middleware(), 'throttle:30,1']);

    // Customers
    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware(Permission::CustomersView->middleware());
    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware(Permission::CustomersCreate->middleware());
    Route::get('/customers/{customerId}', [CustomerController::class, 'show'])
        ->middleware(Permission::CustomersView->middleware())
        ->whereNumber('customerId');
    Route::put('/customers/{customerId}', [CustomerController::class, 'update'])
        ->middleware(Permission::CustomersEdit->middleware())
        ->whereNumber('customerId');
    Route::delete('/customers/{customerId}', [CustomerController::class, 'destroy'])
        ->middleware(Permission::CustomersDelete->middleware())
        ->whereNumber('customerId');

    // Audit log (history)
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware(Permission::WorkspaceView->middleware());
});

// Platform super admin only
Route::prefix('admin')->middleware(['jwt.auth', 'super.admin'])->group(function () {
    Route::get('/workspaces', [AdminWorkspaceController::class, 'index']);
    Route::patch('/workspaces/{workspaceId}/status', [AdminWorkspaceController::class, 'updateStatus'])
        ->whereNumber('workspaceId');
});

Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);
