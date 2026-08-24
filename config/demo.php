<?php

$domains = [
    'local' => 'demo.carwash-management-system.test',
    'live' => 'carwash-demo.zenagital.id',
];

$defaultMode = env('APP_ENV', 'production') === 'production' ? 'live' : 'local';
$configuredMode = (string) env('DEMO_MODE', $defaultMode);
$mode = array_key_exists($configuredMode, $domains) ? $configuredMode : $defaultMode;

return [
    'mode' => $mode,
    'domains' => $domains,
    'domain' => (string) env('DEMO_DOMAIN', $domains[$mode]),
];
