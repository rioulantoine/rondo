<?php
use Rondo\{App, Clock, Pointage, Team};

// Badgeuse. L'heure retenue est celle du serveur ; le PIN est vérifié à chaque badgeage.

const KIOSK_COOKIE = 'rondo_kiosk';

/** Appareil déclaré « tablette du restaurant » par le responsable (cookie vérifié côté serveur). */
function is_kiosk(string $code): bool
{
    $token = $_COOKIE[KIOSK_COOKIE] ?? '';
    if ($token === '') return false;
    $hash = hash('sha256', $token);
    foreach (App::store()->get("config:$code:kioskDevices", []) ?: [] as $d) if (hash_equals($d['hash'], $hash)) return true;
    return false;
}

Flight::route('GET /api/pointage', function () {
    $code = App::code();
    App::person();
    $s = App::store();
    $events = $s->get(Pointage::key($code, Clock::month()), []) ?: [];
    $members = array_map(function ($m) use ($events) {
        $day = Pointage::dayEvents($events, $m['id'], Clock::today());
        $arrival = current(array_filter($day, fn($e) => $e['type'] === 'arrivee'));
        return ['id' => $m['id'], 'name' => $m['name'], 'forfaitJour' => !empty($m['forfaitJour']),
            'status' => Pointage::status($day), 'since' => $day ? end($day)['ts'] : null, 'arrivedAt' => $arrival['ts'] ?? null];
    }, Team::all($s, $code));
    $settings = $s->get("config:$code:pointageSettings") ?: ['pauseEnabled' => false];
    Flight::json(['kiosk' => is_kiosk($code), 'pauseEnabled' => !empty($settings['pauseEnabled']), 'members' => $members, 'serverTime' => Clock::iso()]);
});

Flight::route('POST /api/pointage', function () {
    $code = App::code();
    App::person();
    $b = App::body();
    $s = App::store();
    $memberId = (string) ($b['memberId'] ?? '');
    $member = Team::find($s, $code, $memberId);
    if (!$member) App::fail(404, 'Personne introuvable.');

    $kiosk = is_kiosk($code);
    if (!$kiosk && empty($member['canBadgeFromPersonalDevice'])) {
        App::fail(403, 'Le pointage se fait sur la tablette du restaurant. Le responsable peut t\'autoriser à pointer depuis ton téléphone.');
    }
    $r = Team::verifyPin($s, $code, $memberId, (string) ($b['pin'] ?? ''));
    if (!empty($r['locked'])) App::fail(423, 'Trop d\'essais. Réessaie dans quelques minutes.');
    if (!$r['ok']) Flight::jsonHalt(['error' => 'Code incorrect.', 'code' => 'pin'], 401);

    $settings = $s->get("config:$code:pointageSettings") ?: [];
    $type = (string) ($b['type'] ?? '');
    if (in_array($type, ['pause', 'pause_debut', 'pause_fin'], true) && empty($settings['pauseEnabled'])) App::fail(422, 'Le pointage des pauses est désactivé.');
    $signature = (string) ($b['signature'] ?? '');
    if ($signature !== '' && (!str_starts_with($signature, 'data:image/') || strlen($signature) > 60000)) App::fail(422, 'Signature illisible, recommence.');

    $rec = $s->get("restaurant:$code") ?: [];
    $shifts = $s->get("log:$code:planning:" . Clock::month(), []) ?: [];
    $error = null;
    $event = null;
    // contrôle et ajout dans la même écriture protégée : deux badgeages simultanés ne se perdent pas
    $s->mutate(Pointage::key($code, Clock::month()), function ($events) use ($member, $type, $shifts, $b, $rec, $signature, &$error, &$event) {
        $e = Pointage::build($events, $member, $type, $shifts, [
            'justification' => $b['justification'] ?? '', 'lat' => $b['lat'] ?? null, 'lng' => $b['lng'] ?? null,
            'restaurantLocation' => $rec['location'] ?? null, 'signature' => $signature,
        ]);
        if (isset($e['error'])) { $error = $e; return $events; }
        $event = $e;
        return [...$events, $e];
    }, []);
    if ($error) Flight::jsonHalt(['error' => $error['message'], 'code' => $error['error']], 422);
    unset($event['signature']);
    Flight::json(['event' => $event, 'label' => Pointage::TYPES[$event['type']]], 201);
});

Flight::route('POST /api/pointage/tablette', function () {
    $code = App::code();
    App::owner();
    $token = bin2hex(random_bytes(32));
    App::store()->mutate("config:$code:kioskDevices", fn($l) => [...$l, ['hash' => hash('sha256', $token), 'createdAt' => Clock::iso()]], []);
    setcookie(KIOSK_COOKIE, $token, ['expires' => time() + 400 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
    Flight::json(['kiosk' => true]);
});

Flight::route('DELETE /api/pointage/tablette', function () {
    App::code();
    setcookie(KIOSK_COOKIE, '', ['expires' => 1, 'path' => '/']);
    Flight::json(['kiosk' => false]);
});
