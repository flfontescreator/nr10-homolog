<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Normaliza os valores legados de "Condição inicial" para a lista
     * compartilhada entre Cronograma e Prontuário.
     */
    public function up(): void
    {
        foreach (['tenant_items', 'nc_document_items'] as $table) {
            DB::table($table)->where('condicao_inicial', 'Não Adequada')->update(['condicao_inicial' => 'Não adequado']);
            DB::table($table)->where('condicao_inicial', 'Não Avaliada')->update(['condicao_inicial' => 'Não avaliado']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['tenant_items', 'nc_document_items'] as $table) {
            DB::table($table)->where('condicao_inicial', 'Não adequado')->update(['condicao_inicial' => 'Não Adequada']);
            DB::table($table)->where('condicao_inicial', 'Não avaliado')->update(['condicao_inicial' => 'Não Avaliada']);
        }
    }
};
