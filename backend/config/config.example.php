<?php

return [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'cpanel_database_name',
    'DB_USER' => 'cpanel_database_user',
    'DB_PASS' => 'cpanel_database_password',

    'APP_BASE_URL' => 'https://pilatesvitalite.webactiondemo.ca/pwa',
    'FINAL_DOMAIN' => 'https://pilatesvitalite.ca',
    'SOURCE_BASE_URL' => 'https://pilatesvitalite.webactiondemo.ca',

    'VAPID_PUBLIC_KEY' => '',
    'VAPID_PRIVATE_KEY' => '',
    'VAPID_SUBJECT' => 'mailto:info@pilatesvitalite.ca',

    'CRON_SECRET' => 'change-this-cron-secret',
    'NOTIFY_TEST_SECRET' => 'change-this-notify-test-secret',

    'WATCH_URLS' => [
        'home' => 'https://pilatesvitalite.webactiondemo.ca/',
        'about' => 'https://pilatesvitalite.webactiondemo.ca/about-us',
        'blog' => 'https://pilatesvitalite.webactiondemo.ca/blogs',
        'contact' => 'https://pilatesvitalite.webactiondemo.ca/contact',
    ],

    'HTTP_TIMEOUT' => 15,
    'MAX_BLOG_ITEMS' => 10,
    'NOTIFY_ON_FIRST_RUN' => false,
    'ENABLE_PUSH_SEND' => true,
];
