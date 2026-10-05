<?php

namespace App\Services\Sle;

use InvalidArgumentException;

/**
 * Identificador de um edital no SLE: unidade / número / exercício.
 */
final readonly class EditalRef
{
    public function __construct(
        public int $unidade,
        public int $numero,
        public int $exercicio,
    ) {}

    /**
     * Aceita a URL do portal (".../portal/edital/700100/12/2026", inclusive de
     * um lote), o identificador curto ("700100/12/2026") ou o código com
     * zeros à esquerda ("0700100/000012/2026").
     */
    public static function parse(string $entrada): self
    {
        $texto = trim($entrada);

        if (($pos = stripos($texto, '/edital/')) !== false) {
            $texto = substr($texto, $pos + strlen('/edital/'));
        }

        if (! preg_match('~^(\d{1,7})/(\d{1,6})/(\d{4})(?:[/?#]|$)~', $texto, $m)) {
            throw new InvalidArgumentException(
                "Edital inválido: \"{$entrada}\". Use a URL do portal ou o formato unidade/número/ano (ex: 700100/12/2026)."
            );
        }

        return new self((int) $m[1], (int) $m[2], (int) $m[3]);
    }

    public function path(): string
    {
        return "{$this->unidade}/{$this->numero}/{$this->exercicio}";
    }

    public function urlPortal(): string
    {
        return rtrim(config('sle.base_url'), '/').'/portal/edital/'.$this->path();
    }

    public function __toString(): string
    {
        return $this->path();
    }
}
