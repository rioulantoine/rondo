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
$publish = [];
foreach ([0, 7] as $plus) $publish[\Rondo\Planning::weekStart(date('Y-m-d', strtotime("+$plus day")))] = ['publishedAt' => date('c'), 'publishedBy' => 'Responsable', 'snapshot' => []];
$fs->set("config:$code:planningPublish", $publish);
$fs->set("config:$code:alerts", [['id' => 'alert-1', 'message' => 'Contrôle sanitaire possible cette semaine : registres à jour avant chaque service.', 'target' => 'tous', 'active' => true, 'createdAt' => date('c'), 'createdBy' => 'Responsable']]);
// une journée de service plausible, pour les démonstrations et les captures
$today = date('Y-m-d');
$at = fn(string $hm) => (new DateTimeImmutable("$today $hm", new DateTimeZone('Europe/Paris')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
$temp = fn($id, $name, $v, $min, $max, $hm, $who, $c = '') => ['id' => "t-$id", 'ts' => $at($hm), 'equipmentId' => $id, 'equipmentName' => $name, 'value' => $v, 'min' => $min, 'max' => $max, 'ok' => $v >= $min && $v <= $max, 'staff' => $who, 'comment' => $c, 'period' => 'Avant service du matin'];
$fs->set("log:$code:cuisine:temp:$m", [
    $temp('frigo1', 'Réfrigérateur 1', 2.8, 0, 4, '08:12', 'Léa Martin'),
    $temp('frigo2', 'Réfrigérateur 2', 3.4, 0, 4, '08:14', 'Léa Martin'),
    $temp('chambre', 'Chambre froide', 1.9, 0, 3, '08:17', 'Hugo Lambert'),
    $temp('congelo', 'Congélateur', -12.0, -25, -18, '08:20', 'Hugo Lambert', 'Porte mal fermée, nouveau relevé dans 30 min'),
    $temp('vitrine', 'Vitrine réfrigérée', 3.1, 0, 4, '08:26', 'Léa Martin'),
]);
$done = fn($hm, $who) => ['done' => true, 'ts' => $at($hm), 'staff' => $who];
$fs->set("log:$code:cuisine:tasks:$today", ['t1' => $done('08:30', 'Léa Martin'), 't2' => $done('08:41', 'Hugo Lambert'), 't3' => $done('08:55', 'Léa Martin')]);
$fs->set("log:$code:salle:tasks:$today", ['t1' => $done('11:20', 'Léa Martin'), 't2' => $done('11:32', 'Léa Martin')]);
$fs->set("log:$code:cuisine:trace:$m", [
    ['id' => 'tr-1', 'ts' => $at('08:45'), 'type' => 'dlc', 'product' => 'Crème liquide 35 %', 'date' => date('Y-m-d', strtotime('+1 day')), 'lot' => '24-118', 'supplier' => '', 'temp' => null, 'staff' => 'Léa Martin', 'period' => 'Avant service du matin'],
    ['id' => 'tr-2', 'ts' => $at('09:05'), 'type' => 'reception', 'product' => 'Saumon frais', 'date' => '', 'lot' => 'FR-889', 'supplier' => 'Marée Atlantique', 'temp' => 2.1, 'staff' => 'Hugo Lambert', 'period' => 'Avant service du matin'],
]);
$fs->set("log:$code:pointage:$m", [
    ['id' => 'pt-1', 'memberId' => 'm-lea', 'name' => 'Léa Martin', 'type' => 'arrivee', 'ts' => $at('08:58'), 'plannedStart' => '09:00', 'diffMin' => -2],
    ['id' => 'pt-2', 'memberId' => 'm-hugo', 'name' => 'Hugo Lambert', 'type' => 'arrivee', 'ts' => $at('08:05'), 'offSchedule' => true, 'justification' => 'Livraison à réceptionner', 'validated' => false],
]);
$resa = fn($id, $slot, $party, $first, $last, $status, $notes = '') => ['id' => "r-$id", 'tableIds' => [], 'date' => $today, 'slot' => $slot, 'party' => $party, 'firstName' => $first, 'lastName' => $last, 'name' => "$first $last", 'phone' => '06 12 34 56 78', 'email' => 'client@example.com', 'notes' => $notes, 'status' => $status, 'createdAt' => 0, 'staffSeen' => true];
$resas = [$resa(1, '12:15', 2, 'Claire', 'Morel', 'arrivee'), $resa(2, '12:30', 4, 'Paul', 'Guérin', 'confirmee', 'Anniversaire'), $resa(3, '13:00', 3, 'Nina', 'Faure', 'noshow'), $resa(4, '19:30', 6, 'Marc', 'Durand', 'confirmee', 'Allergie aux fruits à coque')];
$fs->set("reservation:$code:reservations", $resas);
$fs->set("reservation:$code:availability", array_map(fn($r) => ['id' => $r['id'], 'tableIds' => [], 'date' => $r['date'], 'slot' => $r['slot'], 'party' => $r['party'], 'status' => $r['status']], $resas));
echo "Établissement « sumo-beach » / mot de passe « rondo123 » — PIN Léa 1234, Tom 2345, Hugo 3456\n";
