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

    const MAX_DAILY_MIN = 10 * 60;  // L3121-18
    const MAX_WEEKLY_MIN = 48 * 60; // L3121-20
    const MIN_REST_MIN = 11 * 60;   // L3131-1

    private static function toMin(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));
        return $h * 60 + $m;
    }

    /** [début, fin] en minutes depuis minuit du jour du créneau (fin > 24 h si à cheval sur minuit). */
    public static function span(array $sh): array
    {
        $start = self::toMin($sh['start']);
        $end = empty($sh['end']) ? $start + 8 * 60 : self::toMin($sh['end']);
        if ($end <= $start) $end += 24 * 60;
        return [$start, $end];
    }

    public static function days(string $weekStart): array
    {
        return array_map(fn($i) => (new \DateTimeImmutable($weekStart))->modify("+$i days")->format('Y-m-d'), range(0, 6));
    }

    /** Créneaux de la semaine (lus sur un ou deux mois). */
    public static function week(Store $s, string $code, string $weekStart): array
    {
        $days = self::days($weekStart);
        $out = [];
        foreach (array_unique([substr($days[0], 0, 7), substr($days[6], 0, 7)]) as $m) {
            foreach ($s->get(self::key($code, $m)) ?: [] as $sh) if (in_array($sh['date'] ?? '', $days, true)) $out[] = $sh;
        }
        usort($out, fn($a, $b) => strcmp($a['date'] . ($a['start'] ?? ''), $b['date'] . ($b['start'] ?? '')));
        return $out;
    }

    /** Instantané identique à la v1 : par personne, « date|début|fin|note » triés. */
    public static function snapshot(array $weekShifts): array
    {
        $by = [];
        foreach ($weekShifts as $sh) $by[$sh['staff']][] = "{$sh['date']}|" . ($sh['start'] ?? '') . '|' . ($sh['end'] ?? '') . '|' . ($sh['note'] ?? '');
        foreach ($by as &$l) sort($l);
        ksort($by);
        return $by;
    }

    public static function publishStatus(array $record, string $weekStart, array $weekShifts): array
    {
        $rec = $record[$weekStart] ?? null;
        if (!$rec) return ['published' => false, 'upToDate' => false];
        $snap = $rec['snapshot'] ?? [];
        ksort($snap);
        return ['published' => true, 'upToDate' => self::snapshot($weekShifts) == $snap, 'publishedAt' => $rec['publishedAt'] ?? null, 'publishedBy' => $rec['publishedBy'] ?? null];
    }

    /**
     * Alertes du droit du travail général (non HCR, à faire valider par un expert) :
     * plus de 10 h dans la journée, plus de 48 h dans la semaine, moins de 11 h de repos.
     */
    public static function legalAlerts(array $weekShifts): array
    {
        $alerts = [];
        $byStaff = [];
        foreach ($weekShifts as $sh) if (!empty($sh['start'])) $byStaff[$sh['staff']][] = $sh;
        foreach ($byStaff as $staff => $list) {
            $perDay = [];
            $total = 0;
            $intervals = [];
            foreach ($list as $sh) {
                [$a, $b] = self::span($sh);
                $perDay[$sh['date']] = ($perDay[$sh['date']] ?? 0) + $b - $a;
                $total += $b - $a;
                $base = (new \DateTimeImmutable($sh['date']))->getTimestamp() / 60;
                $intervals[] = [$base + $a, $base + $b, $sh['date']];
            }
            foreach ($perDay as $date => $min) if ($min > self::MAX_DAILY_MIN) $alerts[] = ['staff' => $staff, 'date' => $date, 'rule' => 'daily', 'message' => "$staff : " . self::h($min) . " le " . self::d($date) . " (max 10 h)."];
            if ($total > self::MAX_WEEKLY_MIN) $alerts[] = ['staff' => $staff, 'date' => null, 'rule' => 'weekly', 'message' => "$staff : " . self::h($total) . ' sur la semaine (max 48 h).'];
            usort($intervals, fn($x, $y) => $x[0] <=> $y[0]);
            // repos quotidien : entre la fin la plus tardive d'une journée et le premier créneau de la suivante
            $lastEnd = null;
            $lastDate = null;
            foreach ($intervals as [$start, $end, $date]) {
                if ($lastDate !== null && $date !== $lastDate && $start - $lastEnd < self::MIN_REST_MIN) {
                    $alerts[] = ['staff' => $staff, 'date' => $date, 'rule' => 'rest', 'message' => "$staff : " . self::h(max(0, (int) ($start - $lastEnd))) . ' de repos avant le ' . self::d($date) . ' (min 11 h).'];
                }
                if ($date !== $lastDate) { $lastDate = $date; $lastEnd = $end; } else $lastEnd = max($lastEnd, $end);
            }
        }
        return $alerts;
    }

    private static function h(int $min): string
    {
        return intdiv($min, 60) . ' h' . ($min % 60 ? sprintf('%02d', $min % 60) : '');
    }

    private static function d(string $date): string
    {
        return (new \DateTimeImmutable($date))->format('d/m');
    }
}
