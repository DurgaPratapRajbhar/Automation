<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\WhatsAppWebhookController;

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

Route::get('/roles', [RoleController::class, 'index'])->middleware([
        'jwt.auth',
        'workspace',
        'permission:roles.view',
    ]);

Route::get('/members', [MemberController::class, 'index'])->middleware([
        'jwt.auth',
        'workspace',
        'permission:members.view',
    ]);

Route::post('/channels/send-message', [ChannelController::class, 'sendMessage'])
    ->middleware([
        'jwt.auth',
        'workspace',
        'permission:conversations.reply',
        'throttle:30,1',
    ]);
Route::get(
    '/webhooks/whatsapp',
    [WhatsAppWebhookController::class, 'verify']
);

Route::post(
    '/webhooks/whatsapp',
    [WhatsAppWebhookController::class, 'receive']
);


 