<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/Validador.php';

class Documento
{
    const EXTENSIONES = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
    ];

    private $db;

    public function __construct()
    {
        $this->db = (new Conexion())->getConexion();
    }

    public function listar(array $filtros): array
    {
        $sql = 'SELECT * FROM V_DOCUMENTOS_DETALLE WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['categoria']) && Validador::entero($filtros['categoria'])) {
            $sql .= ' AND categoria_id = :categoria';
            $parametros[':categoria'] = (int) $filtros['categoria'];
        }
        if (!empty($filtros['q']) && is_string($filtros['q'])) {
            $texto = '%' . addcslashes(trim($filtros['q']), '%_\\') . '%';
            $sql .= ' AND (titulo LIKE :q1 OR paciente_nombre LIKE :q2)';
            $parametros[':q1'] = $texto;
            $parametros[':q2'] = $texto;
        }
        if (!empty($filtros['paciente']) && is_string($filtros['paciente'])) {
            $sql .= ' AND paciente_cedula = :paciente';
            $parametros[':paciente'] = trim($filtros['paciente']);
        }

        $sql .= ' ORDER BY fecha_subida DESC, identificacion DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM V_DOCUMENTOS_DETALLE WHERE identificacion = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos, ?array $archivo, int $idFuncionario): int
    {
        $limpios = $this->validarDatos($datos);
        if ($archivo === null || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('Seleccioná un archivo para subir');
        }
        [$nombreOriginal, $rutaInterna] = $this->guardarArchivo($archivo);

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO DOCUMENTO (titulo, descripcion, archivo_nombre, archivo_ruta, categoria_id, paciente_cedula, subido_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $limpios['titulo'], $limpios['descripcion'], $nombreOriginal, $rutaInterna,
                $limpios['categoria'], $limpios['paciente'], $idFuncionario,
            ]);
            return (int) $this->db->lastInsertId();
        } catch (Throwable $e) {
            @unlink(RUTA_UPLOADS . '/' . $rutaInterna);
            throw $e;
        }
    }

    public function editar(int $id, array $datos, ?array $archivo): void
    {
        $actual = $this->rutaArchivo($id);
        if ($actual === null) {
            throw new InvalidArgumentException('El documento no existe');
        }
        $limpios = $this->validarDatos($datos);

        $nuevoNombre = $actual['archivo_nombre'];
        $nuevaRuta = $actual['archivo_ruta'];
        $hayArchivoNuevo = $archivo !== null && $archivo['error'] !== UPLOAD_ERR_NO_FILE;
        if ($hayArchivoNuevo) {
            [$nuevoNombre, $nuevaRuta] = $this->guardarArchivo($archivo);
        }

        try {
            $stmt = $this->db->prepare(
                'UPDATE DOCUMENTO SET titulo = ?, descripcion = ?, categoria_id = ?, paciente_cedula = ?,
                        archivo_nombre = ?, archivo_ruta = ? WHERE identificacion = ?'
            );
            $stmt->execute([
                $limpios['titulo'], $limpios['descripcion'], $limpios['categoria'], $limpios['paciente'],
                $nuevoNombre, $nuevaRuta, $id,
            ]);
        } catch (Throwable $e) {
            if ($hayArchivoNuevo) {
                @unlink(RUTA_UPLOADS . '/' . $nuevaRuta);
            }
            throw $e;
        }

        if ($hayArchivoNuevo) {
            @unlink(RUTA_UPLOADS . '/' . basename($actual['archivo_ruta']));
        }
    }

    public function eliminar(int $id): bool
    {
        $actual = $this->rutaArchivo($id);
        if ($actual === null) {
            return false;
        }
        $this->db->prepare('DELETE FROM DOCUMENTO WHERE identificacion = ?')->execute([$id]);
        @unlink(RUTA_UPLOADS . '/' . basename($actual['archivo_ruta']));
        return true;
    }

    public function rutaArchivo(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT archivo_nombre, archivo_ruta FROM DOCUMENTO WHERE identificacion = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    private function validarDatos(array $datos): array
    {
        $titulo = trim((string) ($datos['titulo'] ?? ''));
        $descripcion = trim((string) ($datos['descripcion'] ?? ''));
        $categoria = $datos['categoria'] ?? '';
        $paciente = trim((string) ($datos['paciente'] ?? ''));

        if (!Validador::texto($titulo, 3, 150)) {
            throw new InvalidArgumentException('El título debe tener entre 3 y 150 caracteres');
        }
        if (!Validador::texto($descripcion, 0, 1000)) {
            throw new InvalidArgumentException('La descripción no puede superar los 1000 caracteres');
        }
        if (!Validador::entero($categoria)) {
            throw new InvalidArgumentException('Seleccioná una categoría');
        }

        $stmt = $this->db->prepare("SELECT 1 FROM CATEGORIA WHERE identificacion = ? AND ambito = 'documento'");
        $stmt->execute([(int) $categoria]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('La categoría no existe');
        }

        if ($paciente !== '') {
            if (!Validador::cedula($paciente)) {
                throw new InvalidArgumentException('La cédula del paciente debe tener 7 u 8 números');
            }
            $stmt = $this->db->prepare('SELECT 1 FROM PACIENTE WHERE cedula = ?');
            $stmt->execute([$paciente]);
            if (!$stmt->fetch()) {
                throw new InvalidArgumentException('No hay un paciente registrado con esa cédula (se registra al crear un traslado)');
            }
        }

        return [
            'titulo'      => $titulo,
            'descripcion' => $descripcion,
            'categoria'   => (int) $categoria,
            'paciente'    => $paciente !== '' ? $paciente : null,
        ];
    }

    private function guardarArchivo(array $archivo): array
    {
        if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
            throw new InvalidArgumentException('El archivo supera el tamaño máximo permitido (5 MB)');
        }
        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('No se pudo subir el archivo');
        }
        if ($archivo['size'] > MAX_TAMANO_ARCHIVO) {
            throw new InvalidArgumentException('El archivo supera el tamaño máximo permitido (5 MB)');
        }

        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        if (!isset(self::EXTENSIONES[$extension])) {
            throw new InvalidArgumentException('Solo se permiten archivos PDF, JPG o PNG');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        if ($mime !== self::EXTENSIONES[$extension]) {
            throw new InvalidArgumentException('El contenido del archivo no coincide con su extensión');
        }

        $nombreInterno = bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($archivo['tmp_name'], RUTA_UPLOADS . '/' . $nombreInterno)) {
            throw new RuntimeException('No se pudo guardar el archivo en el servidor');
        }

        return [mb_substr(basename($archivo['name']), 0, 200), $nombreInterno];
    }
}
