<?php
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/conexion.php';

class Sesion
{
    public static function iniciar(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    private static $verificado = false;

    public static function usuario(): ?array
    {
        if (!isset($_SESSION['usuario'])) {
            return null;
        }
        if (!self::$verificado) {
            self::$verificado = true;
            $stmt = (new Conexion())->getConexion()->prepare(
                'SELECT nombre, CI_funcionario, tipo FROM FUNCIONARIO WHERE identificacion = ?'
            );
            $stmt->execute([$_SESSION['usuario']['identificacion']]);
            $fila = $stmt->fetch();

            if (!$fila) {
                $_SESSION = [];
                return null;
            }
            $_SESSION['usuario']['rol'] = $fila['tipo'];
            $_SESSION['usuario']['nombre'] = $fila['nombre'] !== '' ? $fila['nombre'] : $fila['CI_funcionario'];
        }
        return $_SESSION['usuario'];
    }

    public static function exigirLogin(): array
    {
        $usuario = self::usuario();
        if ($usuario === null) {
            Http::error(401, 'Debe iniciar sesión');
        }
        return $usuario;
    }

    public static function exigirRol(array $rolesPermitidos): array
    {
        $usuario = self::exigirLogin();
        if (!in_array($usuario['rol'], $rolesPermitidos, true)) {
            Http::error(403, 'No tiene permisos para esta acción');
        }
        return $usuario;
    }
}
