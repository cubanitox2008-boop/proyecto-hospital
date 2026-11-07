<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../clase/Usuario.php';

$admin = Sesion::exigirRol(['admin']);
$accion = $_GET['accion'] ?? 'listar';

try {
    $usuarios = new Usuario();

    switch ($accion) {
        case 'listar':
            Http::responder(200, ['usuarios' => $usuarios->listar(), 'roles' => Usuario::ROLES]);

        case 'rol':
            Http::exigirMetodo('POST');
            $id = $_GET['id'] ?? '';
            if (!Validador::entero($id)) {
                Http::error(400, 'Id inválido');
            }
            if ((int) $id === $admin['identificacion']) {
                Http::error(422, 'No podés cambiar tu propio rol');
            }
            $datos = Http::cuerpoJson();
            $error = $usuarios->cambiarRol((int) $id, (string) ($datos['tipo'] ?? ''));
            if ($error !== null) {
                Http::error(422, $error);
            }
            Http::responder(200, ['mensaje' => 'Rol actualizado']);

        case 'eliminar':
            Http::exigirMetodo('POST');
            $id = $_GET['id'] ?? '';
            if (!Validador::entero($id)) {
                Http::error(400, 'Id inválido');
            }
            if ((int) $id === $admin['identificacion']) {
                Http::error(422, 'No podés eliminar tu propio usuario');
            }
            $error = $usuarios->eliminar((int) $id);
            if ($error !== null) {
                Http::error(422, $error);
            }
            Http::responder(200, ['mensaje' => 'Usuario eliminado']);

        default:
            Http::error(404, 'Acción no encontrada');
    }
} catch (Throwable $e) {
    error_log($e);
    Http::error(500, 'Error interno del servidor');
}
