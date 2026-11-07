<?php
class Validador
{
    public static function cedula($valor): bool
    {
        return is_string($valor) && preg_match('/^\d{7,8}$/', $valor) === 1;
    }

    public static function nombre($valor): bool
    {
        return is_string($valor)
            && mb_strlen($valor) >= 2 && mb_strlen($valor) <= 100
            && preg_match('/^[\p{L}][\p{L} .\'-]*$/u', $valor) === 1;
    }

    public static function texto($valor, int $min, int $max): bool
    {
        return is_string($valor) && mb_strlen($valor) >= $min && mb_strlen($valor) <= $max;
    }

    public static function hora($valor): bool
    {
        return is_string($valor) && preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $valor) === 1;
    }

    public static function entero($valor): bool
    {
        return filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
    }

    public static function enLista($valor, array $lista): bool
    {
        return in_array($valor, $lista, true);
    }
}
