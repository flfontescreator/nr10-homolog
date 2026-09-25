<?php

namespace App\Enums;

/**
 * Situação fixa dos itens de controle (Cronograma). Lista fechada definida
 * com o cliente — evita texto livre no banco.
 */
enum ItemStatus: string
{
    case Pendente = 'Pendente';
    case EmAndamento = 'Em andamento';
    case Concluido = 'Concluído';
    case Auditoria = 'Auditoria';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::EmAndamento => 'Em andamento',
            self::Concluido => 'Concluído',
            self::Auditoria => 'Auditoria',
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
