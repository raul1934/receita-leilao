<?php

namespace App\Services\Sle;

use App\Models\Edital;

final readonly class ResultadoImportacao
{
    /**
     * @param  array<int, string>  $falhas  número do lote => mensagem de erro
     * @param  string|null  $erroResultado  falha ao ler o extrato do leilão (valores de arremate)
     * @param  int  $lotesSemDetalhesNoPortal  lotes baixados, cujos detalhes o portal não mostra
     */
    public function __construct(
        public Edital $edital,
        public int $totalLotes,
        public int $lotesDetalhados,
        public array $falhas = [],
        public ?string $erroResultado = null,
        public int $lotesSemDetalhesNoPortal = 0,
    ) {}
}
