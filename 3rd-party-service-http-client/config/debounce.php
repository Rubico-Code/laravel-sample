<?php

declare(strict_types=1);

return [
    /*
     * https://app.debounce.io/api
     */
    'private_key' => env('DEBOUNCE_PRIVATE_KEY'),
    'enabled' => (bool) env('DEBOUNCE_ENABLED', false),
];
