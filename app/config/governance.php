<?php

return [
    'resources_path' => env('GOVERNANCE_RESOURCES_PATH', base_path('../resources')),
    'signoffs_path' => env('GOVERNANCE_SIGNOFFS_PATH', base_path('../governance/review_signoffs.json')),
    'demo_review_path' => env('DEMO_REVIEW_MANIFEST_PATH', base_path('../governance/demo_review_manifest.json')),
    'interface_catalog_path' => env('INTERFACE_CATALOG_PATH', base_path('resources/js/interface-catalog.json')),
    'free_text_enabled' => (bool) env('FREE_TEXT_ENABLED', false),
    'required_signoffs' => ['sorani_language', 'clinical_safety'],
    'question_hmac_key' => env('QUESTION_HMAC_KEY', env('APP_KEY')),
    'safety_messages' => [
        'en' => env('SAFETY_BYPASS_MESSAGE_EN'),
        'ckb' => env('SAFETY_BYPASS_MESSAGE_CKB'),
    ],
    'source_refresh_days' => 7,
];
