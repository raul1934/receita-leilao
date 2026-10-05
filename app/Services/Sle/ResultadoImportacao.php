<?php

namespace App\Services\Sle;

use App\Models\Edital;

final readonly class ResultadoImportacao
{
    /**
     * @param  array<int, string>  $falhas  número do lote => mensagem de erro
     */
    public function __construct(
        public Edital $edital,
        public int $totalLotes,
        public int $lotesDetalhados,
        public array $falhas = [],
    ) {}
}
