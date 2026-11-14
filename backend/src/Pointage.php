<?php
namespace Rondo;

/** Badgeuse : règles de la v1, appliquées côté serveur avec l'heure du serveur. */
final class Pointage
{
    const TYPES = ['arrivee' => 'Arrivée', 'depart' => 'Départ', 'pause_debut' => 'Début de pause', 'pause_fin' => 'Fin de pause', 'jour_travaille' => 'Journée travaillée'];
    const SHIFT_TOLERANCE_MIN = 5;
    const GEO_ALERT_METERS = 150;

    public static function key(string $code, string $month): string
    {
        return "log:$code:pointage:$month";
    }

    /** Événements du jour (heure de Paris) d'une personne, triés. */
    public static function dayEvents(array $events, string $memberId, string $date): array
    {
        $out = array_values(array_filter($events, fn($e) => ($e['memberId'] ?? null) === $memberId && Clock::paris($e['ts'])->format('Y-m-d') === $date));
        usort($out, fn($a, $b) => strcmp($a['ts'], $b['ts']));
        return $out;
    }

    /** absent | present | pause | parti | jour (forfait jour enregistré) */
    public static function status(array $dayEvents): string
    {
        $status = 'absent';
        foreach ($dayEvents as $e) {
            $status = match ($e['type']) {
                'arrivee', 'pause_fin' => 'present',
                'pause_debut' => 'pause',
                'depart' => 'parti',
                'jour_travaille' => 'jour',
                default => $status,
            };
        }
        return $status;
    }

    /** Le créneau planifié qui couvre l'heure donnée (tolérance 5 min), ou null. */
    public static function coveringShift(array $shifts, string $name, \DateTimeImmutable $now): ?array
    {
        $date = $now->format('Y-m-d');
        $nowMin = (int) $now->format('G') * 60 + (int) $now->format('i');
        foreach ($shifts as $s) {
            if (($s['staff'] ?? null) !== $name || ($s['date'] ?? null) !== $date || empty($s['start'])) continue;
            [$sh, $sm] = array_map('intval', explode(':', $s['start']));
            $start = $sh * 60 + $sm;
            $end = $start + 8 * 60;
            if (!empty($s['end'])) {
                [$eh, $em] = array_map('intval', explode(':', $s['end']));
                $end = $eh * 60 + $em;
                if ($end < $start) $end += 24 * 60; // créneau à cheval sur minuit
            }
            if ($nowMin >= $start - self::SHIFT_TOLERANCE_MIN && $nowMin <= $end + self::SHIFT_TOLERANCE_MIN) return $s;
        }
        return null;
    }

    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): int
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return (int) round($r * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * Construit l'événement de badgeage, ou renvoie ['error' => code, 'message' => texte].
     * $type 'pause' est résolu en début ou fin selon la dernière pause du jour (règle v1).
     */
    public static function build(array $monthEvents, array $member, string $type, array $shifts, array $opts = []): array
    {
        $now = Clock::now();
        $date = $now->format('Y-m-d');
        $day = self::dayEvents($monthEvents, $member['id'], $date);
        $first = explode(' ', trim($member['name']))[0];

        if ($type === 'pause') {
            $pauses = array_values(array_filter($day, fn($e) => str_starts_with($e['type'], 'pause_')));
            $type = ($pauses && end($pauses)['type'] === 'pause_debut') ? 'pause_fin' : 'pause_debut';
        }
        if (!isset(self::TYPES[$type])) return ['error' => 'type', 'message' => 'Action de pointage inconnue.'];

        $io = array_values(array_filter($day, fn($e) => in_array($e['type'], ['arrivee', 'depart'], true)));
        $last = $io ? end($io) : null;
        if ($type === 'arrivee' && $last && $last['type'] === 'arrivee') {
            return ['error' => 'double_arrivee', 'message' => "$first, ton arrivée est déjà notée à " . Clock::paris($last['ts'])->format('H:i') . '.'];
        }
        if (in_array($type, ['depart', 'pause_debut', 'pause_fin'], true) && (!$last || $last['type'] === 'depart')) {
            return ['error' => 'depart_sans_arrivee', 'message' => "$first, aucune arrivée n'est notée aujourd'hui. Pointe d'abord ton arrivée."];
        }
        if ($type === 'jour_travaille') {
            if (empty($member['forfaitJour'])) return ['error' => 'pas_forfait', 'message' => 'Réservé aux salariés au forfait jour.'];
            foreach ($day as $e) if ($e['type'] === 'jour_travaille') return ['error' => 'jour_deja_enregistre', 'message' => 'Ta journée est déjà enregistrée.'];
        }

        $event = ['id' => App::id('pt'), 'memberId' => $member['id'], 'name' => $member['name'], 'type' => $type, 'ts' => Clock::iso($now)];
        if (isset($opts['lat'], $opts['lng']) && is_numeric($opts['lat']) && is_numeric($opts['lng'])) {
            $event['location'] = ['lat' => (float) $opts['lat'], 'lng' => (float) $opts['lng']];
            if (!empty($opts['restaurantLocation'])) {
                $event['distanceMeters'] = self::distanceMeters($event['location']['lat'], $event['location']['lng'], $opts['restaurantLocation']['lat'], $opts['restaurantLocation']['lng']);
            }
        }
        if (!empty($opts['signature'])) $event['signature'] = $opts['signature'];

        if ($type === 'arrivee') {
            $todays = array_filter($shifts, fn($s) => ($s['staff'] ?? null) === $member['name'] && ($s['date'] ?? null) === $date && !empty($s['start']));
            usort($todays, fn($a, $b) => strcmp($a['start'], $b['start']));
            if ($todays) {
                $planned = reset($todays)['start'];
                [$ph, $pm] = array_map('intval', explode(':', $planned));
                $event['plannedStart'] = $planned;
                $event['diffMin'] = (int) $now->format('G') * 60 + (int) $now->format('i') - ($ph * 60 + $pm);
            }
            // hors planning : jamais bloqué, mais justification obligatoire et validation du responsable
            if (!self::coveringShift($shifts, $member['name'], $now)) {
                $just = trim((string) ($opts['justification'] ?? ''));
                if ($just === '') return ['error' => 'hors_planning', 'message' => "Tu n'as pas de créneau prévu maintenant. Dis pourquoi tu arrives : ce sera validé par le responsable."];
                $event['offSchedule'] = true;
                $event['justification'] = $just;
                $event['validated'] = false;
            }
        }
        return $event;
    }
}
