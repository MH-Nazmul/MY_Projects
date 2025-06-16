<?php
define('PROJECT_PATH', 'https://mhnazmul.free.nf');
return [
    'api_domain' => 'https://sandbox.sslcommerz.com',
    'apiCredentials' => [
        'store_id' => 'testbox',
        'store_passwd' => 'qwerty',
    ],
    'apiUrl' => [
        'make_payment' => '/gwprocess/v4/api.php',
        'transaction_status' => '/validator/v1.0/paymentStatusVendorApi.php',
        'order_validate' => '/validator/v1.0/paymentOrderValidateApi.php',
        'refund' => '/validator/v1.0/refundApi.php',
    ],
    'sslcommerz_connect_ipn_url' => PROJECT_PATH . '/SSLCommerz-PHP-master/pg_redirection/ipn.php',
    'success_url' => '/SSLCommerz-PHP-master/pg_redirection/success.php',
    'failed_url' => '/SSLCommerz-PHP-master/pg_redirection/fail.php',
    'cancel_url' => '/SSLCommerz-PHP-master/pg_redirection/cancel.php',
    'ipn_url' => '/SSLCommerz-PHP-master/pg_redirection/ipn.php',
    'connect_from_localhost' => false,
    'verify_hash' => true,
];
?>