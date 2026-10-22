<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\TipoDocumento;
use App\Models\Venta;
use App\Services\Documentos\ApiPeruService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * clientes.numero_documento es ÚNICO en toda la base (tipo + número), y cliente_empresa los vincula a cada
 * empresa. Por eso registrar un documento que ya existe en otra empresa solo lo VINCULA a la tuya.
 */
class ClienteService
{
    public const ORIGEN_EMPRESA = 'empresa';   // ya era cliente de la empresa
    public const ORIGEN_SISTEMA = 'sistema';   // existía en otra empresa: se vinculó
    public const ORIGEN_EXTERNO = 'externo';   // no existía: se registró con RENIEC/SUNAT

    public function __construct(private ApiPeruService $apiPeru)
    {
    }

    /**
     * Busca un DNI (8) o RUC (11): en la empresa, luego en el sistema y por último en RENIEC/SUNAT.
     * Si no es cliente de la empresa lo vincula o lo registra automáticamente.
     *
     * @return array{0: Cliente, 1: string}  [cliente, origen]
     */
    public function obtenerPorDocumento(string $numero, int $empresaId): array
    {
        $numero = $this->limpiar($numero);
        $tipoId = $this->tipoDocumentoId($numero);

        $existente = Cliente::withTrashed()->where('tipo_documento_id', $tipoId)->where('numero_documento', $numero)->first();

        if ($existente && $existente->empresas()->whereKey($empresaId)->exists()) {
            if ($existente->trashed()) {
                $existente->restore();
            }

            return [$existente, self::ORIGEN_EMPRESA];
        }

        if ($existente) {
            [$cliente] = $this->registrar(['tipo_documento_id' => $tipoId, 'numero_documento' => $numero] + $existente->only('nombre', 'apellido', 'correo', 'telefono', 'direccion') + ['activo' => true], $empresaId);

            return [$cliente, self::ORIGEN_SISTEMA];
        }

        $datos = $this->consultarExterno($numero);

        [$cliente] = $this->registrar([
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
    public function consultarDocumento(string $numero): array
    {
        $numero = $this->limpiar($numero);
        $tipoId = $this->tipoDocumentoId($numero);

        $existente = Cliente::query()->where('tipo_documento_id', $tipoId)->where('numero_documento', $numero)->first();
        if ($existente) {
            return ['tipo_documento_id' => $tipoId, 'numero' => $numero, 'origen' => self::ORIGEN_SISTEMA]
                + $existente->only('nombre', 'apellido', 'direccion');
        }

        return ['tipo_documento_id' => $tipoId, 'origen' => self::ORIGEN_EXTERNO] + $this->consultarExterno($numero);
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

    /** @return array{0: Cliente, 1: bool}  [cliente, vinculado] — vinculado=true si ya existía en otra empresa */
    public function registrar(array $d, int $empresaId): array
    {
        return DB::transaction(function () use ($d, $empresaId) {
            $existente = Cliente::withTrashed()
                ->where('tipo_documento_id', $d['tipo_documento_id'])
                ->where('numero_documento', $d['numero_documento'])
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if ($existente->empresas()->where('empresas.id', $empresaId)->exists()) {
                    throw ValidationException::withMessages(['numero_documento' => 'Este cliente ya está registrado en tu empresa.']);
                }

                if ($existente->trashed()) {
                    $existente->restore();
                    $existente->update($this->datos($d));
                }

                $existente->empresas()->attach($empresaId);

                return [$existente, ! $existente->wasRecentlyCreated];
            }

            $cliente = Cliente::create($this->datos($d));
            $cliente->empresas()->attach($empresaId);

            return [$cliente, false];
        });
    }

    public function actualizar(Cliente $cliente, array $d): Cliente
    {
        $duplicado = Cliente::withTrashed()
            ->where('tipo_documento_id', $d['tipo_documento_id'])
            ->where('numero_documento', $d['numero_documento'])
            ->where('id', '!=', $cliente->id)
            ->exists();

        if ($duplicado) {
            throw ValidationException::withMessages(['numero_documento' => 'Ya existe otro cliente con ese documento.']);
        }

        $cliente->update($this->datos($d));

        return $cliente;
    }

    /** Lo quita de tu empresa; si nadie más lo usa y nunca tuvo ventas, se elimina */
    public function eliminar(Cliente $cliente, int $empresaId): void
    {
        DB::transaction(function () use ($cliente, $empresaId) {
            if (Venta::query()->where('empresa_id', $empresaId)->where('cliente_id', $cliente->id)->exists()) {
                throw ValidationException::withMessages([
                    'cliente' => "«{$cliente->nombre_completo}» tiene ventas registradas: no se puede eliminar. Desactívalo desde Editar.",
                ]);
            }

            $cliente->empresas()->detach($empresaId);

            if ($cliente->empresas()->doesntExist() && ! Venta::query()->where('cliente_id', $cliente->id)->exists()) {
                $cliente->delete();
            }
        });
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