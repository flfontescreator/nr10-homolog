<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Estado de trabalho POR DOCUMENTO: cada item do documento tem a própria
     * cópia dos campos de controle (independente do TenantItem compartilhado),
     * permitindo que dois documentos trabalhem o mesmo subitem do cronograma.
     */
    public function up(): void
    {
        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->foreignId('updated_by')->nullable()->after('sort_order')->constrained('users')->nullOnDelete();

            $table->date('data_inspecao')->nullable()->after('updated_by');
            $table->string('condicao_inicial', 40)->nullable()->after('data_inspecao');
            $table->string('setor')->nullable()->after('condicao_inicial');
            $table->json('setores')->nullable()->after('setor');
            $table->string('criticidade')->nullable()->after('setores');
            $table->text('descricao_nc')->nullable()->after('criticidade');
            $table->string('id_relatorio', 60)->nullable()->after('descricao_nc');
            $table->date('prazo_adequacao')->nullable()->after('id_relatorio');
            $table->text('acao')->nullable()->after('prazo_adequacao');
            $table->text('acao_realizada')->nullable()->after('acao');
            $table->date('data_realizacao')->nullable()->after('acao_realizada');
            $table->string('responsavel')->nullable()->after('data_realizacao');
            $table->string('status', 40)->nullable()->after('responsavel');
        });
    }

    public function down(): void
    {
        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->dropColumn([
                'data_inspecao',
                'condicao_inicial',
                'setor',
                'setores',
                'criticidade',
                'descricao_nc',
                'id_relatorio',
                'prazo_adequacao',
                'acao',
                'acao_realizada',
                'data_realizacao',
                'responsavel',
                'status',
            ]);

            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
        });
    }
};
