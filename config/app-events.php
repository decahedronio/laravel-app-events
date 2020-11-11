<?php

return [
    'enabled' => true,
    'project_id' => env('GCP_PROJECT_ID', 'your-google-project'),
    'topic' => env('APP_EVENTS_TOPIC', 'app-events'),
    'subscription_prefix' => env('APP_EVENTS_SUBSCRIPTION_PREFIX', ''),
    'subscription' => env('APP_EVENTS_SUBSCRIPTION', 'your-service'),

    'mappings' => [],

    'handlers' => [],
];
