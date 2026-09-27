<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'draft';
    case Finalized = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Rascunho',
            self::Finalized => 'Finalizado',
        };
    }

    public function isFinalized(): bool
    {
        return $this === self::Finalized;
    }
}
