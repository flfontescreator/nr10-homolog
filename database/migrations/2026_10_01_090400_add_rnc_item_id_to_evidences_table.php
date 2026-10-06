<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Terceira âncora de evidência: `rnc_item_id`, do módulo RNC (novo e
     * independente de Não Conformidades). `tenant_item_id` e
     * `funcionario_item_id` continuam nullable, então nenhuma evidência
     * existente é tocada — as âncoras seguem mutuamente exclusivas.
     */
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->foreignId('rnc_item_id')
                ->nullable()
                ->after('funcionario_item_id')
                ->constrained('rnc_items')
                ->cascadeOnDelete();
        });

        Schema::table('evidences', function (Blueprint $table) {
            $table->index(['tenant_id', 'rnc_item_id']);
        });
    }

    public function down(): void
    {
        if (DB::table('evidences')->whereNotNull('rnc_item_id')->exists()) {
            return;
        }

        Schema::table('evidences', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'rnc_item_id']);
        });

        Schema::table('evidences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rnc_item_id');
        });
    }
};
