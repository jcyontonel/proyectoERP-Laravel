<?php

namespace Tests;

use App\Modulos\Facturacion\Sunat\Pasarela;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Fakes\PasarelaFalsa;

abstract class TestCase extends BaseTestCase
{
    /** SUNAT simulado: ninguna prueba llama a SUNAT de verdad (salvo FacturadorRealTest, que lo pide) */
    protected PasarelaFalsa $sunat;

    protected function setUp(): void
    {
        parent::setUp();

        // XML, CDR y certificados en un disco falso: las pruebas no escriben en storage/app
        Storage::fake('local');
        $this->sunat = new PasarelaFalsa();
        $this->app->instance(Pasarela::class, $this->sunat);
    }

    /**
     * RefreshDatabase borra la base: solo se permite en memoria o en una base de pruebas, nunca en la real.
     * Va aquí (antes de los traits) y no en beforeRefreshingDatabase(), porque el método vacío del trait
     * RefreshDatabase reemplaza al heredado y la verificación nunca correría.
     */
    protected function setUpTraits()
    {
        $base = (string) DB::connection()->getDatabaseName();
        if ($base !== ':memory:' && ! preg_match('/_(pruebas|test)$/', $base)) {
            throw new RuntimeException("Las pruebas borran la base de datos: «{$base}» no es una base de pruebas (*_pruebas, *_test o en memoria).");
        }

        return parent::setUpTraits();
    }
}
