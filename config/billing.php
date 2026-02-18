<?php


return [

    'bank' => [
        'company_name' => env('BILLING_COMPANY_NAME', 'BeautyBook LLC'),
        'bank_name' => env('BILLING_BANK_NAME', 'ACBA Bank'),
        'account_number' => env('BILLING_ACCOUNT_NUMBER', '1234567890123456'),
        'recipient_name' => env('BILLING_RECIPIENT_NAME', 'BeautyBook LLC'),
        'note_template' => 'Invoice #:id Salon #:salon',
    ],

    'idram' => [
        'wallet_id' => env('BILLING_IDRAM_WALLET', '0000000000'),
        'note_template' => 'Invoice #:id',
    ],

];
