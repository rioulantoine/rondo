<?php
namespace Rondo;

/** Réservation en ligne, format v1 (`reservation:{code}:config|tables|reservations|availability`). */
final class Reservation
{
    const STATUSES = ['confirmee' => 'Confirmée', 'arrivee' => 'Arrivée', 'noshow' => 'No-show', 'annulee' => 'Annulée'];

    public static function key(string $code, string $what): string
    {
        return "reservation:$code:$what";
    }

    public static function defaultConfig(string $name): array
    {
        return [
            'name' => $name,
            'serviceWindows' => [
                ['id' => 'sw-dej', 'label' => 'Déjeuner', 'start' => '12:00', 'end' => '14:00'],
                ['id' => 'sw-din', 'label' => 'Dîner', 'start' => '19:00', 'end' => '22:00'],
            ],
            'slotMax' => (object) [], 'closedSlots' => (object) [],
            'zones' => [['id' => 'salle', 'name' => 'Salle'], ['id' => 'terrasse', 'name' => 'Terrasse']],
            'maxPartySize' => 12, 'reservationDurationMin' => 90,
        ];
    }

    /** Créneaux de 15 min dans les plages de service (règle v1). */
    public static function allSlots(array $config): array
    {
        $set = [];
        foreach ($config['serviceWindows'] ?? [] as $w) {
            [$h, $m] = array_map('intval', explode(':', $w['start']));
            [$eh, $em] = array_map('intval', explode(':', $w['end']));
            for ($cur = $h * 60 + $m; $cur < $eh * 60 + $em; $cur += 15) $set[sprintf('%02d:%02d', intdiv($cur, 60), $cur % 60)] = true;
        }
        $slots = array_keys($set);
        sort($slots);
        return $slots;
    }

    public static function isSlotOffered(array $config, string $date, string $slot): bool
    {
        $max = ((array) ($config['slotMax'] ?? []))[$slot] ?? null;
        if ($max === 0) return false;
        return !in_array($slot, ((array) ($config['closedSlots'] ?? []))[$date] ?? [], true);
    }

    /**
     * Places restantes par créneau (même calcul que la v1 : tables de la zone moins réservations prises,
     * plafonné par le maximum du créneau). Les créneaux passés du jour même sont retirés.
     */
    public static function availability(array $config, array $tables, array $avail, string $date, int $party): array
    {
        // shortcut: sans plan de salle enregistré, 30 tables comme le plan par défaut de la v1 ; à remplacer par le module plan de salle
        $total = count($tables) ?: 30;
        $now = Clock::now();
        $out = [];
        foreach (self::allSlots($config) as $slot) {
            if (!self::isSlotOffered($config, $date, $slot)) continue;
            if ($date === $now->format('Y-m-d') && $slot <= $now->format('H:i')) continue;
            $taken = count(array_filter($avail, fn($r) => $r['date'] === $date && $r['slot'] === $slot && ($r['status'] ?? '') !== 'annulee'));
            $count = max(0, $total - $taken);
            $max = ((array) ($config['slotMax'] ?? []))[$slot] ?? null;
            if (is_int($max) && $max > 0) $count = min($count, max(0, $max - $taken));
            $out[] = ['slot' => $slot, 'count' => $count];
        }
        return $out;
    }

    /** Vue publique allégée, sans coordonnées (format v1). */
    public static function light(array $r): array
    {
        $tables = $r['tableIds'] ?? (isset($r['tableId']) ? [$r['tableId']] : []);
        return ['id' => $r['id'], 'tableIds' => $tables, 'date' => $r['date'], 'slot' => $r['slot'], 'party' => $r['party'], 'status' => $r['status']];
    }

    /** Valide une demande de réservation ; renvoie la réservation ou ['error' => message]. */
    public static function build(array $b, array $config): array
    {
        $date = (string) ($b['date'] ?? '');
        $slot = (string) ($b['slot'] ?? '');
        $party = (int) ($b['party'] ?? 0);
        $first = trim((string) ($b['firstName'] ?? ''));
        $last = trim((string) ($b['lastName'] ?? ''));
        $phone = trim((string) ($b['phone'] ?? ''));
        $email = trim((string) ($b['email'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < Clock::today()) return ['error' => 'Choisis une date à venir.'];
        if ($date > Clock::now()->modify('+180 days')->format('Y-m-d')) return ['error' => 'Les réservations ouvrent six mois à l\'avance au plus.'];
        if (!in_array($slot, self::allSlots($config), true) || !self::isSlotOffered($config, $date, $slot)) return ['error' => 'Ce créneau n\'est pas proposé.'];
        if ($party < 1 || $party > (int) ($config['maxPartySize'] ?? 12)) return ['error' => 'Pour plus de ' . (int) ($config['maxPartySize'] ?? 12) . ' personnes, appelle directement le restaurant.'];
        if ($first === '' || $last === '') return ['error' => 'Indique ton prénom et ton nom.'];
        if (!preg_match('/^[+0-9 ().-]{6,20}$/', $phone)) return ['error' => 'Indique un numéro de téléphone valide.'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['error' => 'Indique une adresse e-mail valide.'];
        return [
            'id' => App::id('r'), 'tableIds' => [], 'date' => $date, 'slot' => $slot, 'party' => $party,
            'firstName' => mb_substr($first, 0, 60), 'lastName' => mb_substr($last, 0, 60), 'name' => mb_substr("$first $last", 0, 121),
            'phone' => $phone, 'email' => $email, 'notes' => mb_substr(trim((string) ($b['notes'] ?? '')), 0, 500),
            'status' => 'confirmee', 'createdAt' => (int) (microtime(true) * 1000), 'staffSeen' => false,
            'cancelToken' => bin2hex(random_bytes(16)),
        ];
    }
}

/** Limite de débit très simple par adresse IP (fichier temporaire), contre le spam de la page publique. */
final class RateLimit
{
    public static function hit(string $bucket, int $max, int $windowSec): bool
    {
        $file = sys_get_temp_dir() . '/rondo-rl-' . hash('sha256', $bucket);
        $now = time();
        $fh = fopen($file, 'c+');
        flock($fh, LOCK_EX);
        $hits = array_filter(json_decode(stream_get_contents($fh) ?: '[]', true) ?: [], fn($t) => $t > $now - $windowSec);
        $ok = count($hits) < $max;
        if ($ok) $hits[] = $now;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, json_encode(array_values($hits)));
        flock($fh, LOCK_UN);
        fclose($fh);
        return $ok;
    }
}
