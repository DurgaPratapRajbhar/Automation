<?php

use App\Http\Controllers\Api\AdminRoleTemplateController;
use App\Http\Controllers\Api\AdminWorkspaceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EmbeddedSignupController;
use App\Http\Controllers\Api\KnowledgeSourceController;
use App\Http\Controllers\Api\MemberController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\WhatsAppTemplateController;
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
    Route::middleware('permission')->whereNumber(['roleId', 'userId', 'channelId', 'customerId', 'knowledgeSourceId', 'conversationId'])->group(function () {

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
        // "Connect WhatsApp" (Meta Embedded Signup) -> permission channels.connect
        Route::post('/channels/connect', [EmbeddedSignupController::class, 'connect'])
            ->middleware('throttle:10,1')
            ->name('channels.connect');

        // WhatsApp message templates of a connected number (stored at Meta)
        Route::get('/channels/{channelId}/templates', [WhatsAppTemplateController::class, 'index'])->name('templates.index');
        Route::post('/channels/{channelId}/templates', [WhatsAppTemplateController::class, 'store'])
            ->middleware('throttle:20,1')
            ->name('templates.store');
        Route::delete('/channels/{channelId}/templates/{templateName}', [WhatsAppTemplateController::class, 'destroy'])
            ->where('templateName', '[a-z0-9_]+')
            ->name('templates.destroy');
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

        Route::get('/knowledge-sources', [KnowledgeSourceController::class, 'index'])->name('knowledge-sources.index');
        Route::post('/knowledge-sources', [KnowledgeSourceController::class, 'store'])->name('knowledge-sources.store');
        Route::get('/knowledge-sources/{knowledgeSourceId}', [KnowledgeSourceController::class, 'show'])->name('knowledge-sources.show');
        Route::put('/knowledge-sources/{knowledgeSourceId}', [KnowledgeSourceController::class, 'update'])->name('knowledge-sources.update');
        Route::delete('/knowledge-sources/{knowledgeSourceId}', [KnowledgeSourceController::class, 'destroy'])->name('knowledge-sources.destroy');

        // Inbox: dukaan ki WhatsApp chats. Reply = "messages.create" permission
        Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::get('/conversations/{conversationId}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::post('/conversations/{conversationId}/messages', [ConversationController::class, 'reply'])
            ->middleware('throttle:30,1')
            ->name('messages.store');
        // Approved template (works after the 24-hour window) -> permission messages.template
        Route::post('/conversations/{conversationId}/template', [ConversationController::class, 'sendTemplate'])
            ->middleware('throttle:30,1')
            ->name('messages.template');

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
