<?php
require __DIR__ . '/../vendor/autoload.php';

use Rondo\StoreException;

date_default_timezone_set('Europe/Paris');
session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

Flight::map('error', function (Throwable $e) {
    $status = $e instanceof StoreException && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
    if ($status === 500) error_log((string) $e);
    Flight::json(['error' => $status === 500 ? 'Erreur du serveur, réessaie.' : $e->getMessage()], $status);
});

foreach (glob(__DIR__ . '/../routes/*.php') as $routes) require $routes;

Flight::start();
