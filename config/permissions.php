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
            'name' => 'Super administrateur',
            'description' => 'Équipe Uptime Africa. N\'appartient à aucune agence.',
            'permissions' => ['*'],
        ],
        'admin' => [
            'name' => 'Administrateur d\'agence',
            'description' => 'Gère l\'agence, son équipe, ses alertes et son abonnement.',
            'permissions' => ['*', '!admin.access'],
        ],
        'collaborator' => [
            'name' => 'Collaborateur',
            'description' => 'Gère les clients, les sites et les incidents de l\'agence.',
            'permissions' => [
                'agency.view', 'users.view', 'clients.view', 'clients.manage', 'sites.view', 'sites.manage',
                'incidents.view', 'incidents.acknowledge', 'notifications.view',
            ],
        ],
    ],

];
