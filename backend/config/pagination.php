<?php

// Default and maximum page size for list endpoints
return [
    'per_page' => (int) env('PAGINATION_PER_PAGE', 25),
    'max_per_page' => (int) env('PAGINATION_MAX_PER_PAGE', 100),
];
