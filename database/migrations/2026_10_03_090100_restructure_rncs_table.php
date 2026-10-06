<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reestrutura o cabeçalho do RNC para o novo formato de relatório:
     * - `modelo` (tecnica|fotografica) escolhido na criação;
     * - `projeto_id` (catálogo de projetos) no lugar de `obra_unidade`;
     * - `data_emissao` vira `data_inspecao`;
     * - blocos livres em Markdown: introdução/recomendações (técnica) e
     *   resumo/conclusão (fotográfica).
     *
     * Tipo de RNC (`rnc_types`) e norma única do cabeçalho saem: as normas
     * passam a ser referências por item.
     */
    public function up(): void
    {
        Schema::table('rncs', function (Blueprint $table) {
            $table->dropForeign(['rnc_type_id']);
            $table->dropForeign(['norma_tecnica_id']);
        });

        Schema::table('rncs', function (Blueprint $table) {
            $table->foreignId('projeto_id')->nullable()->after('descricao')->constrained('projetos')->nullOnDelete();
            $table->string('modelo', 20)->default('tecnica')->after('projeto_id');
            $table->text('introducao')->nullable()->after('modelo');
            $table->text('recomendacoes')->nullable()->after('introducao');
            $table->text('resumo')->nullable()->after('recomendacoes');
            $table->text('conclusao')->nullable()->after('resumo');
        });

        Schema::table('rncs', function (Blueprint $table) {
            $table->renameColumn('data_emissao', 'data_inspecao');
        });

        Schema::table('rncs', function (Blueprint $table) {
            $table->dropColumn(['obra_unidade', 'rnc_type_id', 'norma_tecnica_id']);
        });
    }

    public function down(): void
    {
        Schema::table('rncs', function (Blueprint $table) {
            $table->string('obra_unidade')->nullable();
            $table->foreignId('rnc_type_id')->nullable()->constrained('rnc_types')->nullOnDelete();
            $table->foreignId('norma_tecnica_id')->nullable()->constrained('normas_tecnicas')->nullOnDelete();
        });

        Schema::table('rncs', function (Blueprint $table) {
            $table->renameColumn('data_inspecao', 'data_emissao');
        });

        Schema::table('rncs', function (Blueprint $table) {
            $table->dropForeign(['projeto_id']);
            $table->dropColumn(['projeto_id', 'modelo', 'introducao', 'recomendacoes', 'resumo', 'conclusao']);
        });
    }
};
