<?php

return [
    // Added in front of a 10-digit number from an Excel (India)
    'default_country_code' => env('CAMPAIGN_DEFAULT_COUNTRY_CODE', '91'),

    // One upload: most rows and largest file (in KB)
    'max_import_rows' => 5000,
    'max_import_file_kb' => 5120,
];
