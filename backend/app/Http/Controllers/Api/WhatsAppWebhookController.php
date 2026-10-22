<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\InboundMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        private InboundMessageService $inboundMessages
    ) {}

 
    public function verify(Request $request)
    {
        $verifyToken = config('services.meta.webhook_verify_token');

        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            return response($challenge, 200)
                ->header('Content-Type', 'text/plain');
        }

        return response('Invalid verify token', 403);
    }
 
    public function receive(Request $request): JsonResponse
    {
        $appSecret = (string) config('services.meta.app_secret');

        if ($appSecret === '') {
            logger()->error('WhatsApp webhook: META_APP_SECRET is not configured.');

            return response()->json(['success' => false], 500);
        }

        // Only Meta knows the App Secret — reject anything not signed by Meta
        $signature = $request->header('X-Hub-Signature-256');

        $expected = 'sha256=' . hash_hmac(
            'sha256',
            $request->getContent(),
            $appSecret
        );

        if (!$signature || !hash_equals($expected, $signature)) {
            logger()->warning('WhatsApp webhook: invalid signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['success' => false], 403);
        }

        // Save the message + ring the MessageReceived bell
        $this->inboundMessages->handle(
            $request->input('entry.0.changes.0.value', [])
        );

        // Always 200 quickly — otherwise Meta keeps retrying
        return response()->json(['success' => true]);
    }
}