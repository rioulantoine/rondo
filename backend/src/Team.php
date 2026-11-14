<?php
namespace Rondo;

/** Équipe d'un établissement (`config:{code}:team`), format v1. */
final class Team
{
    const ALL_PERMS = ['cuisine', 'salle', 'bar', 'reservation', 'planning', 'incidents', 'absences', 'validateSwaps'];
    const PIN_MAX_ATTEMPTS = 5;
    const PIN_LOCKOUT_MS = 5 * 60 * 1000;

    public static function key(string $code): string
    {
        return "config:$code:team";
    }

    /** Reprend la normalisation de la v1 (anciens champs `zone`). */
    public static function normalize(array $m): array
    {
        if (!isset($m['permissions'])) {
            $m['permissions'] = match ($m['zone'] ?? null) {
                'cuisine' => ['cuisine'],
                'salle_bar' => ['salle', 'bar', 'reservation'],
                default => [],
            };
        }
        $m['postes'] ??= array_values(array_intersect($m['permissions'], ['cuisine', 'salle', 'bar']));
        return $m;
    }

    /** Ce que le navigateur a le droit de voir : jamais le PIN ni les compteurs d'essais. */
    public static function public(array $m): array
    {
        unset($m['pin'], $m['failedPinAttempts'], $m['pinLockedUntil']);
        return $m;
    }

    public static function all(Store $s, string $code): array
    {
        return array_map(self::normalize(...), $s->get(self::key($code), []) ?: []);
    }

    public static function find(Store $s, string $code, string $id): ?array
    {
        foreach (self::all($s, $code) as $m) if (($m['id'] ?? null) === $id) return $m;
        return null;
    }

    /**
     * Vérifie le PIN côté serveur. 5 erreurs = 5 minutes de blocage (règle v1).
     * @return array{ok:bool, member?:array, locked?:bool, retryAt?:int, attemptsLeft?:int}
     */
    public static function verifyPin(Store $s, string $code, string $id, string $pin): array
    {
        $nowMs = Clock::now()->getTimestamp() * 1000;
        $result = ['ok' => false];
        $s->mutate(self::key($code), function ($team) use ($id, $pin, $nowMs, &$result) {
            foreach ($team as &$m) {
                if (($m['id'] ?? null) !== $id) continue;
                if (($m['pinLockedUntil'] ?? 0) > $nowMs) {
                    $result = ['ok' => false, 'locked' => true, 'retryAt' => (int) $m['pinLockedUntil']];
                    return $team;
                }
                if (isset($m['pin']) && hash_equals((string) $m['pin'], $pin)) {
                    $m['failedPinAttempts'] = 0;
                    $m['pinLockedUntil'] = null;
                    $result = ['ok' => true, 'member' => self::normalize($m)];
                    return $team;
                }
                $m['failedPinAttempts'] = ($m['failedPinAttempts'] ?? 0) + 1;
                if ($m['failedPinAttempts'] >= self::PIN_MAX_ATTEMPTS) {
                    $m['pinLockedUntil'] = $nowMs + self::PIN_LOCKOUT_MS;
                    $m['failedPinAttempts'] = 0;
                    $result = ['ok' => false, 'locked' => true, 'retryAt' => $m['pinLockedUntil']];
                } else {
                    $result = ['ok' => false, 'attemptsLeft' => self::PIN_MAX_ATTEMPTS - $m['failedPinAttempts']];
                }
                return $team;
            }
            return $team;
        }, []);
        return $result;
    }

    /** Identité de session d'une personne. */
    public static function person(array $m): array
    {
        return ['id' => $m['id'], 'name' => $m['name'], 'owner' => false, 'perms' => $m['permissions'], 'forfaitJour' => !empty($m['forfaitJour'])];
    }

    public static function owner(): array
    {
        return ['id' => 'owner', 'name' => 'Responsable', 'owner' => true, 'perms' => self::ALL_PERMS, 'forfaitJour' => false];
    }

    public static function slugify(string $s): string
    {
        // même résultat que le slugify() de la v1 : minuscules, accents retirés (NFD), le reste devient « - »
        $s = preg_replace('/\p{Mn}+/u', '', \Normalizer::normalize(mb_strtolower($s), \Normalizer::FORM_D));
        return trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    }

    public static function authEmail(string $code, ?array $rec): string
    {
        return ($rec['recoveryEmail'] ?? null) ?: "$code@resto.checktonresto.local";
    }
}
