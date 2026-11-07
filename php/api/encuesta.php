<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../clase/Encuesta.php';

$accion = $_GET['accion'] ?? '';

try {
    $encuesta = new Encuesta();

    switch ($accion) {
        case 'responder':
            Http::exigirMetodo('POST');
            $encuesta->responder(Http::cuerpoJson());
            Http::responder(201, ['mensaje' => '¡Gracias por responder la encuesta!']);

        case 'resultados':
            Sesion::exigirRol(['admin']);
            Http::responder(200, $encuesta->resultados());

        default:
            Http::error(404, 'Acción no encontrada');
    }
} catch (InvalidArgumentException $e) {
    Http::error(422, $e->getMessage());
} catch (Throwable $e) {
    error_log($e);
    Http::error(500, 'Error interno del servidor');
}
