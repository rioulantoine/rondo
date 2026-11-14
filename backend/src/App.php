<?php
namespace Rondo;

use Flight;

/** Accès partagés par les routes : configuration, session, stockage, contrôle d'accès. */
final class App
{
    /** Remplacé dans les tests par un MemoryStore. */
    public static ?Store $store = null;

    public static function env(string $k, string $default = ''): string
    {
        $v = getenv($k);
        return $v === false || $v === '' ? $default : $v;
    }

    public static function projectId(): string
    {
        // valeurs publiques par conception (comme dans la v1) ; surchargées par l'environnement
        return self::env('FIREBASE_PROJECT_ID', 'check-ta-cuisine-c752c');
    }

    public static function auth(): FirebaseAuth
    {
        return new FirebaseAuth(self::env('FIREBASE_API_KEY', 'AIzaSyD4X3KyTYXQxULrhGKJdY9QeAaVtvfvZBQ'), self::env('FIREBASE_AUTH_EMULATOR_HOST') ?: null);
    }

    /** Stockage avec les droits de l'établissement connecté (jeton rafraîchi au besoin). */
    public static function store(): Store
    {
        if (self::$store) return self::$store;
        return new Firestore(self::projectId(), function () {
            $a = $_SESSION['auth'] ?? null;
            if (!$a) return null;
            if ($a['exp'] < time()) {
                $a = self::auth()->refresh($a['refreshToken']);
                if (!$a) { unset($_SESSION['auth']); throw new StoreException('Session expirée, reconnecte-toi.', 401); }
                $_SESSION['auth'] = $a;
            }
            return $a['idToken'];
        }, self::env('FIRESTORE_EMULATOR_HOST') ?: null);
    }

    /** Stockage anonyme (page publique de réservation, lecture de la fiche avant connexion). */
    public static function anon(): Store
    {
        return self::$store ?? new Firestore(self::projectId(), fn() => null, self::env('FIRESTORE_EMULATOR_HOST') ?: null);
    }

    public static function body(): array
    {
        return (array) Flight::request()->data->getData();
    }

    public static function fail(int $status, string $message): never
    {
        Flight::jsonHalt(['error' => $message], $status);
        exit;
    }

    public static function code(): string
    {
        return $_SESSION['code'] ?? self::fail(401, 'Connecte ton établissement.');
    }

    public static function person(): array
    {
        self::code();
        return $_SESSION['person'] ?? self::fail(401, 'Choisis qui tu es.');
    }

    /** Contrôle côté serveur (la v1 ne le faisait que dans le navigateur). */
    public static function can(string $perm): array
    {
        $p = self::person();
        if (!$p['owner'] && !in_array($perm, $p['perms'], true)) self::fail(403, "Tu n'as pas accès à cet espace.");
        return $p;
    }

    public static function owner(): array
    {
        $p = self::person();
        if (!$p['owner']) self::fail(403, 'Réservé au responsable.');
        return $p;
    }

    public static function id(string $prefix = ''): string
    {
        return $prefix . base_convert((string) (int) (microtime(true) * 1000), 10, 36) . substr(bin2hex(random_bytes(4)), 0, 5);
    }
}
