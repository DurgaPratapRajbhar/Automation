<?php

return [
    // Added in front of a 10-digit number from an Excel (India)
    'default_country_code' => env('CAMPAIGN_DEFAULT_COUNTRY_CODE', '91'),

    // While testing: send to these numbers only (comma separated, with country code, e.g. 918898540057).
    // Empty = no limit (production). Any other number is refused before WhatsApp is called.
    'only_send_to' => array_values(array_filter(array_map('trim', explode(',', (string) env('CAMPAIGN_ONLY_SEND_TO', ''))))),

    // How fast a campaign sends (people per second); well below WhatsApp's own limit
    'messages_per_second' => 5,

    // One upload: most rows and largest file (in KB)
    'max_import_rows' => 5000,
    'max_import_file_kb' => 5120,
];
