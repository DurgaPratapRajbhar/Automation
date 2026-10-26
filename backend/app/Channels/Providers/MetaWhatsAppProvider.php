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
        return $this->send($credentials, [
            'to' => $recipient,
            'type' => 'text',
            'text' => [
                'body' => $message,
            ],
        ]);
    }

    public function sendTemplate(
        array $credentials,
        string $recipient,
        string $templateName,
        string $language,
        array $parameters
    ): array {
        $template = [
            'name' => $templateName,
            'language' => ['code' => $language],
        ];

        if ($parameters !== []) {
            $template['components'] = [[
                'type' => 'body',
                'parameters' => array_map(fn (string $value) => ['type' => 'text', 'text' => $value], array_values($parameters)),
            ]];
        }

        return $this->send($credentials, [
            'to' => $recipient,
            'type' => 'template',
            'template' => $template,
        ]);
    }

    private function send(array $credentials, array $payload): array
    {
        if (empty($credentials['access_token']) || empty($credentials['phone_number_id'])) {
            throw new RuntimeException('WhatsApp credentials incomplete (access_token / phone_number_id).');
        }

        $baseUrl = config('services.meta.graph_url');
        $version = config('services.meta.graph_version');

        $url = "{$baseUrl}/{$version}/{$credentials['phone_number_id']}/messages";

        $response = Http::withToken($credentials['access_token'])
            ->post($url, ['messaging_product' => 'whatsapp', ...$payload]);

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
