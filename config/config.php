<?php

return [
    'anthropic_api_key' => '',

    'claude_max_tokens' => env('AITRANSLATOR_CLAUDE_MAX_TOKENS', 4000),

    'default_driver' => env('AITRANSLATOR_DEFAULT_DRIVER', 'claude'),

    /*
    |--------------------------------------------------------------------------
    | Available Drivers
    |--------------------------------------------------------------------------
    |
    | Here you may configure the drivers that are available for selection
    | in the settings. The key is the driver identifier and the value
    | is the display name.
    |
    */
    'drivers' => [
        'claude' => 'Claude',
        'dummy' => 'Dummy',
        // You can add more drivers here in the future
        // 'deepl' => 'DeepL',
        // 'google' => 'Google Translate',
    ],

    'default_prompt_id' => null,
];
