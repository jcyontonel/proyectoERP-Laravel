<?php

namespace App\Modulos\Erp\Services;

use App\Modulos\Erp\Models\Cliente;
use App\Modulos\Nucleo\Models\TipoDocumento;
use App\Modulos\Erp\Models\Venta;
use App\Modulos\Nucleo\Services\Documentos\ApiPeruService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Cada empresa tiene sus propios clientes: tipo + número de documento es único DENTRO de la empresa.
 * Nunca se leen ni se copian clientes de otra empresa; un documento nuevo se completa con RENIEC/SUNAT.
 */
class ClienteService
{
    public const ORIGEN_EMPRESA = 'empresa';   // ya era cliente de la empresa
    public const ORIGEN_EXTERNO = 'externo';   // no existía: se registró con RENIEC/SUNAT

    public function __construct(private ApiPeruService $apiPeru)
    {
    }

    /**
     * Busca un DNI (8) o RUC (11) en la empresa y, si no es cliente suyo, lo registra con RENIEC/SUNAT.
     *
     * @return array{0: Cliente, 1: string}  [cliente, origen]
     */
    public function obtenerPorDocumento(string $numero, int $empresaId): array
    {
        $numero = $this->limpiar($numero);
        $tipoId = $this->tipoDocumentoId($numero);

        $existente = $this->delDocumento($empresaId, $tipoId, $numero)->withTrashed()->first();

        if ($existente) {
            if ($existente->trashed()) {
                $existente->restore();
            }

            return [$existente, self::ORIGEN_EMPRESA];
        }

        $datos = $this->consultarExterno($numero);

        $cliente = $this->registrar([
            'tipo_documento_id' => $tipoId,
            'numero_documento'  => $numero,
            'nombre'            => $datos['nombre'],
            'apellido'          => $datos['apellido'],
            'direccion'         => $datos['direccion'],
            'activo'            => true,
        ], $empresaId);

        return [$cliente, self::ORIGEN_EXTERNO];
    }

    /** Datos para autocompletar el formulario de cliente (sin registrar nada) */
    public function consultarDocumento(string $numero, int $empresaId): array
    {
        $numero = $this->limpiar($numero);
        $tipoId = $this->tipoDocumentoId($numero);

        $existente = $this->delDocumento($empresaId, $tipoId, $numero)->first();
        if ($existente) {
            return ['tipo_documento_id' => $tipoId, 'numero' => $numero, 'origen' => self::ORIGEN_EMPRESA, 'id' => $existente->id]
                + $existente->only('nombre', 'apellido', 'direccion');
        }

        return ['tipo_documento_id' => $tipoId, 'origen' => self::ORIGEN_EXTERNO] + $this->consultarExterno($numero);
    }

    /** Cliente de la empresa con ese documento */
    private function delDocumento(int $empresaId, int $tipoId, string $numero)
    {
        return Cliente::query()->deEmpresa($empresaId)->where('tipo_documento_id', $tipoId)->where('numero_documento', $numero);
    }

    private function consultarExterno(string $numero): array
    {
        try {
            $datos = $this->apiPeru->consultar($numero);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['numero_documento' => $e->getMessage()]);
        }

        if (! $datos || blank($datos['nombre'])) {
            $fuente = strlen($numero) === 8 ? 'RENIEC' : 'SUNAT';
            throw ValidationException::withMessages(['numero_documento' => "No se encontró el documento {$numero} en {$fuente}. Regístralo manualmente."]);
        }

        return $datos;
    }

    private function limpiar(string $numero): string
    {
        $numero = preg_replace('/\D/', '', $numero);

        if (! in_array(strlen($numero), [8, 11], true)) {
            throw ValidationException::withMessages(['numero_documento' => 'Ingresa un DNI de 8 dígitos o un RUC de 11.']);
        }

        return $numero;
    }

    /** Catálogo SUNAT 06: 8 dígitos = DNI (1), 11 = RUC (6) */
    private function tipoDocumentoId(string $numero): int
    {
        return (int) TipoDocumento::where('codigo', strlen($numero) === 8 ? '1' : '6')->value('id');
    }

    /** Registra el cliente en la empresa (si estaba eliminado, lo recupera con los datos nuevos) */
    public function registrar(array $d, int $empresaId): Cliente
    {
        return DB::transaction(function () use ($d, $empresaId) {
            $existente = $this->delDocumento($empresaId, (int) $d['tipo_documento_id'], $d['numero_documento'])
                ->withTrashed()->lockForUpdate()->first();

            if ($existente && ! $existente->trashed()) {
                throw ValidationException::withMessages(['numero_documento' => 'Este cliente ya está registrado en tu empresa.']);
            }

            if ($existente) {
                $existente->restore();
                $existente->update($this->datos($d));

                return $existente;
            }

            return Cliente::create(['empresa_id' => $empresaId] + $this->datos($d));
        });
    }

    public function actualizar(Cliente $cliente, array $d): Cliente
    {
        $duplicado = $this->delDocumento($cliente->empresa_id, (int) $d['tipo_documento_id'], $d['numero_documento'])
            ->withTrashed()->whereKeyNot($cliente->id)->exists();

        if ($duplicado) {
            throw ValidationException::withMessages(['numero_documento' => 'Ya existe otro cliente con ese documento.']);
        }

        $cliente->update($this->datos($d));

        return $cliente;
    }

    /** Solo si nunca tuvo ventas (si las tiene, se desactiva) */
    public function eliminar(Cliente $cliente): void
    {
        if (Venta::query()->where('cliente_id', $cliente->id)->exists()) {
            throw ValidationException::withMessages([
                'cliente' => "«{$cliente->nombre_completo}» tiene ventas registradas: no se puede eliminar. Desactívalo desde Editar.",
            ]);
        }

        $cliente->delete();
    }

    private function datos(array $d): array
    {
        return [
            'tipo_documento_id' => $d['tipo_documento_id'],
            'numero_documento'  => $d['numero_documento'],
            'nombre'            => trim($d['nombre']),
            'apellido'          => filled($d['apellido'] ?? null) ? trim($d['apellido']) : null,
            'correo'            => $d['correo'] ?? null,
            'telefono'          => $d['telefono'] ?? null,
            'direccion'         => $d['direccion'] ?? null,
            'activo'            => (bool) ($d['activo'] ?? true),
        ];
    }
}