<?php
namespace Rondo;

/** Connexion Firebase Auth par l'API REST (même compte e-mail/mot de passe que la v1). */
class FirebaseAuth
{
    private string $base;
    private string $tokenUrl;

    public function __construct(private string $apiKey, ?string $emulatorHost = null)
    {
        $this->base = $emulatorHost ? "http://$emulatorHost/identitytoolkit.googleapis.com/v1" : 'https://identitytoolkit.googleapis.com/v1';
        $this->tokenUrl = $emulatorHost ? "http://$emulatorHost/securetoken.googleapis.com/v1/token" : 'https://securetoken.googleapis.com/v1/token';
    }

    /** @return array{idToken:string,refreshToken:string,exp:int,uid:string}|null null si identifiants refusés */
    public function signIn(string $email, string $password): ?array
    {
        [$status, $d] = Http::send('POST', "{$this->base}/accounts:signInWithPassword?key={$this->apiKey}",
            ['Content-Type: application/json'], ['email' => $email, 'password' => $password, 'returnSecureToken' => true]);
        if ($status !== 200) return null;
        return ['idToken' => $d['idToken'], 'refreshToken' => $d['refreshToken'], 'exp' => time() + (int) $d['expiresIn'] - 60, 'uid' => $d['localId']];
    }

    /** @return array{idToken:string,refreshToken:string,exp:int,uid:string}|null */
    public function signUp(string $email, string $password): ?array
    {
        [$status, $d] = Http::send('POST', "{$this->base}/accounts:signUp?key={$this->apiKey}",
            ['Content-Type: application/json'], ['email' => $email, 'password' => $password, 'returnSecureToken' => true]);
        if ($status !== 200) return null;
        return ['idToken' => $d['idToken'], 'refreshToken' => $d['refreshToken'], 'exp' => time() + (int) $d['expiresIn'] - 60, 'uid' => $d['localId']];
    }

    public function refresh(string $refreshToken): ?array
    {
        [$status, $d] = Http::send('POST', "{$this->tokenUrl}?key={$this->apiKey}",
            ['Content-Type: application/json'], ['grant_type' => 'refresh_token', 'refresh_token' => $refreshToken]);
        if ($status !== 200) return null;
        return ['idToken' => $d['id_token'], 'refreshToken' => $d['refresh_token'], 'exp' => time() + (int) $d['expires_in'] - 60, 'uid' => $d['user_id']];
    }

    public function sendPasswordReset(string $email): void
    {
        Http::send('POST', "{$this->base}/accounts:sendOobCode?key={$this->apiKey}",
            ['Content-Type: application/json'], ['requestType' => 'PASSWORD_RESET', 'email' => $email]);
    }
}
