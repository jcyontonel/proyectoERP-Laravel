<?php

namespace App\Modulos\Plataforma\Support;

use Symfony\Component\HttpFoundation\Response;

/** Respuesta de una llamada interna a la API, con la misma forma que usaba el panel (body() y header()) */
final class RespuestaInterna
{
    public function __construct(private Response $respuesta)
    {
    }

    public function body(): string
    {
        return (string) $this->respuesta->getContent();
    }

    public function header(string $nombre): ?string
    {
        return $this->respuesta->headers->get($nombre);
    }
}
