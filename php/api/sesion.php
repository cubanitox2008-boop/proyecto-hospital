<?php
require_once __DIR__ . '/_base.php';

$usuario = Sesion::usuario();

if ($usuario === null) {
    Http::responder(200, ['autenticado' => false]);
}

Http::responder(200, ['autenticado' => true, 'usuario' => $usuario]);
