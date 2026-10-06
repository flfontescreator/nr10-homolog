<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reestrutura a não conformidade para o novo relatório:
     * - `criticidade_id`, `classificacao_risco_id` e `situacao_id` (catálogos);
     * - `acao_corretiva` → `recomendacao`; `prazo_acao` → `prazo_adequacao`;
     *   `data_realizacao` → `data_adequacao`;
     * - saem `local_equipamento`, `responsavel_acao`, o enum `status` e a
     *   criticidade em texto livre.
     */
    public function up(): void
    {
        Schema::table('rnc_items', function (Blueprint $table) {
            if (Schema::hasIndex('rnc_items', ['tenant_id', 'status'])) {
                $table->dropIndex(['tenant_id', 'status']);
            }
        });

        Schema::table('rnc_items', function (Blueprint $table) {
            $table->foreignId('criticidade_id')->nullable()->after('descricao')->constrained('criticidades')->nullOnDelete();
            $table->foreignId('classificacao_risco_id')->nullable()->after('criticidade_id')->constrained('classificacoes_risco')->nullOnDelete();
            $table->foreignId('situacao_id')->nullable()->after('classificacao_risco_id')->constrained('situacoes')->nullOnDelete();
        });

        Schema::table('rnc_items', function (Blueprint $table) {
            $table->renameColumn('acao_corretiva', 'recomendacao');
            $table->renameColumn('prazo_acao', 'prazo_adequacao');
            $table->renameColumn('data_realizacao', 'data_adequacao');
        });

        Schema::table('rnc_items', function (Blueprint $table) {
            $table->dropColumn(['local_equipamento', 'responsavel_acao', 'status', 'criticidade']);
        });
    }

    public function down(): void
    {
        Schema::table('rnc_items', function (Blueprint $table) {
            $table->string('local_equipamento')->nullable();
            $table->string('responsavel_acao')->nullable();
            $table->string('status', 20)->default('aberta');
            $table->string('criticidade', 40)->nullable();
        });

        Schema::table('rnc_items', function (Blueprint $table) {
            $table->renameColumn('recomendacao', 'acao_corretiva');
            $table->renameColumn('prazo_adequacao', 'prazo_acao');
            $table->renameColumn('data_adequacao', 'data_realizacao');
        });

        Schema::table('rnc_items', function (Blueprint $table) {
            $table->dropForeign(['criticidade_id']);
            $table->dropForeign(['classificacao_risco_id']);
            $table->dropForeign(['situacao_id']);
            $table->dropColumn(['criticidade_id', 'classificacao_risco_id', 'situacao_id']);
        });

        Schema::table('rnc_items', function (Blueprint $table) {
            $table->index(['tenant_id', 'status']);
        });
    }
};
