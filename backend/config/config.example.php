<?php

return [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'cpanel_database_name',
    'DB_USER' => 'cpanel_database_user',
    'DB_PASS' => 'cpanel_database_password',

    'APP_BASE_URL' => 'https://pilatesvitalite.webactiondemo.ca/pwa',
    'FINAL_DOMAIN' => 'https://pilatesvitalite.ca',
    'SOURCE_BASE_URL' => 'https://pilatesvitalite.webactiondemo.ca',
    'SOURCE_APP_ENV_PATH' => '',

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
        'shop' => 'https://pilatesvitalite.webactiondemo.ca/shop',
        'membership' => 'https://pilatesvitalite.webactiondemo.ca/membership',
    ],

    'HTTP_TIMEOUT' => 15,
    'MAX_BLOG_ITEMS' => 10,
    'MAX_SHOP_PAGES' => 10,
    'MAX_NEWSLETTER_ITEMS' => 500,
    'NOTIFY_ON_FIRST_RUN' => false,
    'ENABLE_PUSH_SEND' => true,
];
