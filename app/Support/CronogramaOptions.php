<?php

namespace App\Support;

use Database\Seeders\CatalogSeeder;

/**
 * Listas fixas do Cronograma de Adequação, extraídas da planilha de referência.
 * Os valores vêm da coluna "Setor" (índice 3) e da coluna "Condição Inicial"
 * (índice 6) do arquivo storage/app/imports/cronograma.csv.
 */
class CronogramaOptions
{
    /**
     * Valores permitidos para "Condição inicial".
     */
    public static function condicoesIniciais(): array
    {
        return [
            'Não Adequada',
            'Não Avaliada',
        ];
    }

    /**
     * Valores permitidos para "Criticidade" de um subitem (inclusive os
     * valores extras introduzidos pelo cliente: "Não aplicada" e "Em partes").
     */
    public static function criticidades(): array
    {
        return [
            'Não aplicada',
            'Em partes',
            'ALTA',
            'MÉDIA',
            'Crítica / Grave e Iminente Risco (GIR)',
        ];
    }

    /**
     * Valores únicos (ordenados) da coluna Setor da planilha de cronograma.
     */
    public static function setores(): array
    {
        static $setores = null;

        if ($setores !== null) {
            return $setores;
        }

        $rows = CatalogSeeder::csvRows(storage_path('app/imports/cronograma.csv'));

        $setores = collect($rows)
            ->slice(2) // pula título e cabeçalho
            ->map(fn ($row) => trim((string) ($row[3] ?? '')))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $setores;
    }
}
