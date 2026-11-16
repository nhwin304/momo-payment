<?php

declare(strict_types=1);

return [
    // Mặc định không cho phép thanh toán cho tới khi admin bật trong nhwin/settings.
    'permission' => 'manage_momo_payment',

    // Chỉ dùng máy chủ chính thức; không đưa endpoint tùy ý của người dùng vào HTTP client.
    'endpoints' => [
        'sandbox' => [
            'create' => 'https://test-payment.momo.vn/v2/gateway/api/create',
            'query' => 'https://test-payment.momo.vn/v2/gateway/api/query',
        ],
        'production' => [
            'create' => 'https://payment.momo.vn/v2/gateway/api/create',
            'query' => 'https://payment.momo.vn/v2/gateway/api/query',
        ],
    ],
    'connect_timeout' => 10,
    'timeout' => 35,

    // Tên đối tác xuất hiện trong yêu cầu tạo thanh toán.
    'partner_name' => env('APP_NAME', 'Laravel'),
    'store_id' => 'MainStore',
];
