<?php

namespace Tests\Support;

/**
 * Gera um PDF mínimo de uma página com uma linha de texto (Courier) por
 * item, para testar a leitura de PDFs sem guardar documentos reais.
 */
final class PdfSimples
{
    /**
     * @param  list<string>  $linhas
     */
    public static function gerar(array $linhas): string
    {
        $conteudo = "BT /F1 9 Tf 11 TL 20 800 Td\n";

        foreach ($linhas as $linha) {
            $texto = mb_convert_encoding($linha, 'Windows-1252', 'UTF-8');
            $conteudo .= '('.addcslashes($texto, '()\\').") Tj T*\n";
        }

        $conteudo .= 'ET';

        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($conteudo)." >>\nstream\n{$conteudo}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
        ];

        $pdf = "%PDF-1.4\n";
        $posicoes = [];

        foreach ($objetos as $indice => $objeto) {
            $posicoes[] = strlen($pdf);
            $pdf .= ($indice + 1)." 0 obj\n{$objeto}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";

        foreach ($posicoes as $posicao) {
            $pdf .= sprintf("%010d 00000 n \n", $posicao);
        }

        return $pdf.'trailer << /Size '.(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
