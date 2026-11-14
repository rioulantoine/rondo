<?php
namespace Rondo;

/**
 * Accès Firestore par l'API REST, avec le même modèle que la v1 :
 * collection `checkTaCuisineData`, un document par clé, champ `value` = JSON sérialisé.
 * Les requêtes portent le jeton Firebase de l'établissement : les règles Firestore de la v1 s'appliquent.
 */
class Firestore implements Store
{
    const COLLECTION = 'checkTaCuisineData';
    private string $base;

    /** @param callable():?string $token renvoie le jeton d'identité (null = anonyme) */
    public function __construct(private string $projectId, private $token, ?string $emulatorHost = null)
    {
        $root = $emulatorHost ? "http://$emulatorHost" : 'https://firestore.googleapis.com';
        $this->base = "$root/v1/projects/$projectId/databases/(default)/documents";
    }

    public static function docId(string $key): string
    {
        return str_replace('/', '~', $key);
    }

    private function docName(string $key): string
    {
        return "projects/{$this->projectId}/databases/(default)/documents/" . self::COLLECTION . '/' . self::docId($key);
    }

    private function url(string $key): string
    {
        return $this->base . '/' . self::COLLECTION . '/' . rawurlencode(self::docId($key));
    }

    private function request(string $method, string $url, ?array $body = null): array
    {
        $headers = ['Content-Type: application/json'];
        if ($t = ($this->token)()) $headers[] = "Authorization: Bearer $t";
        [$status, $data] = Http::send($method, $url, $headers, $body);
        return [$status, $data];
    }

    /** Renvoie [valeur décodée, updateTime] ; [null, null] si le document n'existe pas. */
    private function read(string $key): array
    {
        [$status, $data] = $this->request('GET', $this->url($key));
        if ($status === 404) return [null, null];
        if ($status !== 200) throw new StoreException($data['error']['message'] ?? "Lecture impossible ($status)", $status);
        $raw = $data['fields']['value']['stringValue'] ?? null;
        return [$raw === null ? null : json_decode($raw, true), $data['updateTime'] ?? null];
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->read($key)[0] ?? $default;
    }

    private function fields(mixed $value, array $extra): array
    {
        $f = [
            'value' => ['stringValue' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
            'updatedAt' => ['stringValue' => Clock::iso()],
        ];
        foreach ($extra as $k => $v) $f[$k] = $v === null ? ['nullValue' => null] : ['stringValue' => (string) $v];
        return $f;
    }

    public function set(string $key, mixed $value, array $extraFields = []): void
    {
        [$status, $data] = $this->commit($key, $this->fields($value, $extraFields), null);
        if ($status !== 200) throw new StoreException($data['error']['message'] ?? "Écriture impossible ($status)", $status);
    }

    /**
     * Lecture-modification-écriture protégée contre les écritures simultanées (équivalent du
     * runTransaction de la v1) : on réécrit seulement si le document n'a pas bougé, sinon on recommence.
     */
    public function mutate(string $key, callable $fn, mixed $default = null): mixed
    {
        for ($try = 0; $try < 6; $try++) {
            [$current, $updateTime] = $this->read($key);
            $next = $fn($current ?? $default);
            [$status, $data] = $this->commit($key, $this->fields($next, []), $updateTime ? ['updateTime' => $updateTime] : ['exists' => false]);
            if ($status === 200) return $next;
            if (!in_array($data['error']['status'] ?? '', ['FAILED_PRECONDITION', 'ALREADY_EXISTS', 'ABORTED'], true)) {
                throw new StoreException($data['error']['message'] ?? "Écriture impossible ($status)", $status);
            }
            usleep(50000 * ($try + 1));
        }
        throw new StoreException('Trop d\'écritures simultanées, réessaie.', 409);
    }

    /**
     * Écrit le document et, dans le même commit atomique, l'inscrit dans `keyIndex:{code}` comme la v1
     * (sauvegarde, export, suppression RGPD).
     */
    private function commit(string $key, array $fields, ?array $precondition): array
    {
        $write = ['update' => ['name' => $this->docName($key), 'fields' => $fields]];
        if ($precondition) $write['currentDocument'] = $precondition;
        $writes = [$write];
        if (preg_match('/^(config|log|reservation):([^:]+):/', $key, $m)) {
            $writes[] = [
                'update' => ['name' => $this->docName("keyIndex:{$m[2]}"), 'fields' => ['updatedAt' => ['stringValue' => Clock::iso()]]],
                'updateMask' => ['fieldPaths' => ['updatedAt']],
                'updateTransforms' => [['fieldPath' => 'keys', 'appendMissingElements' => ['values' => [['stringValue' => $key]]]]],
            ];
        }
        return $this->request('POST', $this->base . ':commit', ['writes' => $writes]);
    }
}
