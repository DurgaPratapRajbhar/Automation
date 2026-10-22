<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WhatsAppWebhookController extends Controller
{
    /**
     * Meta webhook verification
     */
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

    /**
     * Receive WhatsApp messages
     */
   public function receive(Request $request): JsonResponse
    {
        $appSecret = (string) config('services.meta.app_secret');

        if ($appSecret === '') {
            logger()->error('WhatsApp webhook: META_APP_SECRET is not configured.');

            return response()->json(['success' => false], 500);
        }
        
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

        $value = $request->input('entry.0.changes.0.value');

        $message = $value['messages'][0] ?? null;

        // Status webhook hai, incoming message nahi
        if (!$message) {
            return response()->json([
                'success' => true,
            ]);
        }

        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

        $customerNumber = $message['from'] ?? null;

        $customerName =
            $value['contacts'][0]['profile']['name'] ?? null;

        $messageId = $message['id'] ?? null;

        $messageText =
            $message['text']['body'] ?? null;

        logger()->info('Incoming WhatsApp Message', [
            'phone_number_id' => $phoneNumberId,
            'customer_number' => $customerNumber,
            'customer_name' => $customerName,
            'message_id' => $messageId,
            'message' => $messageText,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }
}