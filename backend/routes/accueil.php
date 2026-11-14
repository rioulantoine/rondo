<?php
use Rondo\{App, Clock, Haccp, Planning, Pointage, Team};

// Tableau de bord de l'accueil : une seule requête au lieu d'une vingtaine de lectures côté navigateur (v1).

Flight::route('GET /api/accueil', function () {
    $code = App::code();
    $p = App::person();
    $s = App::store();
    $today = Clock::today();

    $spaces = array_values(array_filter(array_keys(Haccp::SPACES), fn($sp) => $p['owner'] || in_array($sp, $p['perms'], true)));
    $events = $s->get(Pointage::key($code, Clock::month())) ?: [];

    $me = null;
    if (!$p['owner']) {
        $member = Team::find($s, $code, $p['id']);
        $next = Planning::myShifts($s, $code, $p['name'], $today, Clock::now()->modify('+13 days')->format('Y-m-d'));
        $me = [
            'status' => Pointage::status(Pointage::dayEvents($events, $p['id'], $today)),
            'canBadgeHere' => !empty($member['canBadgeFromPersonalDevice']),
            'today' => array_values(array_filter($next, fn($sh) => $sh['date'] === $today)),
            'next' => current(array_filter($next, fn($sh) => $sh['date'] > $today)) ?: null,
        ];
    }

    $alerts = array_values(array_filter($s->get("config:$code:alerts") ?: [], fn($a) => !empty($a['active'])
        && ($p['owner'] || ($a['target'] ?? 'tous') === 'tous' || in_array($a['target'], $p['perms'], true))));

    $owner = null;
    if ($p['owner']) {
        $pending = array_values(array_filter($events, fn($e) => !empty($e['offSchedule']) && empty($e['validated'])));
        $owner = ['pendingBadges' => array_map(fn($e) => array_diff_key($e, ['signature' => 1, 'location' => 1]), $pending)];
    }

    Flight::json([
        'today' => $today,
        'spaces' => array_map(fn($sp) => Haccp::today($s, $code, $sp), $spaces),
        'me' => $me,
        'alerts' => array_map(fn($a) => ['id' => $a['id'], 'message' => $a['message'], 'target' => $a['target'] ?? 'tous', 'createdBy' => $a['createdBy'] ?? '', 'createdAt' => $a['createdAt'] ?? null], $alerts),
        'owner' => $owner,
    ]);
});

/** Validation d'un pointage hors planning (responsable). */
Flight::route('POST /api/pointage/@id/validation', function (string $id) {
    $code = App::code();
    $p = App::owner();
    $month = (string) (Flight::request()->query['month'] ?? Clock::month());
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) App::fail(422, 'Mois invalide.');
    $found = false;
    App::store()->mutate(Pointage::key($code, $month), function ($l) use ($id, $p, &$found) {
        foreach ($l as &$e) if ($e['id'] === $id) { $e['validated'] = true; $e['validatedAt'] = Clock::iso(); $e['validatedBy'] = $p['name']; $found = true; }
        return $l;
    }, []);
    if (!$found) App::fail(404, 'Pointage introuvable.');
    Flight::json(['ok' => true]);
});
