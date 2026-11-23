<?php

return [
    'providers' => [
        'virustotal' => [
            'name' => 'VirusTotal',
            'credential_required' => true,
            'credential_variable' => 'VIRUSTOTAL_API_KEY',
            'credential' => env('VIRUSTOTAL_API_KEY'),
        ],
    ],
];
