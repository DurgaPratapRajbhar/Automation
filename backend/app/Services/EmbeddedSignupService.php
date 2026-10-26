<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Services\Meta\MetaGraph;
use Illuminate\Support\Facades\Crypt;

// "Connect WhatsApp": after the Meta popup, turn its code into a working channel for the shop.
// 1. code -> business token  2. subscribe WABA to our app  3. register the number  4. save channel
class EmbeddedSignupService
{
    public function __construct(
        private ChannelService $channelService,
        private MetaGraph $graph
    ) {}

    // Public values the browser needs to open the Meta popup; null when the server is not set up
    public function publicConfig(): ?array
    {
        if (! config('services.meta.app_id') || ! config('services.meta.embedded_signup_config_id')) {
            return null;
        }

        return [
            'app_id' => config('services.meta.app_id'),
            'config_id' => config('services.meta.embedded_signup_config_id'),
            'graph_version' => config('services.meta.graph_version'),
        ];
    }

    public function connect(int $workspaceId, string $code, string $wabaId, string $phoneNumberId): array
    {
        if (! config('services.meta.app_id') || ! config('services.meta.app_secret')) {
            throw new BusinessRuleException(__('channels.signup_not_configured'));
        }

        // Fail before touching Meta if this number already belongs to a channel
        $this->channelService->ensurePhoneNumberIdAvailable($phoneNumberId);

        $token = $this->exchangeCode($code);

        // Messages of this WABA now reach our webhook
        $this->graph->post("{$wabaId}/subscribed_apps", $token);

        // Register on Cloud API with a PIN we generate and keep (needed to re-register later)
        $pin = (string) random_int(100000, 999999);
        $this->graph->post("{$phoneNumberId}/register", $token, ['messaging_product' => 'whatsapp', 'pin' => $pin]);

        $phone = $this->graph->get($phoneNumberId, $token, ['fields' => 'verified_name,display_phone_number'])->json();

        return $this->channelService->create($workspaceId, [
            'type' => 'whatsapp',
            'provider' => 'meta',
            'name' => trim(($phone['verified_name'] ?? 'WhatsApp').' '.($phone['display_phone_number'] ?? '')),
            'credentials' => [
                'access_token' => $token,
                'phone_number_id' => $phoneNumberId,
                'waba_id' => $wabaId,
            ],
        ], ['pin' => Crypt::encryptString($pin)]);
    }

    // The popup's code is valid for ~30 seconds and only once
    private function exchangeCode(string $code): string
    {
        $token = $this->graph->get('oauth/access_token', null, [
            'client_id' => config('services.meta.app_id'),
            'client_secret' => config('services.meta.app_secret'),
            'code' => $code,
        ])->json('access_token');

        if (! is_string($token) || $token === '') {
            throw new BusinessRuleException(__('channels.meta_error', ['message' => 'no access token returned']));
        }

        return $token;
    }
}
