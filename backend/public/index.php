<?php
require __DIR__ . '/../vendor/autoload.php';

Flight::route('GET /api/hello', function () {
    Flight::json(['message' => 'Hello depuis Flight']);
});

Flight::start();
