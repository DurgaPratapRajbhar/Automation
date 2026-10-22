<?php

namespace App\Channels\Providers;

use App\Channels\Contracts\ChannelProviderInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MetaWhatsAppProvider implements ChannelProviderInterface
{
    public function sendMessage(
        array $credentials,
        string $recipient,
        string $message
    ): array {

        if (empty($credentials['access_token']) || empty($credentials['phone_number_id'])) {
            throw new RuntimeException('WhatsApp credentials incomplete (access_token / phone_number_id).');
        }

        $accessToken = $credentials['access_token'];
        $phoneNumberId = $credentials['phone_number_id'];

        $baseUrl = config('services.meta.graph_url');
        $version = config('services.meta.graph_version');

        $url = "{$baseUrl}/{$version}/{$phoneNumberId}/messages";

        $response = Http::withToken($accessToken)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                ],
            ]);

        if ($response->failed()) {

            $error = $response->json('error');

            throw new RuntimeException(
                $error['message'] ?? 'WhatsApp message sending failed.',
                $response->status()
            );
        }

        return $response->json();
    }
}