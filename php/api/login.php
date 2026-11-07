<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../clase/Usuario.php';

Http::exigirMetodo('POST');

try {
    $datos = Http::cuerpoJson();
    $ci = trim((string) ($datos['user'] ?? ''));
    $pass = (string) ($datos['pass'] ?? '');

    if ($ci === '' || $pass === '') {
        Http::error(400, 'Debe ingresar usuario y contraseña');
    }
    if (!Validador::cedula($ci)) {
        Http::error(400, 'La cédula debe tener 7 u 8 números, sin puntos ni guiones');
    }

    $resultado = (new Usuario())->login($ci, $pass);
    if ($resultado === null) {
        Http::error(401, 'Usuario o contraseña incorrectos');
    }

    session_regenerate_id(true);
    $_SESSION['usuario'] = $resultado;

    Http::responder(200, ['mensaje' => 'Login correcto', 'usuario' => $resultado]);
} catch (Throwable $e) {
    error_log($e);
    Http::error(500, 'Error interno del servidor');
}
