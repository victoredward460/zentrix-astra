<?php

return [
    'openrouter' => [
        'api_key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL'),
        'models' => [
            'nvidia/nemotron-3-ultra-550b-a55b:free' => 'NVIDIA Nemotron 3 Ultra · Free · Default',
            'nvidia/nemotron-3.5-super-vl:free' => 'NVIDIA Nemotron 3.5 Super VL · Free',
        ],
        'reasoning_disabled_models' => [
            'nvidia/nemotron-3.5-super-vl:free',
        ],
        'require_zdr' => (bool) env('OPENROUTER_REQUIRE_ZDR', true),
        'data_collection' => env('OPENROUTER_DATA_COLLECTION', 'deny'),
        'connect_timeout' => (int) env('OPENROUTER_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 45),
        'max_tokens' => 700,
    ],

    'message' => [
        'history_limit' => 8,
        'max_input_chars' => 4000,
        'max_context_chars' => 8000,
        'max_output_chars' => 6000,
    ],
];
