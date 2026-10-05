<?php

namespace Tests\Unit;

use App\Services\Sle\EditalRef;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EditalRefTest extends TestCase
{
    #[DataProvider('entradasValidas')]
    public function test_interpreta_os_formatos_aceitos(string $entrada): void
    {
        $ref = EditalRef::parse($entrada);

        $this->assertSame([700100, 12, 2026], [$ref->unidade, $ref->numero, $ref->exercicio]);
        $this->assertSame('700100/12/2026', $ref->path());
    }

    public static function entradasValidas(): array
    {
        return [
            'url do portal' => ['https://www25.receita.fazenda.gov.br/sle-sociedade/portal/edital/700100/12/2026'],
            'url com barra no final' => ['https://www25.receita.fazenda.gov.br/sle-sociedade/portal/edital/700100/12/2026/'],
            'url de um lote' => ['https://www25.receita.fazenda.gov.br/sle-sociedade/portal/edital/700100/12/2026/lote/1'],
            'identificador curto' => ['700100/12/2026'],
            'código com zeros à esquerda' => ['0700100/000012/2026'],
            'com espaços' => ['  700100/12/2026  '],
        ];
    }

    #[DataProvider('entradasInvalidas')]
    public function test_rejeita_entradas_invalidas(string $entrada): void
    {
        $this->expectException(InvalidArgumentException::class);

        EditalRef::parse($entrada);
    }

    public static function entradasInvalidas(): array
    {
        return [
            'vazio' => [''],
            'texto' => ['leilão'],
            'sem ano' => ['700100/12'],
            'ano com dois dígitos' => ['700100/12/26'],
            'url sem edital' => ['https://www25.receita.fazenda.gov.br/sle-sociedade/portal'],
        ];
    }
}
