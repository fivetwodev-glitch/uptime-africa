<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/** Mise en forme française des valeurs affichées. */
class Format
{
    public static function ms(?int $ms): string
    {
        return $ms === null ? '—' : number_format($ms, 0, ',', "\u{202F}").' ms';
    }

    public static function percent(?float $value): string
    {
        return $value === null ? '—' : number_format($value, $value >= 99.995 && $value < 100 ? 3 : 2, ',', "\u{202F}").' %';
    }

    public static function duration(?int $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        if ($seconds < 60) {
            return $seconds.' s';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return trim(($days ? $days.' j ' : '').($hours ? $hours.' h ' : '').($minutes ? $minutes.' min' : ''));
    }

    public static function datetime(?DateTimeInterface $date): string
    {
        if ($date === null) {
            return '—';
        }

        return Carbon::instance($date)->timezone(config('uptime.timezone_display'))->translatedFormat('d M Y, H:i');
    }

    public static function ago(?DateTimeInterface $date): string
    {
        return $date === null ? 'Jamais' : Carbon::instance($date)->diffForHumans();
    }
}
