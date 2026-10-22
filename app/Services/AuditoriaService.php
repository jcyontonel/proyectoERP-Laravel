<?php

namespace App\Services;

use App\Models\AuditoriaEvento;
use App\Models\Empresa;
use App\Support\EmpresaActual;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Escribe la bitácora de auditoría. Una instancia por petición (scoped): todos los eventos de la
 * misma petición comparten request_id (ej. la venta, su cobro y su kardex).
 * No registra durante migraciones y seeders, y un fallo al auditar nunca interrumpe la operación.
 */
class AuditoriaService
{
    /** Campos que nunca se guardan con su valor */
    private const SENSIBLES = ['password', 'remember_token', 'sunat_clave_sol', 'certificado_clave'];

    /** Campos que no aportan (cambian solos) */
    private const IGNORADOS = ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'email_verified_at'];

    private ?string $requestId = null;

    /** Alta, cambio o baja de un modelo con el trait Auditable */
    public function modelo(Model $modelo, string $accion): void
    {
        $antes = [];
        $despues = [];

        if ($accion === 'actualizado') {
            $cambios = array_diff_key($modelo->getChanges(), array_flip(self::IGNORADOS));
            if (! $cambios) {
                return;
            }
            foreach ($cambios as $campo => $valor) {
                $antes[$campo] = $this->valor($modelo, $campo, $modelo->getOriginal($campo), $modelo->getRawOriginal($campo));
                $despues[$campo] = $this->valor($modelo, $campo, $valor, $valor);
            }
        } elseif ($accion === 'eliminado') {
            $antes = $this->limpiar($modelo, $modelo->getAttributes());
        } else {
            $despues = $this->limpiar($modelo, $modelo->getAttributes());
        }

        $this->registrar($accion, class_basename($modelo), $modelo->getKey(), $antes, $despues, $this->empresaDe($modelo));
    }

    /** Evento libre (inicio de sesión, cambio de permisos, rol asignado…) */
    public function registrar(string $accion, string $entidad, int|string|null $entidadId = null, array $antes = [], array $despues = [], ?int $empresaId = null, ?int $userId = null): void
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return;   // migraciones, seeders y comandos
        }

        try {
            $user = auth()->user();

            AuditoriaEvento::create([
                'empresa_id'       => $empresaId ?? ($user ? EmpresaActual::id($user) : null),
                'user_id'          => $userId ?? $user?->getKey(),
                'entidad'          => $entidad,
                'entidad_id'       => is_numeric($entidadId) ? (int) $entidadId : null,
                'accion'           => $accion,
                'datos_anteriores' => $antes ?: null,
                'datos_nuevos'     => $despues ?: null,
                'ip'               => request()?->ip(),
                'request_id'       => $this->requestId ??= (string) Str::uuid(),
            ]);
        } catch (Throwable $e) {
            report($e);   // la auditoría nunca debe impedir vender o comprar
        }
    }

    /* ───────────────────────── Privado ───────────────────────── */

    private function empresaDe(Model $modelo): ?int
    {
        if ($modelo instanceof Empresa) {
            return (int) $modelo->getKey();
        }

        $id = $modelo->getAttribute('empresa_id');

        return $id ? (int) $id : null;   // null = la empresa actual del usuario (clientes, usuarios, catálogos)
    }

    private function limpiar(Model $modelo, array $atributos): array
    {
        $datos = [];
        foreach (array_diff_key($atributos, array_flip(self::IGNORADOS)) as $campo => $valor) {
            if ($valor === null || $valor === '') {
                continue;
            }
            $datos[$campo] = $this->valor($modelo, $campo, $valor, $valor);
        }

        return $datos;
    }

    private function valor(Model $modelo, string $campo, mixed $valor, mixed $crudo): mixed
    {
        if (in_array($campo, self::SENSIBLES, true) || in_array($campo, $modelo->getHidden(), true)) {
            return filled($crudo) ? '••••••' : null;
        }
        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d H:i:s');
        }
        if (is_bool($valor) || is_numeric($valor) || is_null($valor)) {
            return $valor;
        }

        return is_scalar($valor) ? Str::limit((string) $valor, 500) : json_decode(json_encode($valor), true);
    }
}
