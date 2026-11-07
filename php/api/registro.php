<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../clase/Usuario.php';

Http::exigirMetodo('POST');

try {
    $datos = Http::cuerpoJson();
    $nombre = trim((string) ($datos['nombre'] ?? ''));
    $ci = trim((string) ($datos['ci'] ?? ''));
    $pass = (string) ($datos['pass'] ?? '');

    if (!Validador::nombre($nombre)) {
        Http::error(400, 'Ingresá un nombre válido (solo letras y espacios)');
    }
    if (!Validador::cedula($ci)) {
        Http::error(400, 'La cédula debe tener 7 u 8 números, sin puntos ni guiones');
    }
    if (strlen($pass) < 6 || strlen($pass) > 72) {
        Http::error(400, 'La contraseña debe tener entre 6 y 72 caracteres');
    }

    if (!(new Usuario())->registrar($nombre, $ci, $pass)) {
        Http::error(409, 'Ya existe un usuario con esa cédula');
    }

    Http::responder(201, ['mensaje' => 'Usuario creado correctamente']);
} catch (Throwable $e) {
    error_log($e);
    Http::error(500, 'Error interno del servidor');
}
