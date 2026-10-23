<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_un_invitado_es_enviado_al_login(): void
    {
        $this->get(route('inicio'))->assertRedirect(route('login'));   // el ERP (/erp); la raíz es la web pública
    }
}
