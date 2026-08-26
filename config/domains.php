<?php

$appUrl = (string) env('APP_URL', 'http://localhost');
$scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
$appDomain = parse_url($appUrl, PHP_URL_HOST) ?: 'localhost';
$adminDomain = (string) env('ADMIN_DOMAIN', 'admin.'.$appDomain);
$memberDomain = (string) env('MEMBER_DOMAIN', 'member.'.$appDomain);

return [
    'app' => $appDomain,
    'admin' => $adminDomain,
    'member' => $memberDomain,
    'admin_url' => (string) env('ADMIN_URL', $scheme.'://'.$adminDomain),
    'member_url' => (string) env('MEMBER_URL', $scheme.'://'.$memberDomain),
];
