<?php
use Rondo\{App, Clock, Haccp};

// Registres HACCP d'un espace (cuisine, salle, bar). Clés Firestore identiques à la v1.

function haccp_space(string $sp): array
{
    if (!isset(Haccp::SPACES[$sp])) App::fail(404, 'Espace inconnu.');
    return [App::code(), App::can($sp)];
}

function haccp_month(): string
{
    $m = (string) (Flight::request()->query['month'] ?? '');
    return preg_match('/^\d{4}-\d{2}$/', $m) ? $m : Clock::month();
}

Flight::route('GET /api/registres', function () {
    $code = App::code();
    $p = App::person();
    $s = App::store();
    $spaces = array_filter(array_keys(Haccp::SPACES), fn($sp) => $p['owner'] || in_array($sp, $p['perms'], true));
    Flight::json(array_map(fn($sp) => Haccp::today($s, $code, $sp), array_values($spaces)));
});

Flight::route('GET /api/registres/@sp', function (string $sp) {
    [$code] = haccp_space($sp);
    $s = App::store();
    $month = haccp_month();
    Flight::json([
        'space' => $sp, 'label' => Haccp::SPACES[$sp]['label'], 'month' => $month, 'today' => Clock::today(),
        'periods' => Haccp::PERIODS, 'currentPeriod' => Haccp::currentPeriod(), 'traceTypes' => Haccp::TRACE_TYPES,
        'equipments' => Haccp::equipments($s, $code, $sp),
        'tasks' => Haccp::tasks($s, $code, $sp),
        'taskLog' => $s->get("log:$code:$sp:tasks:" . Clock::today()) ?: (object) [],
        'temps' => $s->get("log:$code:$sp:temp:$month") ?: [],
        'traces' => $s->get("log:$code:$sp:trace:$month") ?: [],
        'summary' => Haccp::today($s, $code, $sp),
    ]);
});

Flight::route('POST /api/registres/@sp/temperatures', function (string $sp) {
    [$code, $p] = haccp_space($sp);
    $b = App::body();
    $s = App::store();
    $eq = current(array_filter(Haccp::equipments($s, $code, $sp), fn($e) => $e['id'] === ($b['equipmentId'] ?? null)));
    if (!$eq) App::fail(404, 'Point de contrôle introuvable.');
    $entry = Haccp::temperature($eq, $b['value'] ?? null, (string) ($b['comment'] ?? ''), (string) ($b['period'] ?? ''), $p['name']);
    if (isset($entry['error'])) App::fail(422, $entry['error']);
    $list = $s->mutate("log:$code:$sp:temp:" . Clock::month(), fn($l) => [...$l, $entry], []);
    // shortcut: l'e-mail d'alerte critique (EmailJS en v1, en pause) n'est pas encore branché
    Flight::json(['entry' => $entry, 'number' => count($list)], 201);
});

Flight::route('POST /api/registres/@sp/taches/@id', function (string $sp, string $id) {
    [$code, $p] = haccp_space($sp);
    $done = (bool) (App::body()['done'] ?? true);
    $log = App::store()->mutate("log:$code:$sp:tasks:" . Clock::today(), function ($log) use ($id, $done, $p) {
        $log = (array) $log;
        if ($done) $log[$id] = ['done' => true, 'ts' => Clock::iso(), 'staff' => $p['name']];
        else unset($log[$id]);
        return $log ?: (object) [];
    }, []);
    Flight::json(['taskLog' => $log]);
});

Flight::route('POST /api/registres/@sp/tracabilite', function (string $sp) {
    [$code, $p] = haccp_space($sp);
    $b = App::body();
    $type = (string) ($b['type'] ?? '');
    $product = trim((string) ($b['product'] ?? ''));
    if (!isset(Haccp::TRACE_TYPES[$type])) App::fail(422, "Type d'entrée inconnu.");
    if ($product === '') App::fail(422, 'Indique le produit.');
    $date = (string) ($b['date'] ?? '');
    if ($type === 'dlc' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) App::fail(422, 'Indique la DLC.');
    $temp = ($b['temp'] ?? '') === '' || ($b['temp'] ?? null) === null ? null : (float) $b['temp'];
    $entry = [
        'id' => App::id(), 'ts' => Clock::iso(), 'type' => $type, 'product' => $product, 'date' => $date,
        'lot' => trim((string) ($b['lot'] ?? '')), 'supplier' => trim((string) ($b['supplier'] ?? '')),
        'temp' => $temp, 'staff' => $p['name'], 'period' => Haccp::currentPeriod(),
    ];
    $list = App::store()->mutate("log:$code:$sp:trace:" . Clock::month(), fn($l) => [...$l, $entry], []);
    Flight::json(['entry' => $entry, 'number' => count($list)], 201);
});

/** Produit retiré et jeté : l'alerte DLC disparaît, l'entrée reste au registre. */
Flight::route('POST /api/registres/@sp/tracabilite/@id/retrait', function (string $sp, string $id) {
    [$code, $p] = haccp_space($sp);
    $month = haccp_month();
    $found = false;
    App::store()->mutate("log:$code:$sp:trace:$month", function ($l) use ($id, $p, &$found) {
        foreach ($l as &$e) if ($e['id'] === $id) { $e['removedAt'] = Clock::iso(); $e['removedBy'] = $p['name']; $found = true; }
        return $l;
    }, []);
    if (!$found) App::fail(404, 'Entrée introuvable.');
    Flight::json(['ok' => true]);
});

// ---------- réglages (responsable) ----------

Flight::route('PUT /api/registres/@sp/equipements', function (string $sp) {
    [$code] = haccp_space($sp);
    App::owner();
    $list = [];
    foreach ((array) (App::body()['equipments'] ?? []) as $e) {
        $name = trim((string) ($e['name'] ?? ''));
        if ($name === '' || !is_numeric($e['min'] ?? null) || !is_numeric($e['max'] ?? null)) App::fail(422, 'Chaque point de contrôle a un nom, un mini et un maxi.');
        $list[] = ['id' => (string) ($e['id'] ?? '') ?: App::id(), 'name' => $name, 'min' => min((float) $e['min'], (float) $e['max']), 'max' => max((float) $e['min'], (float) $e['max'])];
    }
    App::store()->set("config:$code:$sp:equipments", $list);
    Flight::json(['equipments' => $list]);
});

Flight::route('PUT /api/registres/@sp/taches', function (string $sp) {
    [$code] = haccp_space($sp);
    App::owner();
    $list = [];
    foreach ((array) (App::body()['tasks'] ?? []) as $t) {
        $label = trim((string) ($t['label'] ?? ''));
        if ($label === '') App::fail(422, 'Une tâche ne peut pas être vide.');
        $list[] = ['id' => (string) ($t['id'] ?? '') ?: App::id(), 'label' => $label, 'moment' => in_array($t['moment'] ?? '', Haccp::PERIODS, true) ? $t['moment'] : Haccp::PERIODS[0]];
    }
    App::store()->set("config:$code:$sp:tasks", $list);
    // instantané du jour, comme la v1 (l'export PDF retrouve les tâches telles qu'elles étaient)
    App::store()->set("log:$code:$sp:taskdefs:" . Clock::today(), $list);
    Flight::json(['tasks' => $list]);
});

/** Données du rapport de contrôle sanitaire (cuisine, salle, bar réunis) sur une période. */
Flight::route('GET /api/rapport-haccp', function () {
    $code = App::code();
    App::owner();
    $q = Flight::request()->query;
    $from = (string) ($q['du'] ?? '');
    $to = (string) ($q['au'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) App::fail(422, 'Période invalide.');
    $days = (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1;
    if ($days > 93) App::fail(422, 'Trois mois au plus par rapport.');
    $s = App::store();
    $months = [];
    for ($d = new DateTimeImmutable(substr($from, 0, 7) . '-01'); $d->format('Y-m') <= substr($to, 0, 7); $d = $d->modify('+1 month')) $months[] = $d->format('Y-m');
    $inRange = fn($e) => ($day = Clock::paris($e['ts'])->format('Y-m-d')) >= $from && $day <= $to;
    $spaces = [];
    foreach (Haccp::SPACES as $sp => $def) {
        $temps = $traces = [];
        foreach ($months as $m) {
            foreach ($s->get("log:$code:$sp:temp:$m") ?: [] as $i => $e) if ($inRange($e)) $temps[] = ['n' => $i + 1] + $e;
            foreach ($s->get("log:$code:$sp:trace:$m") ?: [] as $i => $e) if ($inRange($e)) $traces[] = ['n' => $i + 1] + $e;
        }
        $tasks = [];
        $total = count(Haccp::tasks($s, $code, $sp));
        for ($d = new DateTimeImmutable($from); $d->format('Y-m-d') <= $to; $d = $d->modify('+1 day')) {
            $log = $s->get("log:$code:$sp:tasks:" . $d->format('Y-m-d')) ?: [];
            $tasks[] = ['date' => $d->format('Y-m-d'), 'done' => count(array_filter((array) $log, fn($t) => !empty($t['done']))), 'total' => $total];
        }
        $spaces[] = ['space' => $sp, 'label' => $def['label'], 'temps' => $temps, 'traces' => $traces, 'tasks' => $tasks];
    }
    $rec = $s->get("restaurant:$code") ?: [];
    Flight::json(['restaurant' => $rec['name'] ?? $code, 'from' => $from, 'to' => $to, 'exportedAt' => Clock::iso(), 'spaces' => $spaces]);
});
