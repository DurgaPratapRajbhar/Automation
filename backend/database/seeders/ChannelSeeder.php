<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Channel;
use App\Models\ChannelCredential;

class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channel = Channel::create([
            'workspace_id' => 2,
            'type' => 'whatsapp',
            'provider' => 'meta',
            'name' => 'Indian Solar Light Centre WhatsApp',

            // WABA ID
            'external_account_id' => env('META_WABA_ID'),

            'status' => 'connected',
        ]);

        ChannelCredential::create([
            'channel_id' => $channel->id,

            // Abhi access token lena baki hai
            'access_token' =>  env('META_WHATSAPP_ACCESS_TOKEN'),

            'config' => [
                // Phone profile / Phone Number ID
                'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),

                // Optional: yahan bhi WABA ID rakh sakte hain,
                // but external_account_id me already hai
                'waba_id' => env('META_WABA_ID'),
            ],
        ]);
    }
}