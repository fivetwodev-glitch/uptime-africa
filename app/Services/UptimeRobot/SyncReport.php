<?php

namespace App\Services\UptimeRobot;

/** Résumé lisible d'un rapport de SyncEngine (console, messages flash, endpoint). */
final class SyncReport
{
    public static function summary(array $report): string
    {
        if ($report['status'] === 'skipped') {
            return $report['message'] ?? 'Synchronisation non effectuée.';
        }

        $text = sprintf(
            '%d site(s) : %d en ligne, %d en échec, %d inconnu(s), %d déjà traité(s). Incidents : %d ouvert(s), %d résolu(s).',
            $report['sites'] ?? 0, $report['up'] ?? 0, $report['down'] ?? 0, $report['unknown'] ?? 0,
            $report['skipped'] ?? 0, $report['incidents_opened'] ?? 0, $report['incidents_resolved'] ?? 0,
        );

        return $report['status'] === 'failed'
            ? 'API UptimeRobot indisponible, aucun site marqué en panne. '.($report['message'] ?? '')
            : $text;
    }
}
