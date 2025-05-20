<?php

return [
    // Types d'entités exportables avec conditions, attributs, et relations
    'entities' => [
        'users' => [
            'model' => \App\Models\User::class,
            'conditions' => [], // Ex: ['type' => 'admin'] pour STI
            'attributes' => ['id', 'name', 'email'], // Attributs à inclure
            'exclude_attributes' => ['password'], // Attributs à exclure
            'relations' => [
                'profile' => ['name', 'phone'], // Relation hasOne
                'posts' => ['title', 'created_at'], // Relation hasMany
            ],
        ],
    ],

    // Formats de sortie supportés
    'formats' => ['csv', 'json', 'xlsx', 'pdf'],

    // Répertoire de stockage des fichiers exportés
    'storage_path' => storage_path('app/public/exports'),

    // Paramètres de notification
    'notification' => [
        'emails' => env('EXPORT_NOTIFICATION_EMAILS', 'admin@example.com'),
        'disk' => 'public',
        'url_expiration' => 24 * 60, // 24h
        'view' => 'data-export::notifications.export-completed',
        'failed_view' => 'data-export::notifications.export-failed',
    ],

    // Taille des lots pour les curseurs
    'batch_size' => 1000,
];