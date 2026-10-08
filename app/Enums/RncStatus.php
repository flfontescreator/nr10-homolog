<?php

namespace App\Enums;

/**
 * Situação do RNC. O rascunho é editável livremente; "publicado" significa que
 * existe uma revisão oficial (Rev:000N) congelada — o conteúdo pode mudar de
 * novo, mas só voltará a ter valor oficial na próxima publicação. "arquivado"
 * é o estado final: a RNC vira somente leitura até ser desarquivada (ver
 * `RncController::authorizeNotArchived()`).
 */
enum RncStatus: string
{
    case Rascunho = 'rascunho';
    case Publicado = 'publicado';
    case Arquivado = 'arquivado';

    public function label(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Publicado => 'Publicado',
            self::Arquivado => 'Arquivada',
        };
    }

    public function isPublished(): bool
    {
        return $this === self::Publicado;
    }

    public function isArchived(): bool
    {
        return $this === self::Arquivado;
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Rascunho => 'badge-draft',
            self::Publicado => 'badge-published',
            self::Arquivado => 'badge-archived',
        };
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
