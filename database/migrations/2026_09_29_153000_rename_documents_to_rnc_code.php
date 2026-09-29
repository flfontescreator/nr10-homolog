<?php

use App\Models\NcDocument;
use Illuminate\Database\Migrations\Migration;

/**
 * Renomeia os códigos de documentos existentes para o padrão RNC-XXXXX
 * (ex.: DN-07 vira RNC-00007), preservando o número sequencial por cliente.
 * O histórico de versões guarda snapshots com o código antigo (intencional:
 * são registros históricos em ponto no tempo).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (NcDocument::query()->whereNotLike('code', 'RNC-%')->orderBy('number')->get() as $document) {
            $document->update(['code' => NcDocument::makeCode($document->number)]);
        }
    }

    public function down(): void
    {
        foreach (NcDocument::query()->whereLike('code', 'RNC-%')->orderBy('number')->get() as $document) {
            $document->update(['code' => 'DN-'.str_pad((string) $document->number, 2, '0', STR_PAD_LEFT)]);
        }
    }
};
