<?php
namespace Rondo;

final class Http
{
    /** @return array{0:int,1:array} statut HTTP et corps JSON décodé */
    public static function send(string $method, string $url, array $headers = [], ?array $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $raw = curl_exec($ch);
        if ($raw === false) throw new StoreException('Réseau indisponible : ' . curl_error($ch), 503);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        return [$status, json_decode($raw, true) ?? []];
    }
}

final class Clock
{
    /** Heure de référence. Surchargée dans les tests. Le pointage utilise l'heure du serveur, jamais celle de l'appareil. */
    public static ?\DateTimeImmutable $frozen = null;

    public static function now(): \DateTimeImmutable
    {
        return self::$frozen ?? new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
    }

    /** Format ISO UTC identique au toISOString() de la v1. */
    public static function iso(?\DateTimeImmutable $d = null): string
    {
        return ($d ?? self::now())->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z');
    }

    public static function today(): string
    {
        return self::now()->format('Y-m-d');
    }

    public static function month(): string
    {
        return self::now()->format('Y-m');
    }

    /** Convertit un horodatage ISO stocké en date/heure de Paris. */
    public static function paris(string $iso): \DateTimeImmutable
    {
        return (new \DateTimeImmutable($iso))->setTimezone(new \DateTimeZone('Europe/Paris'));
    }
}
