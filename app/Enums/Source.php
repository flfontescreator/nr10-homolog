<?php

namespace App\Enums;

enum Source: string
{
    case Cronograma = 'cronograma';
    case Prontuario = 'prontuario';
    case Checklist = 'checklist';

    public function label(): string
    {
        return match ($this) {
            self::Cronograma => 'Cronograma Adequação NR-10',
            self::Prontuario => 'Prontuário NR-10',
            self::Checklist => 'Não Conformidades',
        };
    }
}
