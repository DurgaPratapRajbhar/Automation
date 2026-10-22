<?php

return [

    'private_key' => storage_path('keys/jwt-private.pem'),

    'public_key' => storage_path('keys/jwt-public.pem'),

    'algorithm' => 'RS256',

    'issuer' => env('JWT_ISSUER', 'automation-auth'),

    'audience' => env('JWT_AUDIENCE', 'automation-api'),

    'ttl' => (int) env('JWT_TTL', 900),

];