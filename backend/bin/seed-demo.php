<?php
// Remplit les émulateurs Firebase avec un établissement de démonstration. Refuse de tourner sur la vraie base.
require __DIR__ . '/../vendor/autoload.php';
use Rondo\{App, Firestore, FirebaseAuth};

if (!getenv('FIRESTORE_EMULATOR_HOST') || !getenv('FIREBASE_AUTH_EMULATOR_HOST')) {
    fwrite(STDERR, "Démarre d'abord les émulateurs (npm run emulators à la racine).\n");
    exit(1);
}
$code = 'sumo-beach';
$auth = new FirebaseAuth('demo-key', getenv('FIREBASE_AUTH_EMULATOR_HOST'));
$a = $auth->signIn("$code@resto.checktonresto.local", 'rondo123') ?? $auth->signUp("$code@resto.checktonresto.local", 'rondo123');
$fs = new Firestore(App::projectId(), fn() => 'owner', getenv('FIRESTORE_EMULATOR_HOST'));
$fs->set("restaurant:$code", ['name' => 'Sumo Beach', 'ownerUid' => $a['uid'], 'plan' => 'trial'], ['ownerUid' => $a['uid']]);
$fs->set("config:$code:team", [
    ['id' => 'm-lea', 'name' => 'Léa Martin', 'pin' => '1234', 'permissions' => ['cuisine', 'salle', 'planning'], 'postes' => ['cuisine', 'salle'], 'weeklyHours' => 35, 'canBadgeFromPersonalDevice' => true],
    ['id' => 'm-tom', 'name' => 'Tom Riou', 'pin' => '2345', 'permissions' => ['bar', 'salle'], 'postes' => ['bar'], 'weeklyHours' => 30],
    ['id' => 'm-hugo', 'name' => 'Hugo Lambert', 'pin' => '3456', 'permissions' => ['cuisine'], 'postes' => ['cuisine'], 'weeklyHours' => 39],
]);
$fs->set("config:$code:pointageSettings", ['pauseEnabled' => true]);
$m = date('Y-m');
$shifts = [];
foreach (range(0, 6) as $i) {
    $d = date('Y-m-d', strtotime("+$i day"));
    $shifts[] = ['id' => "s-lea-$i", 'staff' => 'Léa Martin', 'date' => $d, 'start' => '09:00', 'end' => '15:00', 'space' => 'cuisine'];
    $shifts[] = ['id' => "s-tom-$i", 'staff' => 'Tom Riou', 'date' => $d, 'start' => '18:00', 'end' => '01:00', 'space' => 'bar'];
}
$fs->set("log:$code:planning:$m", $shifts);
echo "Établissement « sumo-beach » / mot de passe « rondo123 » — PIN Léa 1234, Tom 2345, Hugo 3456\n";
