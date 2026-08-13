<?php

return [
    'roles' => [
        'Administrator' => ['*'],
        'Accountant' => [
            'posted_records.view',
            'reports.view',
            'audit.view',
            'configuration.manage',
            'master_data.manage',
            'drafts.manage',
            'drafts.submit',
            'transactions.approve',
            'transactions.reverse',
            'cash_bank.manage',
        ],
        'Encoder / Staff' => [
            'posted_records.view',
            'drafts.manage',
            'drafts.submit',
        ],
        'Viewer / Auditor' => [
            'posted_records.view',
            'reports.view',
            'audit.view',
        ],
    ],
];
