<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/Validador.php';

class Encuesta
{
    const ENCUESTA_ID = 1;

    private $db;

    public function __construct()
    {
        $this->db = (new Conexion())->getConexion();
    }

    public function responder(array $datos): void
    {
        $preguntas = $this->preguntasPorClave();
        $sector = trim((string) ($datos['sector'] ?? ''));
        $calificacion = trim((string) ($datos['calificacion'] ?? ''));
        $comentario = trim((string) ($datos['comentario'] ?? ''));

        if (!array_key_exists($sector, $preguntas['sector']['opciones'])) {
            throw new InvalidArgumentException('Seleccioná el sector que visitó');
        }
        if (!array_key_exists($calificacion, $preguntas['calificacion']['opciones'])) {
            throw new InvalidArgumentException('Seleccioná una calificación');
        }
        if (!Validador::texto($comentario, 0, 500)) {
            throw new InvalidArgumentException('El comentario no puede superar los 500 caracteres');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('INSERT INTO RESPUESTA (texto, pregunta_id) VALUES (?, ?)');
            $stmt->execute([$sector, $preguntas['sector']['id']]);
            $stmt->execute([$calificacion, $preguntas['calificacion']['id']]);
            if ($comentario !== '') {
                $stmt->execute([$comentario, $preguntas['comentario']['id']]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function resultados(): array
    {
        $preguntas = $this->preguntasPorClave();

        $stmt = $this->db->prepare('SELECT clave, valor, cantidad FROM V_RESULTADOS_ENCUESTA WHERE encuesta_id = ?');
        $stmt->execute([self::ENCUESTA_ID]);
        $conteos = [];
        foreach ($stmt->fetchAll() as $fila) {
            $conteos[$fila['clave']][$fila['valor']] = (int) $fila['cantidad'];
        }

        $armar = function ($clave) use ($preguntas, $conteos) {
            $lista = [];
            foreach ($preguntas[$clave]['opciones'] as $valor => $etiqueta) {
                $lista[] = [
                    'valor'    => (string) $valor,
                    'etiqueta' => $etiqueta,
                    'cantidad' => $conteos[$clave][(string) $valor] ?? 0,
                ];
            }
            return $lista;
        };

        $calificaciones = $armar('calificacion');
        $total = 0;
        $suma = 0;
        foreach ($calificaciones as $c) {
            $total += $c['cantidad'];
            $suma += $c['cantidad'] * (int) $c['valor'];
        }

        $stmt = $this->db->prepare(
            'SELECT texto, fecha FROM V_COMENTARIOS_ENCUESTA WHERE encuesta_id = ? ORDER BY fecha DESC LIMIT 20'
        );
        $stmt->execute([self::ENCUESTA_ID]);

        return [
            'total'          => $total,
            'promedio'       => $total > 0 ? round($suma / $total, 2) : null,
            'calificaciones' => $calificaciones,
            'sectores'       => $armar('sector'),
            'comentarios'    => $stmt->fetchAll(),
        ];
    }

    private function preguntasPorClave(): array
    {
        $stmt = $this->db->prepare('SELECT identificacion, clave, opciones FROM PREGUNTA WHERE encuesta_id = ?');
        $stmt->execute([self::ENCUESTA_ID]);

        $resultado = [];
        foreach ($stmt->fetchAll() as $fila) {
            $opciones = [];
            if ($fila['opciones'] !== null && $fila['opciones'] !== '') {
                foreach (explode('|', $fila['opciones']) as $par) {
                    [$valor, $etiqueta] = array_pad(explode(':', $par, 2), 2, '');
                    $opciones[$valor] = $etiqueta;
                }
            }
            $resultado[$fila['clave']] = ['id' => (int) $fila['identificacion'], 'opciones' => $opciones];
        }

        foreach (['sector', 'calificacion', 'comentario'] as $clave) {
            if (!isset($resultado[$clave])) {
                throw new RuntimeException('La encuesta no está configurada en la base de datos');
            }
        }
        return $resultado;
    }
}
