<?php

/*
| Rôles et permissions système. Source unique : insérés par la migration
| des rôles puis resynchronisés par `php artisan db:seed`.
*/

return [

    'permissions' => [
        'agency.view' => ['Voir l\'agence', 'Agence'],
        'agency.update' => ['Modifier l\'agence', 'Agence'],
        'users.view' => ['Voir les utilisateurs', 'Utilisateurs'],
        'users.manage' => ['Gérer les utilisateurs', 'Utilisateurs'],
        'clients.view' => ['Voir les clients', 'Clients'],
        'clients.manage' => ['Gérer les clients', 'Clients'],
        'sites.view' => ['Voir les sites', 'Sites'],
        'sites.manage' => ['Gérer les sites', 'Sites'],
        'incidents.view' => ['Voir les incidents', 'Incidents'],
        'incidents.acknowledge' => ['Prendre en charge un incident', 'Incidents'],
        'notifications.view' => ['Voir le journal des notifications', 'Notifications'],
        'notifications.manage' => ['Gérer les canaux et préférences d\'alerte', 'Notifications'],
        'subscriptions.view' => ['Voir l\'abonnement', 'Abonnement'],
        'subscriptions.manage' => ['Gérer l\'abonnement', 'Abonnement'],
        'settings.manage' => ['Gérer les paramètres', 'Paramètres'],
        'audit.view' => ['Voir l\'historique des actions', 'Historique'],
        'admin.access' => ['Accéder à l\'administration générale', 'Administration'],
    ],

    'roles' => [
        'super_admin' => [
            'name' => 'Administrateur général',
            'description' => 'Équipe Uptime Africa. N\'appartient à aucune agence.',
            'permissions' => ['*'],
        ],
        'owner' => [
            'name' => 'Propriétaire',
            'description' => 'Responsable de l\'agence et de son abonnement.',
            'permissions' => ['*', '!admin.access'],
        ],
        'admin' => [
            'name' => 'Administrateur d\'agence',
            'description' => 'Gère l\'agence au quotidien, sans l\'abonnement.',
            'permissions' => ['*', '!admin.access', '!subscriptions.manage', '!agency.update'],
        ],
        'member' => [
            'name' => 'Membre',
            'description' => 'Gère les clients, les sites et les incidents.',
            'permissions' => [
                'agency.view', 'clients.view', 'clients.manage', 'sites.view', 'sites.manage',
                'incidents.view', 'incidents.acknowledge', 'notifications.view',
            ],
        ],
        'viewer' => [
            'name' => 'Lecteur',
            'description' => 'Consultation uniquement.',
            'permissions' => ['agency.view', 'clients.view', 'sites.view', 'incidents.view'],
        ],
    ],

];
