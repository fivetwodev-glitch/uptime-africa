<?php

namespace App\Enums;

enum PlanFeature: string
{
    case MultiClients = 'multi_clients';
    case AdvancedNotifications = 'advanced_notifications';
    case TeamManagement = 'team_management';
    case AdvancedReports = 'advanced_reports';
    case PrioritySupport = 'priority_support';

    public function label(): string
    {
        return match ($this) {
            self::MultiClients => 'Gestion de plusieurs clients',
            self::AdvancedNotifications => 'Notifications avancées (WhatsApp, panne prolongée, résumés)',
            self::TeamManagement => 'Gestion des équipes',
            self::AdvancedReports => 'Rapports avancés',
            self::PrioritySupport => 'Fonctionnalités et support prioritaires',
        };
    }
}
