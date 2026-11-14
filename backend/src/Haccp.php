<?php
namespace Rondo;

/** Registres d'hygiène HACCP. Un seul module pour cuisine, salle et bar (trois copies en v1). */
final class Haccp
{
    const PERIODS = ['Avant service du matin', 'Après le service du midi', 'Avant fermeture'];
    const TRACE_TYPES = ['dlc' => 'DLC produit ouvert', 'lot' => 'Numéro de lot', 'reception' => 'Réception fournisseur'];

    /** Valeurs par défaut reprises à l'identique des trois pages v1. */
    const SPACES = [
        'cuisine' => [
            'label' => 'Cuisine',
            'equipments' => [
                ['id' => 'frigo1', 'name' => 'Réfrigérateur 1', 'min' => 0, 'max' => 4],
                ['id' => 'frigo2', 'name' => 'Réfrigérateur 2', 'min' => 0, 'max' => 4],
                ['id' => 'vitrine', 'name' => 'Vitrine réfrigérée', 'min' => 0, 'max' => 4],
                ['id' => 'congelo', 'name' => 'Congélateur', 'min' => -25, 'max' => -18],
                ['id' => 'chambre', 'name' => 'Chambre froide', 'min' => 0, 'max' => 3],
                ['id' => 'reception', 'name' => 'Réception marchandises', 'min' => 0, 'max' => 4],
                ['id' => 'friture', 'name' => 'Huile de friture', 'min' => 0, 'max' => 180],
                ['id' => 'temoin', 'name' => 'Plat témoin', 'min' => 0, 'max' => 4],
            ],
            'tasks' => [
                ['id' => 't1', 'label' => 'Relever les températures des froids', 'moment' => self::PERIODS[0]],
                ['id' => 't2', 'label' => 'Contrôler les DLC des produits ouverts', 'moment' => self::PERIODS[0]],
                ['id' => 't3', 'label' => 'Vérifier la propreté des plans de travail', 'moment' => self::PERIODS[0]],
                ['id' => 't4', 'label' => 'Nettoyer les postes entre les services', 'moment' => self::PERIODS[1]],
                ['id' => 't5', 'label' => 'Relevé de température de fermeture', 'moment' => self::PERIODS[2]],
                ['id' => 't6', 'label' => 'Nettoyage complet cuisine et sols', 'moment' => self::PERIODS[2]],
                ['id' => 't7', 'label' => 'Sortir et trier les poubelles', 'moment' => self::PERIODS[2]],
                ['id' => 't8', 'label' => 'Vérifier la fermeture des chambres froides', 'moment' => self::PERIODS[2]],
            ],
        ],
        'salle' => [
            'label' => 'Salle',
            'equipments' => [
                ['id' => 'vitrine-dessert', 'name' => 'Vitrine réfrigérée desserts', 'min' => 0, 'max' => 4],
                ['id' => 'buffet-froid', 'name' => 'Buffet froid (service)', 'min' => 0, 'max' => 4],
                ['id' => 'buffet-chaud', 'name' => 'Maintien au chaud (buffet)', 'min' => 63, 'max' => 90],
                ['id' => 'frigo-salle', 'name' => 'Réfrigérateur salle', 'min' => 0, 'max' => 4],
                ['id' => 'temoin', 'name' => 'Plat témoin salle', 'min' => 0, 'max' => 4],
            ],
            'tasks' => [
                ['id' => 't1', 'label' => 'Dresser les tables et vérifier la propreté', 'moment' => self::PERIODS[0]],
                ['id' => 't2', 'label' => 'Contrôler les DLC des produits en vitrine', 'moment' => self::PERIODS[0]],
                ['id' => 't3', 'label' => "Vérifier l'affichage des allergènes au menu", 'moment' => self::PERIODS[0]],
                ['id' => 't4', 'label' => 'Nettoyer et désinfecter les tables entre chaque client', 'moment' => self::PERIODS[1]],
                ['id' => 't5', 'label' => 'Contrôler le réassort et la température du buffet', 'moment' => self::PERIODS[1]],
                ['id' => 't6', 'label' => 'Nettoyage complet de la salle et des sols', 'moment' => self::PERIODS[2]],
                ['id' => 't7', 'label' => 'Nettoyage des sanitaires clients', 'moment' => self::PERIODS[2]],
                ['id' => 't8', 'label' => 'Ranger et nettoyer le poste plonge', 'moment' => self::PERIODS[2]],
                ['id' => 't9', 'label' => 'Sortir et trier les poubelles', 'moment' => self::PERIODS[2]],
            ],
        ],
        'bar' => [
            'label' => 'Bar',
            'equipments' => [
                ['id' => 'frigo-bar', 'name' => 'Réfrigérateur bar', 'min' => 0, 'max' => 4],
                ['id' => 'chambre-bar', 'name' => 'Chambre froide bar', 'min' => 0, 'max' => 3],
                ['id' => 'vitrine-garnitures', 'name' => 'Vitrine fruits & garnitures', 'min' => 0, 'max' => 4],
                ['id' => 'congelo-bar', 'name' => 'Congélateur (glace pilée...)', 'min' => -25, 'max' => -18],
            ],
            'tasks' => [
                ['id' => 't1', 'label' => 'Relever les températures des froids du bar', 'moment' => self::PERIODS[0]],
                ['id' => 't2', 'label' => 'Contrôler les DLC des fruits et garnitures', 'moment' => self::PERIODS[0]],
                ['id' => 't3', 'label' => 'Vérifier la propreté du plan de travail bar', 'moment' => self::PERIODS[0]],
                ['id' => 't4', 'label' => 'Nettoyer le plan de travail entre chaque commande', 'moment' => self::PERIODS[1]],
                ['id' => 't5', 'label' => "Changer régulièrement l'eau du bac à glaçons", 'moment' => self::PERIODS[1]],
                ['id' => 't6', 'label' => 'Nettoyage complet du bar et du sol', 'moment' => self::PERIODS[2]],
                ['id' => 't7', 'label' => 'Nettoyage et désinfection de la tireuse à bière', 'moment' => self::PERIODS[2]],
                ['id' => 't8', 'label' => 'Vider et nettoyer le bac à glaçons', 'moment' => self::PERIODS[2]],
                ['id' => 't9', 'label' => 'Sortir et trier les poubelles', 'moment' => self::PERIODS[2]],
            ],
        ],
    ];

    public static function equipments(Store $s, string $code, string $sp): array
    {
        return $s->get("config:$code:$sp:equipments") ?? self::SPACES[$sp]['equipments'];
    }

    public static function tasks(Store $s, string $code, string $sp): array
    {
        return $s->get("config:$code:$sp:tasks") ?? self::SPACES[$sp]['tasks'];
    }

    public static function currentPeriod(): string
    {
        $h = (int) Clock::now()->format('G');
        return self::PERIODS[$h < 11 ? 0 : ($h < 16 ? 1 : 2)];
    }

    /** Jours restants avant la DLC (négatif = dépassée). */
    public static function daysLeft(string $date): int
    {
        return (int) (new \DateTimeImmutable(Clock::today()))->diff(new \DateTimeImmutable($date))->format('%r%a');
    }

    /** Alertes DLC J-3 à dépassée, entrées non retirées, la plus urgente d'abord. */
    public static function dlcAlerts(array $traces): array
    {
        $out = [];
        foreach ($traces as $e) {
            if (($e['type'] ?? '') !== 'dlc' || empty($e['date']) || !empty($e['removedAt'])) continue;
            $d = self::daysLeft($e['date']);
            if ($d <= 3) $out[] = ['entry' => $e, 'daysLeft' => $d];
        }
        usort($out, fn($a, $b) => $a['daysLeft'] <=> $b['daysLeft']);
        return $out;
    }

    /**
     * Construit un relevé de température au format v1. Hors bornes : action corrective obligatoire.
     * @return array entrée, ou ['error' => message]
     */
    public static function temperature(array $eq, mixed $value, string $comment, string $period, string $staff): array
    {
        if (!is_numeric($value)) return ['error' => 'Entre une température valide.'];
        $v = round((float) $value, 1);
        $min = min($eq['min'], $eq['max']);
        $max = max($eq['min'], $eq['max']);
        $ok = $v >= $min && $v <= $max;
        if (!$ok && trim($comment) === '') return ['error' => "{$eq['name']} à " . self::fmt($v) . " °C. Indique l'action corrective."];
        return [
            'id' => App::id(), 'ts' => Clock::iso(), 'equipmentId' => $eq['id'], 'equipmentName' => $eq['name'],
            'value' => $v, 'min' => $min, 'max' => $max, 'ok' => $ok, 'staff' => $staff, 'comment' => trim($comment),
            'period' => in_array($period, self::PERIODS, true) ? $period : self::currentPeriod(),
        ];
    }

    public static function fmt(float $v): string
    {
        return str_replace(['.', '-'], [',', '−'], (string) $v);
    }

    /** Résumé du jour d'un espace, utilisé par l'accueil et l'écran de l'espace. */
    public static function today(Store $s, string $code, string $sp): array
    {
        $tasks = self::tasks($s, $code, $sp);
        $log = $s->get("log:$code:$sp:tasks:" . Clock::today()) ?: [];
        $temps = array_values(array_filter($s->get("log:$code:$sp:temp:" . Clock::month()) ?: [], fn($e) => Clock::paris($e['ts'])->format('Y-m-d') === Clock::today()));
        // le mois précédent aussi : un produit ouvert le 30 peut périmer le 2
        $prev = Clock::now()->modify('first day of last month')->format('Y-m');
        $traces = [...($s->get("log:$code:$sp:trace:$prev") ?: []), ...($s->get("log:$code:$sp:trace:" . Clock::month()) ?: [])];
        $done = count(array_filter($tasks, fn($t) => !empty($log[$t['id']]['done'])));
        return [
            'space' => $sp, 'label' => self::SPACES[$sp]['label'],
            'tasksDone' => $done, 'tasksTotal' => count($tasks),
            'tempAlerts' => array_values(array_filter($temps, fn($e) => !$e['ok'])),
            'tempCount' => count($temps),
            'dlcAlerts' => self::dlcAlerts($traces),
        ];
    }
}
