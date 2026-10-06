<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos cadastrais do funcionário:
     *
     * - `data_admissao`: data PURA (sem hora). Fica nullable para não obrigar
     *   o preenchimento nos registros já existentes.
     * - `situacao_id`: FK para `funcionario_situacoes` (Ativo/Inativo). Não
     *   confundir com `situacoes` (avaliação de itens). Nullable porque os
     *   funcionários já cadastrados não têm valor; a tela exibe `-`.
     */
    public function up(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            $table->date('data_admissao')->nullable()->after('cpf');
            $table->foreignId('situacao_id')
                ->nullable()
                ->after('data_admissao')
                ->constrained('funcionario_situacoes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('situacao_id');
            $table->dropColumn('data_admissao');
        });
    }
};
