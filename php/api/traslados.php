<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../clase/Traslado.php';

const ROLES_GESTION = ['admin', 'enfermero'];

$usuario = Sesion::exigirLogin();
$accion = $_GET['accion'] ?? 'listar';

try {
    $traslado = new Traslado();

    switch ($accion) {
        case 'listar':
            $soloChofer = $usuario['rol'] === 'chofer' ? $usuario['identificacion'] : null;
            Http::responder(200, ['traslados' => $traslado->listar($_GET, $soloChofer)]);

        case 'paciente':
            Sesion::exigirRol(ROLES_GESTION);
            $cedula = trim((string) ($_GET['cedula'] ?? ''));
            if (!Validador::cedula($cedula)) {
                Http::error(400, 'Cédula inválida');
            }
            Http::responder(200, ['paciente' => $traslado->buscarPaciente($cedula)]);

        case 'crear':
            Http::exigirMetodo('POST');
            Sesion::exigirRol(ROLES_GESTION);
            $id = $traslado->crear(Http::cuerpoJson());
            Http::responder(201, ['mensaje' => 'Traslado registrado', 'id' => $id]);

        case 'editar':
            Http::exigirMetodo('POST');
            Sesion::exigirRol(ROLES_GESTION);
            $id = $_GET['id'] ?? '';
            if (!Validador::entero($id)) {
                Http::error(400, 'Id inválido');
            }
            if (!$traslado->editar((int) $id, Http::cuerpoJson())) {
                Http::error(404, 'El traslado no existe');
            }
            Http::responder(200, ['mensaje' => 'Traslado actualizado']);

        case 'estado':
            Http::exigirMetodo('POST');
            Sesion::exigirRol(['admin', 'enfermero', 'chofer']);
            $id = $_GET['id'] ?? '';
            if (!Validador::entero($id)) {
                Http::error(400, 'Id inválido');
            }
            $actual = $traslado->obtener((int) $id);
            if ($actual === null) {
                Http::error(404, 'El traslado no existe');
            }
            if ($usuario['rol'] === 'chofer' && (int) $actual['chofer_id'] !== $usuario['identificacion']) {
                Http::error(403, 'No tiene permisos para esta acción');
            }
            $datos = Http::cuerpoJson();
            $traslado->cambiarEstado((int) $id, (string) ($datos['estado'] ?? ''));
            Http::responder(200, ['mensaje' => 'Estado actualizado']);

        case 'eliminar':
            Http::exigirMetodo('POST');
            Sesion::exigirRol(['admin']);
            $id = $_GET['id'] ?? '';
            if (!Validador::entero($id)) {
                Http::error(400, 'Id inválido');
            }
            if (!$traslado->eliminar((int) $id)) {
                Http::error(404, 'El traslado no existe');
            }
            Http::responder(200, ['mensaje' => 'Traslado eliminado']);

        default:
            Http::error(404, 'Acción no encontrada');
    }
} catch (InvalidArgumentException $e) {
    Http::error(422, $e->getMessage());
} catch (Throwable $e) {
    error_log($e);
    Http::error(500, 'Error interno del servidor');
}
