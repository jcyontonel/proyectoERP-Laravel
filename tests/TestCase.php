<?php

namespace Tests;

use App\Modulos\Facturacion\Sunat\Pasarela;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
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
}
