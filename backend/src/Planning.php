<?php
namespace Rondo;

/** Planning (format v1 : `log:{code}:planning:{AAAA-MM}`, publication par semaine). */
final class Planning
{
    public static function key(string $code, string $month): string
    {
        return "log:$code:planning:$month";
    }

    /** Lundi de la semaine (AAAA-MM-JJ), clé de publication de la v1. */
    public static function weekStart(string $date): string
    {
        $d = new \DateTimeImmutable($date);
        return $d->modify('-' . ((int) $d->format('N') - 1) . ' days')->format('Y-m-d');
    }

    public static function isPublished(array $publishRecord, string $date): bool
    {
        return isset($publishRecord[self::weekStart($date)]);
    }

    /** Créneaux publiés d'une personne entre deux dates incluses, triés. */
    public static function myShifts(Store $s, string $code, string $name, string $from, string $to): array
    {
        $publish = $s->get("config:$code:planningPublish") ?: [];
        $months = array_unique([substr($from, 0, 7), substr($to, 0, 7)]);
        $out = [];
        foreach ($months as $m) {
            foreach ($s->get(self::key($code, $m)) ?: [] as $sh) {
                if (($sh['staff'] ?? null) === $name && $sh['date'] >= $from && $sh['date'] <= $to && self::isPublished($publish, $sh['date'])) $out[] = $sh;
            }
        }
        usort($out, fn($a, $b) => strcmp($a['date'] . ($a['start'] ?? ''), $b['date'] . ($b['start'] ?? '')));
        return $out;
    }
}
