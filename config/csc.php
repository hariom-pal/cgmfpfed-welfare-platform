<?php

declare(strict_types=1);
// Will be used for production
/*return [
    'connect' => [
        'client_id' => env('CSC_CONNECT_CLIENT_ID', '11a51dc1-7ede-4dc5-8b91-6b85f6585e37'),
        'client_secret' => env('CSC_CONNECT_CLIENT_SECRET'),
        'client_token' => env('CSC_CONNECT_CLIENT_TOKEN'),
        'redirect_uri' => env('CSC_CONNECT_REDIRECT_URI', env('APP_URL').'/csc/callback'),
        'authorization_endpoint' => env('CSC_CONNECT_AUTHORIZATION_ENDPOINT', 'https://connect.csc.gov.in/account/authorize'),
        'token_endpoint' => env('CSC_CONNECT_TOKEN_ENDPOINT', 'https://connect.csc.gov.in/account/token'),
        'resource_url' => env('CSC_CONNECT_RESOURCE_URL', 'https://connect.csc.gov.in/account/resource'),
    ],
    'bridge' => [
        'merchant_id' => env('CSC_BRIDGE_MERCHANT_ID', '49387'),
        'product_id' => env('CSC_BRIDGE_PRODUCT_ID', '4938787463'),
        'product_name' => env('CSC_BRIDGE_PRODUCT_NAME', 'Forest Department Government of Chattisgarh'),
        'wallet_payment_url' => env('CSC_WALLET_PAYMENT_URL', 'https://wallet.csccloud.in/v1/payment'),
        'application_fee' => (float) env('CSC_SCHOLARSHIP_APPLICATION_FEE', 50.00),
    ],
    'vle_role_id' => (int) env('CSC_VLE_ROLE_ID', 99),
];*/

return [
    'connect' => [
        'client_id' => env('CSC_CONNECT_CLIENT_ID', '8e39abfc-6ef4-41b9-af9e-a9b74f216dcd'),
        'client_secret' => env('CSC_CONNECT_CLIENT_SECRET'),
        'client_token' => env('CSC_CONNECT_CLIENT_TOKEN'),
        'redirect_uri' => env('CSC_CONNECT_REDIRECT_URI', 'http://127.0.0.1:8000/csc/callback'),
        'authorization_endpoint' => env('CSC_CONNECT_AUTHORIZATION_ENDPOINT', 'https://connectuat.csccloud.in/account/authorize'),
        'token_endpoint' => env('CSC_CONNECT_TOKEN_ENDPOINT', 'https://connectuat.csccloud.in/account/token'),
        'resource_url' => env('CSC_CONNECT_RESOURCE_URL', 'https://connectuat.csccloud.in/account/resource'),
    ],
    'bridge' => [
        'merchant_id' => env('CSC_BRIDGE_MERCHANT_ID', '49387'),
        'product_id' => env('CSC_BRIDGE_PRODUCT_ID', '4938787463'),
        'product_name' => env('CSC_BRIDGE_PRODUCT_NAME', 'Forest Department Government of Chattisgarh'),
        'wallet_payment_url' => env('CSC_WALLET_PAYMENT_URL', 'https://payuat.csccloud.in/v1/payment'),
        'application_fee' => (float) env('CSC_SCHOLARSHIP_APPLICATION_FEE', 50.00),
    ],
    'vle_role_id' => (int) env('CSC_VLE_ROLE_ID', 99),
];
