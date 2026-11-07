<?php
require_once __DIR__ . '/conexion.php';

class Usuario
{
    const ROLES = ['admin', 'enfermero', 'chofer', 'funcionario'];

    private $db;

    public function __construct()
    {
        $this->db = (new Conexion())->getConexion();
    }

    public function registrar(string $nombre, string $ci, string $pass): bool
    {
        $stmt = $this->db->prepare('SELECT identificacion FROM FUNCIONARIO WHERE CI_funcionario = ?');
        $stmt->execute([$ci]);
        if ($stmt->fetch()) {
            return false;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO FUNCIONARIO (nombre, CI_funcionario, pass, tipo) VALUES (?, ?, ?, 'funcionario')"
        );
        try {
            return $stmt->execute([$nombre, $ci, $pass]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public function login(string $ci, string $pass): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT identificacion, nombre, CI_funcionario, pass, tipo FROM FUNCIONARIO WHERE CI_funcionario = ?'
        );
        $stmt->execute([$ci]);
        $fila = $stmt->fetch();

        if (!$fila || $pass !== $fila['pass']) {
            return null;
        }

        return [
            'identificacion' => (int) $fila['identificacion'],
            'CI_funcionario' => $fila['CI_funcionario'],
            'nombre'         => $fila['nombre'] !== '' ? $fila['nombre'] : $fila['CI_funcionario'],
            'rol'            => $fila['tipo'],
        ];
    }

    public function listar(): array
    {
        return $this->db->query(
            'SELECT identificacion, nombre, CI_funcionario, tipo FROM FUNCIONARIO ORDER BY nombre'
        )->fetchAll();
    }

    public function cambiarRol(int $id, string $tipo): ?string
    {
        if (!in_array($tipo, self::ROLES, true)) {
            return 'Rol inválido';
        }

        $stmt = $this->db->prepare('SELECT tipo FROM FUNCIONARIO WHERE identificacion = ?');
        $stmt->execute([$id]);
        $tipoActual = $stmt->fetchColumn();
        if ($tipoActual === false) {
            return 'El usuario no existe';
        }
        if ($tipoActual === $tipo) {
            return null;
        }
        if ($tipoActual === 'enfermero' && $this->tieneTrasladosComoEnfermero($id)) {
            return 'No se puede cambiar el rol: el enfermero tiene traslados asociados';
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM ENFERMERO WHERE identificacion = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM CHOFER WHERE identificacion = ?')->execute([$id]);

            if ($tipo === 'enfermero') {
                $this->db->prepare('INSERT INTO ENFERMERO (identificacion) VALUES (?)')->execute([$id]);
            } elseif ($tipo === 'chofer') {
                $this->db->prepare('INSERT INTO CHOFER (identificacion) VALUES (?)')->execute([$id]);
            }

            $this->db->prepare('UPDATE FUNCIONARIO SET tipo = ? WHERE identificacion = ?')->execute([$tipo, $id]);
            $this->db->commit();
            return null;
        } catch (PDOException $e) {
            $this->db->rollBack();
            if ($e->getCode() === '23000') {
                return 'No se puede cambiar el rol: el chofer tiene traslados asociados';
            }
            throw $e;
        }
    }

    public function eliminar(int $id): ?string
    {
        if ($this->tieneTrasladosComoEnfermero($id)) {
            return 'No se puede eliminar: el usuario tiene traslados asociados';
        }
        try {
            $stmt = $this->db->prepare('DELETE FROM FUNCIONARIO WHERE identificacion = ?');
            $stmt->execute([$id]);
            return $stmt->rowCount() > 0 ? null : 'El usuario no existe';
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                return 'No se puede eliminar: el usuario tiene traslados asociados';
            }
            throw $e;
        }
    }

    private function tieneTrasladosComoEnfermero(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM TRASLADO WHERE enfermero = ? LIMIT 1');
        $stmt->execute([$id]);
        return (bool) $stmt->fetch();
    }
}
