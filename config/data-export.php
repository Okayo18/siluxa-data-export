<?php

return [
    // Classes d'exportation
    'exports' => [
        'users' => [
            'class' => \App\Exports\UserExport::class,
        ],
    ],

    // Formats de sortie supportés
    'formats' => ['csv', 'xlsx', 'pdf'],

    // Répertoire de stockage des fichiers exportés
    'storage_path' => storage_path('app/public/exports'),

    // Paramètres de notification
    'notification' => [
        'emails' => env('EXPORT_NOTIFICATION_EMAILS', 'admin@example.com'),
        'disk' => 'public',
        'view' => 'data-export::notifications.export-completed',
        'failed_view' => 'data-export::notifications.export-failed',
    ],

    // Taille des lots pour les curseurs
    'batch_size' => 1000,
];