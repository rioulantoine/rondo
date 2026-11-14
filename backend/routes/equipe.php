<?php
use Rondo\{App, Clock, Team};

// Équipe et accès (responsable). Le PIN s'écrit ici mais ne ressort jamais.

const POSTES = ['cuisine', 'salle', 'bar', 'ouvfer'];

/** Champs d'une fiche, validés. $current = fiche existante (le PIN vide la conserve). */
function team_member(array $b, ?array $current, array $team): array
{
    $name = trim((string) ($b['name'] ?? ''));
    if ($name === '') App::fail(422, 'Indique le nom.');
    foreach ($team as $m) if ($m['name'] === $name && $m['id'] !== ($current['id'] ?? null)) App::fail(409, 'Ce nom est déjà dans l\'équipe (le planning s\'appuie sur le nom).');
    $pin = (string) ($b['pin'] ?? '');
    if ($pin === '' && !$current) App::fail(422, 'Choisis un code PIN à 4 chiffres.');
    if ($pin !== '' && !preg_match('/^\d{4}$/', $pin)) App::fail(422, 'Le code PIN fait 4 chiffres.');
    foreach ($team as $m) if ($pin !== '' && ($m['pin'] ?? null) === $pin && $m['id'] !== ($current['id'] ?? null)) App::fail(409, 'Ce PIN est déjà pris par quelqu\'un de l\'équipe.');
    $email = trim((string) ($b['email'] ?? ''));
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) App::fail(422, 'Adresse e-mail invalide.');
    $hours = $b['weeklyHours'] ?? null;
    $m = array_merge($current ?? ['id' => App::id('m')], [
        'name' => $name,
        'permissions' => array_values(array_intersect(Team::ALL_PERMS, (array) ($b['permissions'] ?? []))),
        'postes' => array_values(array_intersect(POSTES, (array) ($b['postes'] ?? []))),
        'weeklyHours' => is_numeric($hours) ? max(0, min(60, (float) $hours)) : null,
        'phone' => mb_substr(trim((string) ($b['phone'] ?? '')), 0, 30),
        'email' => $email,
        'canBadgeFromPersonalDevice' => !empty($b['canBadgeFromPersonalDevice']),
        'forfaitJour' => !empty($b['forfaitJour']),
        'hygieneTrainingDone' => !empty($b['hygieneTrainingDone']),
        'hygieneTrainingDate' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($b['hygieneTrainingDate'] ?? '')) ? $b['hygieneTrainingDate'] : null,
    ]);
    unset($m['zone']); // ancien format v1, remplacé par permissions
    if ($pin !== '') { $m['pin'] = $pin; $m['failedPinAttempts'] = 0; $m['pinLockedUntil'] = null; }
    return $m;
}

Flight::route('GET /api/equipe', function () {
    $code = App::code();
    App::owner();
    $s = App::store();
    $rec = $s->get("restaurant:$code") ?: [];
    Flight::json([
        'members' => array_map(Team::public(...), Team::all($s, $code)),
        'settings' => ['pauseEnabled' => !empty(($s->get("config:$code:pointageSettings") ?: [])['pauseEnabled']), 'location' => $rec['location'] ?? null],
    ]);
});

Flight::route('POST /api/equipe', function () {
    $code = App::code();
    App::owner();
    $created = null;
    App::store()->mutate(Team::key($code), function ($team) use (&$created) {
        $created = team_member(App::body(), null, array_map(Team::normalize(...), $team));
        return [...$team, $created];
    }, []);
    Flight::json(['member' => Team::public($created)], 201);
});

Flight::route('PUT /api/equipe/@id', function (string $id) {
    $code = App::code();
    App::owner();
    $updated = null;
    App::store()->mutate(Team::key($code), function ($team) use ($id, &$updated) {
        $norm = array_map(Team::normalize(...), $team);
        foreach ($norm as $i => $m) if ($m['id'] === $id) $team[$i] = $updated = team_member(App::body(), $m, $norm);
        return $team;
    }, []);
    if (!$updated) App::fail(404, 'Personne introuvable.');
    Flight::json(['member' => Team::public($updated)]);
});

Flight::route('DELETE /api/equipe/@id', function (string $id) {
    $code = App::code();
    App::owner();
    // l'historique (relevés, pointages) garde le nom : rien n'est effacé des registres
    App::store()->mutate(Team::key($code), fn($team) => array_values(array_filter($team, fn($m) => ($m['id'] ?? null) !== $id)), []);
    Flight::json(['ok' => true]);
});

Flight::route('PUT /api/reglages/pointage', function () {
    $code = App::code();
    App::owner();
    $b = App::body();
    $s = App::store();
    $s->mutate("config:$code:pointageSettings", fn($v) => array_merge((array) $v, ['pauseEnabled' => !empty($b['pauseEnabled'])]), []);
    if (isset($b['location'])) {
        $loc = $b['location'];
        if ($loc !== null && (!is_numeric($loc['lat'] ?? null) || !is_numeric($loc['lng'] ?? null))) App::fail(422, 'Position invalide.');
        $s->mutate("restaurant:$code", function ($rec) use ($loc) {
            $rec = (array) $rec;
            $rec['location'] = $loc === null ? null : ['lat' => (float) $loc['lat'], 'lng' => (float) $loc['lng'], 'setAt' => Clock::iso()];
            return $rec;
        }, []);
    }
    Flight::json(['ok' => true]);
});
