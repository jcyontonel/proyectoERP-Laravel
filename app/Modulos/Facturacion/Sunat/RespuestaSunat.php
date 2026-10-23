<?php

namespace App\Modulos\Facturacion\Sunat;

/** Resultado de una llamada a SUNAT, ya clasificado */
final class RespuestaSunat
{
    public const ACEPTADO = 'aceptado';
    public const OBSERVADO = 'observado';
    public const RECHAZADO = 'rechazado';
    public const ERROR = 'error';                 // no se reintenta (credenciales, XML inválido…)
    public const SIN_CONEXION = 'sin_conexion';   // internet caído, timeout o SUNAT fuera de servicio: se reintenta
    public const TICKET = 'ticket';               // RA/RC recibido: hay que consultar el ticket
    public const EN_PROCESO = 'en_proceso';       // el ticket aún no termina (código 98)

    /** Excepciones de SUNAT que indican servicio no disponible (no un error del comprobante) */
    private const REINTENTABLES = [100, 109, 110, 125];

    public function __construct(
        public readonly string $resultado,
        public readonly ?string $codigo = null,
        public readonly ?string $descripcion = null,
        public readonly array $observaciones = [],
        public readonly ?string $cdrZip = null,
        public readonly ?string $ticket = null,
    ) {
    }

    public function seReintenta(): bool
    {
        return in_array($this->resultado, [self::SIN_CONEXION, self::EN_PROCESO], true);
    }

    /** Clasifica un código de CDR o de excepción SUNAT */
    public static function porCodigo(string $codigo, ?string $descripcion, array $observaciones = [], ?string $cdrZip = null): self
    {
        if (! ctype_digit($codigo)) {   // "HTTP", "WSDL"…: no hubo comunicación con SUNAT
            return new self(self::SIN_CONEXION, $codigo, $descripcion);
        }

        $n = (int) $codigo;
        $resultado = match (true) {
            $n === 0                                  => self::ACEPTADO,
            $n >= 4000                                => self::OBSERVADO,
            $n >= 2000                                => self::RECHAZADO,
            in_array($n, self::REINTENTABLES, true), ($n >= 130 && $n <= 138), ($n >= 200 && $n <= 252) => self::SIN_CONEXION,
            default                                   => self::ERROR,
        };

        return new self($resultado, $codigo, $descripcion, $observaciones, $cdrZip);
    }
}
