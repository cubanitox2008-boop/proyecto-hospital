<?php
require_once __DIR__ . '/_base.php';
require_once __DIR__ . '/../clase/Documento.php';

const ROLES_ESCRITURA = ['admin', 'enfermero', 'funcionario'];

$usuario = Sesion::exigirLogin();
$accion = $_GET['accion'] ?? 'listar';

try {
    $documento = new Documento();

    switch ($accion) {
        case 'listar':
            Http::responder(200, ['documentos' => $documento->listar($_GET)]);

        case 'descargar':
            $id = $_GET['id'] ?? '';
            if (!Validador::entero($id)) {
                Http::error(400, 'Id inválido');
            }
            $datos = $documento->rutaArchivo((int) $id);
            $ruta = $datos ? RUTA_UPLOADS . '/' . basename($datos['archivo_ruta']) : null;
            if ($ruta === null || !is_file($ruta)) {
                Http::error(404, 'Archivo no encontrado');
            }
            $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
            $nombreSeguro = preg_replace('/[^A-Za-z0-9._-]/', '_', $datos['archivo_nombre']);
            header('Content-Type: ' . Documento::EXTENSIONES[$extension]);
            header('Content-Disposition: inline; filename="' . $nombreSeguro . '"');
            header('Content-Length: ' . filesize($ruta));
            readfile($ruta);
            exit;

        case 'crear':
            Http::exigirMetodo('POST');
            Sesion::exigirRol(ROLES_ESCRITURA);
            $id = $documento->crear($_POST, $_FILES['archivo'] ?? null, $usuario['identificacion']);
            Http::responder(201, ['mensaje' => 'Documento guardado', 'id' => $id]);

        case 'editar':
            Http::exigirMetodo('POST');
            Sesion::exigirRol(ROLES_ESCRITURA);
            $id = $_GET['id'] ?? '';
            if (!Validador::entero($id)) {
                Http::error(400, 'Id inválido');
            }
            $documento->editar((int) $id, $_POST, $_FILES['archivo'] ?? null);
            Http::responder(200, ['mensaje' => 'Documento actualizado']);

        case 'eliminar':
            Http::exigirMetodo('POST');
            Sesion::exigirRol(['admin']);
            $id = $_GET['id'] ?? '';
            if (!Validador::entero($id)) {
                Http::error(400, 'Id inválido');
            }
            if (!$documento->eliminar((int) $id)) {
                Http::error(404, 'El documento no existe');
            }
            Http::responder(200, ['mensaje' => 'Documento eliminado']);

        default:
            Http::error(404, 'Acción no encontrada');
    }
} catch (InvalidArgumentException $e) {
    Http::error(422, $e->getMessage());
} catch (Throwable $e) {
    error_log($e);
    Http::error(500, 'Error interno del servidor');
}
