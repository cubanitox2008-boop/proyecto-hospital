<?php
require_once __DIR__ . '/_base.php';

Http::exigirMetodo('POST');

$_SESSION = [];
session_destroy();

Http::responder(200, ['mensaje' => 'Sesión cerrada']);
