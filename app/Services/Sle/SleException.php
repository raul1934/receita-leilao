<?php

namespace App\Services\Sle;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SleException extends RuntimeException
{
    public static function resposta(string $uri, Response $response, ?Throwable $anterior = null): self
    {
        // A API responde HTTP 500 com {"code":"ER9999","message":"..."} tanto
        // para falhas quanto para editais/lotes que não existem.
        $mensagem = $response->json('message') ?? Str::limit(trim($response->body()), 200);

        return new self("SLE respondeu HTTP {$response->status()} em {$uri}: {$mensagem}", $response->status(), $anterior);
    }
}
