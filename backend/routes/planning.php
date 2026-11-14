<?php
use Rondo\{App, Clock, Planning, Team};

// Planning d'équipe. Écriture : responsable ou permission « planning ». Lecture employé : ses créneaux publiés.

function planning_editor(): bool
{
    $p = App::person();
    return $p['owner'] || in_array('planning', $p['perms'], true);
}

function planning_week_param(): string
{
    $w = (string) (Flight::request()->query['semaine'] ?? '');
    return Planning::weekStart(preg_match('/^\d{4}-\d{2}-\d{2}$/', $w) ? $w : Clock::today());
}

/** Valide et normalise un créneau envoyé par le navigateur. */
function planning_shift(array $b, string $code): array
{
    $staff = trim((string) ($b['staff'] ?? ''));
    $names = array_column(Team::all(App::store(), $code), 'name');
    if (!in_array($staff, $names, true)) App::fail(422, 'Choisis une personne de l\'équipe.');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($b['date'] ?? ''))) App::fail(422, 'Date invalide.');
    foreach (['start', 'end'] as $k) if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string) ($b[$k] ?? ''))) App::fail(422, 'Indique les heures de début et de fin.');
    if ($b['start'] === $b['end']) App::fail(422, 'Le début et la fin sont identiques.');
    return [
        'staff' => $staff, 'date' => $b['date'], 'start' => $b['start'], 'end' => $b['end'],
        'space' => in_array($b['space'] ?? '', ['cuisine', 'salle', 'bar'], true) ? $b['space'] : '',
        'note' => mb_substr(trim((string) ($b['note'] ?? '')), 0, 200),
    ];
}

Flight::route('GET /api/planning', function () {
    $code = App::code();
    $p = App::person();
    $s = App::store();
    $week = planning_week_param();
    $shifts = Planning::week($s, $code, $week);
    $record = $s->get("config:$code:planningPublish") ?: [];
    $status = Planning::publishStatus($record, $week, $shifts);
    if (planning_editor()) {
        Flight::json([
            'editor' => true, 'week' => $week, 'days' => Planning::days($week), 'shifts' => $shifts, 'status' => $status,
            'team' => array_map(fn($m) => ['id' => $m['id'], 'name' => $m['name'], 'postes' => $m['postes'], 'weeklyHours' => $m['weeklyHours'] ?? null], Team::all($s, $code)),
            'alerts' => Planning::legalAlerts($shifts),
        ]);
        return;
    }
    Flight::json([
        'editor' => false, 'week' => $week, 'days' => Planning::days($week), 'status' => ['published' => $status['published']],
        'shifts' => $status['published'] ? array_values(array_filter($shifts, fn($sh) => $sh['staff'] === $p['name'])) : [],
    ]);
});

Flight::route('POST /api/planning/creneaux', function () {
    $code = App::code();
    if (!planning_editor()) App::fail(403, 'Réservé à qui gère le planning.');
    $sh = ['id' => App::id()] + planning_shift(App::body(), $code);
    App::store()->mutate(Planning::key($code, substr($sh['date'], 0, 7)), fn($l) => [...$l, $sh], []);
    Flight::json(['shift' => $sh], 201);
});

Flight::route('PUT /api/planning/creneaux/@id', function (string $id) {
    $code = App::code();
    if (!planning_editor()) App::fail(403, 'Réservé à qui gère le planning.');
    $b = App::body();
    $from = (string) ($b['fromDate'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) App::fail(422, 'Date d\'origine manquante.');
    $sh = ['id' => $id] + planning_shift($b, $code);
    $s = App::store();
    $found = false;
    $sameMonth = substr($from, 0, 7) === substr($sh['date'], 0, 7);
    $s->mutate(Planning::key($code, substr($from, 0, 7)), function ($l) use ($id, $sh, $sameMonth, &$found) {
        $out = [];
        foreach ($l as $x) {
            if ($x['id'] !== $id) { $out[] = $x; continue; }
            $found = true;
            if ($sameMonth) $out[] = $sh;
        }
        return $out;
    }, []);
    if (!$found) App::fail(404, 'Créneau introuvable.');
    if (!$sameMonth) $s->mutate(Planning::key($code, substr($sh['date'], 0, 7)), fn($l) => [...$l, $sh], []);
    Flight::json(['shift' => $sh]);
});

Flight::route('DELETE /api/planning/creneaux/@id', function (string $id) {
    $code = App::code();
    if (!planning_editor()) App::fail(403, 'Réservé à qui gère le planning.');
    $date = (string) (Flight::request()->query['date'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) App::fail(422, 'Date manquante.');
    App::store()->mutate(Planning::key($code, substr($date, 0, 7)), fn($l) => array_values(array_filter($l, fn($x) => $x['id'] !== $id)), []);
    Flight::json(['ok' => true]);
});

Flight::route('POST /api/planning/publication', function () {
    $code = App::code();
    $p = App::person();
    if (!planning_editor()) App::fail(403, 'Réservé à qui gère le planning.');
    $week = planning_week_param();
    $s = App::store();
    $snapshot = Planning::snapshot(Planning::week($s, $code, $week));
    $s->mutate("config:$code:planningPublish", function ($rec) use ($week, $snapshot, $p) {
        $rec = (array) $rec;
        $rec[$week] = ['publishedAt' => Clock::iso(), 'publishedBy' => $p['name'], 'snapshot' => $snapshot ?: (object) []];
        return $rec;
    }, []);
    // shortcut: les notices « ton planning a changé » (planningNotices v1) ne sont pas encore envoyées
    Flight::json(['status' => ['published' => true, 'upToDate' => true, 'publishedAt' => Clock::iso(), 'publishedBy' => $p['name']]]);
});
