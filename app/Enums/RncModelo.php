<?php

namespace App\Enums;

/**
 * Modelo do relatório de não conformidade. É escolhido na criação e muda o
 * layout do relatório (blocos e forma de apresentação), não os dados.
 */
enum RncModelo: string
{
    case Tecnica = 'tecnica';
    case Fotografica = 'fotografica';

    public function label(): string
    {
        return match ($this) {
            self::Tecnica => 'RNC Técnico',
            self::Fotografica => 'RNC Fotográfico',
        };
    }

    public function isFotografica(): bool
    {
        return $this === self::Fotografica;
    }

    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
