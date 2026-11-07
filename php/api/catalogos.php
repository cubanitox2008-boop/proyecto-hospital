<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../clase/Traslado.php';
require_once __DIR__ . '/../clase/conexion.php';

Sesion::exigirLogin();

try {
    $traslado = new Traslado();
    $db = (new Conexion())->getConexion();
    $categorias = $db->query(
        "SELECT identificacion, nombre FROM CATEGORIA WHERE ambito = 'documento' ORDER BY nombre"
    )->fetchAll();

    Http::responder(200, [
        'categorias' => $categorias,
        'vehiculos'  => $traslado->catalogoVehiculos(),
        'choferes'   => $traslado->catalogoChoferes(),
        'enfermeros' => $traslado->catalogoEnfermeros(),
        'estados'    => Traslado::ESTADOS,
    ]);
} catch (Throwable $e) {
    error_log($e);
    Http::error(500, 'Error interno del servidor');
}
