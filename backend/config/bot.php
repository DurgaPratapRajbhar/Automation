<?php

// How the WhatsApp bot talks in a chat
return [
    // Earlier messages of the chat the bot reads before it answers, so "haan" or "karwao" is understood
    'history_messages' => (int) env('BOT_HISTORY_MESSAGES', 10),

    // The bot stays quiet in a chat for this long after the owner replies there or the customer asks for the owner
    'human_pause_hours' => (int) env('BOT_HUMAN_PAUSE_HOURS', 12),
];
