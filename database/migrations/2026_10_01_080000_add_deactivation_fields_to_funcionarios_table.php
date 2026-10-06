<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Desativação de funcionário. O registro nunca é apagado: a "demissão"
     * desliga a edição mas preserva todo o histórico de itens e evidências.
     *
     * A reativação é uma JANELA TEMPORÁRIA de 48h: só Admin/SuperAdmin,
     * com senha e justificativa, e ao expirar o funcionário volta a ficar
     * inativo sozinho (comando `funcionarios:expira-reativacoes`).
     */
    public function up(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            $table->boolean('ativo')->default(true)->after('cpf');
            $table->timestamp('desativado_em')->nullable()->after('ativo');
            $table->foreignId('desativado_por')->nullable()->after('desativado_em')->constrained('users')->nullOnDelete();
            $table->text('desativacao_justificativa')->nullable()->after('desativado_por');
            $table->timestamp('reativado_em')->nullable()->after('desativacao_justificativa');
            $table->foreignId('reativado_por')->nullable()->after('reativado_em')->constrained('users')->nullOnDelete();
            $table->text('reativacao_justificativa')->nullable()->after('reativado_por');
            $table->timestamp('reativacao_expira_em')->nullable()->after('reativacao_justificativa');
        });

        // A validade do documento pertence à EVIDÊNCIA (box de anexo com
        // "Se aplica"), não ao item de controle.
        Schema::table('funcionario_items', function (Blueprint $table) {
            $table->dropColumn(['validade_aplica', 'data_validade']);
        });
    }

    public function down(): void
    {
        Schema::table('funcionario_items', function (Blueprint $table) {
            $table->boolean('validade_aplica')->default(false);
            $table->date('data_validade')->nullable();
        });

        Schema::table('funcionarios', function (Blueprint $table) {
            $table->dropColumn([
                'ativo',
                'desativado_em',
                'desativado_por',
                'desativacao_justificativa',
                'reativado_em',
                'reativado_por',
                'reativacao_justificativa',
                'reativacao_expira_em',
            ]);
        });
    }
};
