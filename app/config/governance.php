<?php

return [
    'resources_path' => env('GOVERNANCE_RESOURCES_PATH', base_path('../resources')),
    'free_text_enabled' => (bool) env('FREE_TEXT_ENABLED', false),
    'required_signoffs' => ['sorani_language', 'clinical_safety'],
    'question_hmac_key' => env('QUESTION_HMAC_KEY', env('APP_KEY')),
    'safety_messages' => [
        'en' => env('SAFETY_BYPASS_MESSAGE_EN'),
        'ckb' => env('SAFETY_BYPASS_MESSAGE_CKB'),
    ],
    'source_refresh_days' => 7,
];
