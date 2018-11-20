<?php

return [
    'host' => '{imap.gmail.com:993/imap/ssl}INBOX',
    'username' => 'login',
    'password' => 'password',
    'pattern' => [
        'subj' => '#^ml/[0-3]{1}[0-9]{1}_[0-1]{1}[0-9]{1}_[2]{1}[0]{1}[0-9]{1}[0-9]{1}/create#i',
        'cart' => '#^cart/[0-3]{1}[0-9]{1}_[0-1]{1}[0-9]{1}_[2]{1}[0]{1}[0-9]{1}[0-9]{1}#i',
    ],
    /** обрабатывать почту только с этих адресов */
    'allowFromEmail' => [
        'email1',
        'email2',
        'email3',
    ],

];
