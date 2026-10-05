<?php

namespace App\Services\Sle;

use Illuminate\Support\Facades\Process;

/**
 * Converte PDF em texto com o pdftotext (pacote poppler-utils da imagem
 * Docker), mantendo o alinhamento das colunas.
 */
class PdfParaTexto
{
    public function converter(string $pdf): string
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'sle-pdf-');
        file_put_contents($arquivo, $pdf);

        try {
            $processo = Process::timeout(60)->run(['pdftotext', '-layout', '-enc', 'UTF-8', $arquivo, '-']);
        } finally {
            @unlink($arquivo);
        }

        if ($processo->failed()) {
            throw new SleException('Não foi possível ler o PDF: '.trim($processo->errorOutput() ?: $processo->output()));
        }

        return $processo->output();
    }
}
