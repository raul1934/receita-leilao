<?php

namespace App\Jobs;

use App\Services\Sle\EditalImporter;
use App\Services\Sle\EditalRef;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportarEdital implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Editais grandes têm centenas de lotes e cada lote é uma requisição.
     */
    public int $timeout = 1800;

    public int $tries = 2;

    /**
     * Evita importar o mesmo edital em paralelo.
     */
    public int $uniqueFor = 3600;

    public function __construct(
        public EditalRef $ref,
        public bool $comDetalhes = true,
        public ?string $cidade = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->ref->path();
    }

    public function handle(EditalImporter $importer): void
    {
        $resultado = $importer->importar($this->ref, $this->comDetalhes, ['cidade' => $this->cidade]);

        Log::info("Edital {$this->ref} importado", [
            'lotes' => $resultado->totalLotes,
            'lotes_detalhados' => $resultado->lotesDetalhados,
            'falhas' => $resultado->falhas,
        ]);
    }
}
