<?php

return [
    // Free trial of a shop that signs up by itself
    'days' => (int) env('TRIAL_DAYS', 7),

    // AI answers per day for a shop on trial (a paid shop has no limit)
    'ai_daily_limit' => (int) env('TRIAL_AI_DAILY_LIMIT', 100),

    // Shown to a shop whose trial has ended
    'contact_phone' => env('TRIAL_CONTACT_PHONE', '83569 74324'),
];
