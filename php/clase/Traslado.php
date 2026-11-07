<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/Validador.php';

class Traslado
{
    const ESTADOS = ['Pendiente', 'En curso', 'En destino', 'Retorno', 'Finalizado'];

    private $db;

    public function __construct()
    {
        $this->db = (new Conexion())->getConexion();
    }

    public function listar(array $filtros, ?int $soloChofer = null): array
    {
        $sql = 'SELECT * FROM V_TRASLADOS_DETALLE WHERE 1 = 1';
        $parametros = [];

        if (!empty($filtros['paciente']) && is_string($filtros['paciente'])) {
            $texto = trim($filtros['paciente']);
            $sql .= ' AND (paciente_cedula = :cedula OR paciente_nombre LIKE :nombre)';
            $parametros[':cedula'] = $texto;
            $parametros[':nombre'] = '%' . addcslashes($texto, '%_\\') . '%';
        }
        if (!empty($filtros['vehiculo']) && is_string($filtros['vehiculo'])) {
            $sql .= ' AND vehiculo_matricula LIKE :matricula';
            $parametros[':matricula'] = '%' . addcslashes(trim($filtros['vehiculo']), '%_\\') . '%';
        }
        if (!empty($filtros['estado']) && Validador::enLista($filtros['estado'], self::ESTADOS)) {
            $sql .= ' AND estado = :estado';
            $parametros[':estado'] = $filtros['estado'];
        }
        if ($soloChofer !== null) {
            $sql .= ' AND chofer_id = :chofer';
            $parametros[':chofer'] = $soloChofer;
        }

        $sql .= ' ORDER BY fecha_creacion DESC, identificacion DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM V_TRASLADOS_DETALLE WHERE identificacion = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        $d = $this->validar($datos);

        $this->db->beginTransaction();
        try {
            $this->guardarPaciente($d);
            $stmt = $this->db->prepare(
                'INSERT INTO TRASLADO (estado, origen, destino, acompanante, hora_salida, hora_llegada,
                                       chofer, enfermero, paciente_cedula, vehiculo_matricula)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $d['estado'], $d['origen'], $d['destino'], $d['acompanante'], $d['hora_salida'],
                $d['hora_llegada'], $d['chofer'], $d['enfermero'], $d['paciente_cedula'], $d['vehiculo'],
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->db->commit();
            return $id;
        } catch (PDOException $e) {
            $this->db->rollBack();
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException('El vehículo, el chofer o el enfermero seleccionado no existe');
            }
            throw $e;
        }
    }

    public function editar(int $id, array $datos): bool
    {
        if ($this->obtener($id) === null) {
            return false;
        }
        $d = $this->validar($datos);

        $this->db->beginTransaction();
        try {
            $this->guardarPaciente($d);
            $stmt = $this->db->prepare(
                'UPDATE TRASLADO SET estado = ?, origen = ?, destino = ?, acompanante = ?, hora_salida = ?,
                        hora_llegada = ?, chofer = ?, enfermero = ?, paciente_cedula = ?, vehiculo_matricula = ?
                 WHERE identificacion = ?'
            );
            $stmt->execute([
                $d['estado'], $d['origen'], $d['destino'], $d['acompanante'], $d['hora_salida'],
                $d['hora_llegada'], $d['chofer'], $d['enfermero'], $d['paciente_cedula'], $d['vehiculo'], $id,
            ]);
            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            if ($e->getCode() === '23000') {
                throw new InvalidArgumentException('El vehículo, el chofer o el enfermero seleccionado no existe');
            }
            throw $e;
        }
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        if (!Validador::enLista($estado, self::ESTADOS)) {
            throw new InvalidArgumentException('Estado inválido');
        }
        $this->db->prepare('UPDATE TRASLADO SET estado = ? WHERE identificacion = ?')->execute([$estado, $id]);
    }

    public function eliminar(int $id): bool
    {
        $stmt = $this->db->prepare('DELETE FROM TRASLADO WHERE identificacion = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public function buscarPaciente(string $cedula): ?array
    {
        $stmt = $this->db->prepare('SELECT cedula, nombre, datos_paciente FROM PACIENTE WHERE cedula = ?');
        $stmt->execute([$cedula]);
        return $stmt->fetch() ?: null;
    }

    public function catalogoVehiculos(): array
    {
        return $this->db->query('SELECT matricula, tipo FROM VEHICULO ORDER BY tipo, matricula')->fetchAll();
    }

    public function catalogoChoferes(): array
    {
        return $this->db->query(
            'SELECT f.identificacion, f.nombre FROM CHOFER c
             JOIN FUNCIONARIO f ON f.identificacion = c.identificacion ORDER BY f.nombre'
        )->fetchAll();
    }

    public function catalogoEnfermeros(): array
    {
        return $this->db->query(
            'SELECT f.identificacion, f.nombre FROM ENFERMERO e
             JOIN FUNCIONARIO f ON f.identificacion = e.identificacion ORDER BY f.nombre'
        )->fetchAll();
    }

    private function guardarPaciente(array $d): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO PACIENTE (cedula, nombre, datos_paciente) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), datos_paciente = VALUES(datos_paciente)'
        );
        $stmt->execute([$d['paciente_cedula'], $d['paciente_nombre'], $d['paciente_datos']]);
    }

    private function validar(array $datos): array
    {
        $texto = function ($clave) use ($datos) {
            return trim((string) ($datos[$clave] ?? ''));
        };

        $vehiculo = $texto('vehiculo');
        $origen = $texto('origen');
        $destino = $texto('destino');
        $acompanante = $texto('acompanante');
        $cedula = $texto('paciente_cedula');
        $nombrePaciente = $texto('paciente_nombre');
        $datosPaciente = $texto('paciente_datos');
        $horaSalida = $texto('hora_salida');
        $horaLlegada = $texto('hora_llegada');
        $estado = $texto('estado') !== '' ? $texto('estado') : 'Pendiente';
        $chofer = $datos['chofer'] ?? '';
        $enfermero = $datos['enfermero'] ?? '';

        if (!Validador::texto($vehiculo, 3, 20)) {
            throw new InvalidArgumentException('Seleccioná un vehículo');
        }
        if (!Validador::entero($chofer)) {
            throw new InvalidArgumentException('Seleccioná un chofer');
        }
        if ($enfermero !== '' && $enfermero !== null && !Validador::entero($enfermero)) {
            throw new InvalidArgumentException('Enfermero inválido');
        }
        if (!Validador::cedula($cedula)) {
            throw new InvalidArgumentException('La cédula del paciente debe tener 7 u 8 números');
        }
        if (!Validador::nombre($nombrePaciente)) {
            throw new InvalidArgumentException('Ingresá un nombre de paciente válido (solo letras y espacios)');
        }
        if (!Validador::texto($datosPaciente, 0, 1000)) {
            throw new InvalidArgumentException('Los datos del paciente no pueden superar los 1000 caracteres');
        }
        if (!Validador::texto($origen, 3, 255) || !Validador::texto($destino, 3, 255)) {
            throw new InvalidArgumentException('El origen y el destino deben tener entre 3 y 255 caracteres');
        }
        if (!Validador::texto($acompanante, 0, 100)) {
            throw new InvalidArgumentException('El acompañante no puede superar los 100 caracteres');
        }
        if (!Validador::hora($horaSalida)) {
            throw new InvalidArgumentException('Ingresá una hora de salida válida');
        }
        if ($horaLlegada !== '' && !Validador::hora($horaLlegada)) {
            throw new InvalidArgumentException('La hora de llegada no es válida');
        }
        if ($horaLlegada !== '' && strcmp(substr($horaLlegada, 0, 5), substr($horaSalida, 0, 5)) <= 0) {
            throw new InvalidArgumentException('La hora de llegada debe ser posterior a la de salida');
        }
        if (!Validador::enLista($estado, self::ESTADOS)) {
            throw new InvalidArgumentException('Estado inválido');
        }

        return [
            'vehiculo'        => $vehiculo,
            'chofer'          => (int) $chofer,
            'enfermero'       => ($enfermero === '' || $enfermero === null) ? null : (int) $enfermero,
            'paciente_cedula' => $cedula,
            'paciente_nombre' => $nombrePaciente,
            'paciente_datos'  => $datosPaciente !== '' ? $datosPaciente : null,
            'origen'          => $origen,
            'destino'         => $destino,
            'acompanante'     => $acompanante !== '' ? $acompanante : null,
            'hora_salida'     => $horaSalida,
            'hora_llegada'    => $horaLlegada !== '' ? $horaLlegada : null,
            'estado'          => $estado,
        ];
    }
}
