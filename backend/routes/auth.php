<?php
use Rondo\{App, Clock, Team};

// Connexion en deux temps, comme la v1 : l'établissement (code + mot de passe), puis la personne (PIN).

Flight::route('GET /api/session', function () {
    Flight::json([
        'restaurant' => isset($_SESSION['code']) ? ['code' => $_SESSION['code'], 'name' => $_SESSION['name']] : null,
        'person' => $_SESSION['person'] ?? null,
    ]);
});

Flight::route('POST /api/login', function () {
    $b = App::body();
    $code = Team::slugify((string) ($b['code'] ?? ''));
    $pwd = (string) ($b['password'] ?? '');
    if (!$code || $pwd === '') App::fail(422, 'Renseigne le code et le mot de passe.');
    $rec = App::anon()->get("restaurant:$code");
    $auth = $rec ? App::auth()->signIn(Team::authEmail($code, $rec), $pwd) : null;
    // shortcut: les comptes v1 jamais migrés vers Firebase Auth (mot de passe en clair) doivent se connecter une fois sur la v1
    if (!$auth) App::fail(401, 'Code ou mot de passe incorrect.');
    session_regenerate_id(true);
    $_SESSION = ['code' => $code, 'name' => $rec['name'] ?? $code, 'auth' => $auth];
    if (!empty($b['remember'])) {
        setcookie(session_name(), session_id(), ['expires' => time() + 30 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
    Flight::json(['restaurant' => ['code' => $code, 'name' => $_SESSION['name']], 'person' => null]);
});

Flight::route('POST /api/restaurants', function () {
    $b = App::body();
    $name = trim((string) ($b['name'] ?? ''));
    $code = Team::slugify((string) ($b['code'] ?? ''));
    $email = trim((string) ($b['email'] ?? ''));
    $pwd = (string) ($b['password'] ?? '');
    if (!$name || !$code || !$email || !$pwd) App::fail(422, 'Complète tous les champs.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) App::fail(422, 'Adresse e-mail invalide.');
    if (strlen($pwd) < 6) App::fail(422, 'Le mot de passe doit faire au moins 6 caractères.');
    if (empty($b['cgu'])) App::fail(422, "Accepte les conditions générales pour continuer.");
    if (App::anon()->get("restaurant:$code")) App::fail(409, 'Ce code est déjà pris, choisis-en un autre.');
    $auth = App::auth()->signUp($email, $pwd);
    if (!$auth) App::fail(409, 'Cet e-mail est déjà utilisé pour un autre établissement.');
    $_SESSION = ['code' => $code, 'name' => $name, 'auth' => $auth];
    $now = Clock::now();
    App::store()->set("restaurant:$code", [
        'name' => $name, 'ownerUid' => $auth['uid'], 'recoveryEmail' => $email, 'cguAcceptedAt' => Clock::iso(),
        'plan' => 'trial', 'subscriptionStatus' => 'trialing', 'trialStartedAt' => Clock::iso(),
        'trialEndsAt' => Clock::iso($now->modify('+14 days')), 'stripeCustomerId' => null,
    ], ['ownerUid' => $auth['uid']]);
    $_SESSION['person'] = Team::owner();
    Flight::json(['restaurant' => ['code' => $code, 'name' => $name], 'person' => $_SESSION['person']], 201);
});

Flight::route('POST /api/password-reset', function () {
    $code = Team::slugify((string) (App::body()['code'] ?? ''));
    $rec = $code ? App::anon()->get("restaurant:$code") : null;
    if ($rec && !empty($rec['recoveryEmail'])) App::auth()->sendPasswordReset($rec['recoveryEmail']);
    // même réponse dans tous les cas : on ne révèle pas quels codes existent
    Flight::json(['ok' => true]);
});

Flight::route('GET /api/identities', function () {
    $code = App::code();
    Flight::json(array_map(fn($m) => ['id' => $m['id'], 'name' => $m['name'], 'postes' => $m['postes']], Team::all(App::store(), $code)));
});

Flight::route('POST /api/identity', function () {
    $code = App::code();
    $b = App::body();
    if (!empty($b['owner'])) {
        $rec = App::store()->get("restaurant:$code");
        if (!App::auth()->signIn(Team::authEmail($code, $rec), (string) ($b['password'] ?? ''))) App::fail(401, 'Mot de passe incorrect.');
        $_SESSION['person'] = Team::owner();
    } else {
        $pin = (string) ($b['pin'] ?? '');
        if (!preg_match('/^\d{4}$/', $pin)) App::fail(422, 'Le code PIN fait 4 chiffres.');
        $r = Team::verifyPin(App::store(), $code, (string) ($b['memberId'] ?? ''), $pin);
        if (!empty($r['locked'])) App::fail(423, 'Trop d\'essais. Réessaie à ' . (new DateTimeImmutable('@' . intdiv($r['retryAt'], 1000)))->setTimezone(new DateTimeZone('Europe/Paris'))->format('H:i') . '.');
        if (!$r['ok']) App::fail(401, isset($r['attemptsLeft']) ? "Code incorrect. Encore {$r['attemptsLeft']} essai(s)." : 'Personne introuvable.');
        $_SESSION['person'] = Team::person($r['member']);
    }
    Flight::json(['person' => $_SESSION['person']]);
});

Flight::route('DELETE /api/identity', function () {
    unset($_SESSION['person']);
    Flight::json(['ok' => true]);
});

Flight::route('POST /api/logout', function () {
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', ['expires' => 1, 'path' => '/']);
    Flight::json(['ok' => true]);
});
