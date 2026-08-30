<?php

$appUrl = (string) env('APP_URL', 'http://localhost');
$applicationType = (string) config('app.type', 'ALL');
$scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
$appDomain = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
$adminDomain = (string) env('ADMIN_DOMAIN', 'admin.'.$appDomain);
$memberDomain = (string) env('MEMBER_DOMAIN', 'member.'.$appDomain);
$demoDomain = $applicationType === 'DEMO'
    ? $appDomain
    : (string) env('DEMO_DOMAIN', 'demo.'.$appDomain);
$demoUrl = $applicationType === 'DEMO'
    ? $appUrl
    : (string) env('DEMO_URL', $scheme.'://'.$demoDomain);

return [
    'app' => $appDomain,
    'admin' => $adminDomain,
    'member' => $memberDomain,
    'demo' => $demoDomain,
    'admin_url' => (string) env('ADMIN_URL', $scheme.'://'.$adminDomain),
    'member_url' => (string) env('MEMBER_URL', $scheme.'://'.$memberDomain),
    'demo_url' => $demoUrl,
];
