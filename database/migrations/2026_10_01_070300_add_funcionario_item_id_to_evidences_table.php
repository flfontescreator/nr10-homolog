<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A evidência passa a ter duas âncoras possíveis: item de catálogo
     * (cronograma, prontuário, não conformidades) OU item de funcionário.
     * Exatamente uma das duas é preenchida. Os arquivos de biblioteca
     * (pivôs evidence_document/evidence_tenant_item) seguem intactos.
     */
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->foreignId('funcionario_item_id')
                ->nullable()
                ->after('tenant_item_id')
                ->constrained('funcionario_items')
                ->cascadeOnDelete();
        });

        // tenant_item_id deixa de ser obrigatório: evidência de funcionário
        // não passa por item de catálogo.
        Schema::table('evidences', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_item_id')->nullable()->change();
        });

        // Remove o índice composto anterior (tenant_id, tenant_item_id) e
        // recria tolerante a NULL, junto do índice da nova âncora.
        Schema::table('evidences', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'tenant_item_id']);
        });

        Schema::table('evidences', function (Blueprint $table) {
            $table->index(['tenant_id', 'tenant_item_id']);
            $table->index(['tenant_id', 'funcionario_item_id']);
        });
    }

    public function down(): void
    {
        // Evidências de funcionário não têm item de catálogo: só é possível
        // reverter quando não houver nenhuma delas.
        if (DB::table('evidences')->whereNull('tenant_item_id')->exists()) {
            return;
        }

        Schema::table('evidences', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'funcionario_item_id']);
            $table->dropIndex(['tenant_id', 'tenant_item_id']);
        });

        Schema::table('evidences', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_item_id')->nullable(false)->change();
        });

        Schema::table('evidences', function (Blueprint $table) {
            $table->index(['tenant_id', 'tenant_item_id']);
        });

        Schema::table('evidences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('funcionario_item_id');
        });
    }
};
