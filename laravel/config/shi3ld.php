<?php

$siteUrl = rtrim((string) env('SHOP_URL', env('APP_URL', 'http://localhost')), '/');

return [
    'offline_state_file' => env('SITE_OFFLINE_STATE_FILE', '/workspace/site-access/offline.json'),
    'shared_design_file' => env('SITE_SHARED_DESIGN_FILE', '/workspace/site-access/design.json'),
    'enquiries_email' => env('ENQUIRIES_EMAIL', 'enquiries@example.test'),
    'site_url' => $siteUrl,
    'shop_url' => $siteUrl.'/shop',
    'about_url' => $siteUrl.'/about',
    'account_url' => $siteUrl.'/account',
    'basket_url' => $siteUrl.'/checkout/cart/',
    'magento' => [
        'base_url' => env('MAGENTO_BASE_URL', 'http://nginx'),
        'access_token' => env('MAGENTO_ACCESS_TOKEN'),
        'store_code' => env('MAGENTO_STORE_CODE', 'default'),
    ],
];
