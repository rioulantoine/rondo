<?php
namespace Rondo;

/** Stockage clé → valeur JSON (Firestore en production, mémoire dans les tests). */
interface Store
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, array $extraFields = []): void;
    /** @param callable(mixed):mixed $fn reçoit la valeur actuelle, renvoie la nouvelle */
    public function mutate(string $key, callable $fn, mixed $default = null): mixed;
}

class StoreException extends \RuntimeException {}

class MemoryStore implements Store
{
    public array $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value, array $extraFields = []): void
    {
        $this->data[$key] = $value;
    }

    public function mutate(string $key, callable $fn, mixed $default = null): mixed
    {
        return $this->data[$key] = $fn($this->data[$key] ?? $default);
    }
}
