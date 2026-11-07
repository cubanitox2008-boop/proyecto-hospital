<?php
class Http
{
    public static function cabeceras(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }

    public static function responder(int $codigo, array $datos): void
    {
        http_response_code($codigo);
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error(int $codigo, string $mensaje): void
    {
        self::responder($codigo, ['error' => $mensaje]);
    }

    public static function exigirMetodo(string $metodo): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
            self::error(405, 'Método no permitido');
        }
    }

    public static function cuerpoJson(): array
    {
        $datos = json_decode(file_get_contents('php://input'), true);
        return is_array($datos) ? $datos : [];
    }
}
