<?php
use Rondo\{App, Clock, RateLimit, Reservation, Team};

// ---------- page publique : aucune session, aucune coordonnée renvoyée ----------

function public_code(string $code): string
{
    $code = Team::slugify($code);
    if (!$code || !App::anon()->get("restaurant:$code")) App::fail(404, 'Établissement introuvable.');
    return $code;
}

function resa_config(Rondo\Store $s, string $code): array
{
    $rec = $s->get("restaurant:$code") ?: [];
    return ($s->get(Reservation::key($code, 'config')) ?: []) + Reservation::defaultConfig($rec['name'] ?? $code);
}

Flight::route('GET /api/public/@code', function (string $code) {
    $code = public_code($code);
    $s = App::anon();
    $c = resa_config($s, $code);
    Flight::json(['code' => $code, 'name' => $c['name'], 'maxPartySize' => (int) $c['maxPartySize'], 'today' => Clock::today(), 'theme' => $s->get(Reservation::key($code, 'theme'))]);
});

Flight::route('GET /api/public/@code/disponibilites', function (string $code) {
    $code = public_code($code);
    $q = Flight::request()->query;
    $date = (string) ($q['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) App::fail(422, 'Date invalide.');
    $s = App::anon();
    $slots = Reservation::availability(resa_config($s, $code), $s->get(Reservation::key($code, 'tables')) ?: [], $s->get(Reservation::key($code, 'availability')) ?: [], $date, max(1, (int) ($q['party'] ?? 2)));
    Flight::json(['date' => $date, 'slots' => $slots]);
});

Flight::route('POST /api/public/@code/reservations', function (string $code) {
    $code = public_code($code);
    if (!RateLimit::hit('resa:' . ($_SERVER['REMOTE_ADDR'] ?? '?'), 5, 3600)) App::fail(429, 'Trop de réservations depuis cet appareil. Appelle le restaurant.');
    $b = App::body();
    if (!empty($b['website'])) App::fail(422, 'Réservation refusée.'); // champ piège invisible pour les robots
    $s = App::anon();
    $config = resa_config($s, $code);
    $resa = Reservation::build($b, $config);
    if (isset($resa['error'])) App::fail(422, $resa['error']);
    $left = current(array_filter(Reservation::availability($config, $s->get(Reservation::key($code, 'tables')) ?: [], $s->get(Reservation::key($code, 'availability')) ?: [], $resa['date'], $resa['party']), fn($x) => $x['slot'] === $resa['slot']));
    if (!$left || $left['count'] < 1) App::fail(409, 'Ce créneau vient d\'être pris. Choisis-en un autre.');
    $s->mutate(Reservation::key($code, 'reservations'), fn($l) => [...$l, $resa], []);
    $s->mutate(Reservation::key($code, 'availability'), fn($l) => [...$l, Reservation::light($resa)], []);
    // shortcut: e-mail de confirmation (EmailJS en v1) non branché côté serveur ; le lien d'annulation est affiché à l'écran
    Flight::json(['id' => $resa['id'], 'date' => $resa['date'], 'slot' => $resa['slot'], 'party' => $resa['party'], 'cancelToken' => $resa['cancelToken']], 201);
});

Flight::route('POST /api/public/@code/annulation', function (string $code) {
    $code = public_code($code);
    $token = (string) (App::body()['token'] ?? '');
    if (strlen($token) < 8) App::fail(422, 'Lien d\'annulation invalide.');
    $s = App::anon();
    $found = null;
    $s->mutate(Reservation::key($code, 'reservations'), function ($l) use ($token, &$found) {
        foreach ($l as &$r) {
            // jeton v2, ou identifiant pour les liens déjà envoyés par la v1
            $match = isset($r['cancelToken']) ? hash_equals($r['cancelToken'], $token) : $r['id'] === $token;
            if ($match) { $found = $r; if ($r['status'] === 'confirmee') { $r['status'] = 'annulee'; $r['cancelledAt'] = (int) (microtime(true) * 1000); } }
        }
        return $l;
    }, []);
    if (!$found) App::fail(404, 'Cette réservation n\'existe plus.');
    if ($found['status'] === 'confirmee') $s->mutate(Reservation::key($code, 'availability'), fn($l) => array_map(fn($r) => $r['id'] === $found['id'] ? ['status' => 'annulee'] + $r : $r, $l), []);
    Flight::json(['date' => $found['date'], 'slot' => $found['slot'], 'alreadyCancelled' => $found['status'] === 'annulee']);
});

// ---------- espace pro (permission « reservation ») ----------

Flight::route('GET /api/reservations', function () {
    $code = App::code();
    App::can('reservation');
    $date = (string) (Flight::request()->query['date'] ?? Clock::today());
    $s = App::store();
    $all = $s->get(Reservation::key($code, 'reservations')) ?: [];
    $day = array_values(array_filter($all, fn($r) => $r['date'] === $date));
    usort($day, fn($a, $b) => strcmp($a['slot'], $b['slot']));
    $config = resa_config($s, $code);
    Flight::json([
        'date' => $date, 'config' => $config, 'statuses' => Reservation::STATUSES,
        'reservations' => array_map(fn($r) => array_diff_key($r, ['cancelToken' => 1]), $day),
        'unseen' => count(array_filter($all, fn($r) => empty($r['staffSeen']) && $r['date'] >= Clock::today())),
        'slots' => Reservation::allSlots($config),
    ]);
});

Flight::route('POST /api/reservations', function () {
    $code = App::code();
    App::can('reservation');
    $s = App::store();
    $b = App::body() + ['email' => 'sur-place@rondo.invalid'];
    $resa = Reservation::build($b, ['maxPartySize' => 60] + resa_config($s, $code));
    if (isset($resa['error'])) App::fail(422, $resa['error']);
    $resa['staffSeen'] = true;
    $resa['notes'] = $resa['notes'] ?: 'Ajout manuel';
    $s->mutate(Reservation::key($code, 'reservations'), fn($l) => [...$l, $resa], []);
    $s->mutate(Reservation::key($code, 'availability'), fn($l) => [...$l, Reservation::light($resa)], []);
    Flight::json(['reservation' => $resa], 201);
});

Flight::route('PATCH /api/reservations/@id', function (string $id) {
    $code = App::code();
    App::can('reservation');
    $status = (string) (App::body()['status'] ?? '');
    if (!isset(Reservation::STATUSES[$status])) App::fail(422, 'Statut inconnu.');
    $s = App::store();
    $found = false;
    $s->mutate(Reservation::key($code, 'reservations'), function ($l) use ($id, $status, &$found) {
        foreach ($l as &$r) if ($r['id'] === $id) { $r['status'] = $status; $r['staffSeen'] = true; $found = true; }
        return $l;
    }, []);
    if (!$found) App::fail(404, 'Réservation introuvable.');
    $s->mutate(Reservation::key($code, 'availability'), fn($l) => array_map(fn($r) => $r['id'] === $id ? ['status' => $status] + $r : $r, $l), []);
    Flight::json(['ok' => true]);
});

Flight::route('POST /api/reservations/vues', function () {
    $code = App::code();
    App::can('reservation');
    App::store()->mutate(Reservation::key($code, 'reservations'), fn($l) => array_map(fn($r) => ['staffSeen' => true] + $r, $l), []);
    Flight::json(['ok' => true]);
});
