<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_tenant_item', function (Blueprint $table) {
            $table->dropForeign(['tenant_item_id']);
        });

        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropForeign(['funcionario_id']);
        });

        // A unicidade volta a ser (tenant_id, catalog_item_id).
        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'funcionario_id', 'catalog_item_id']);
        });

        // Índices que mencionam a coluna também precisam sair antes do drop.
        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'funcionario_id']);
        });

        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropColumn('funcionario_id');
        });

        Schema::table('tenant_items', function (Blueprint $table) {
            $table->unique(['tenant_id', 'catalog_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'catalog_item_id']);
            $table->foreignId('funcionario_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->unique(['tenant_id', 'funcionario_id', 'catalog_item_id']);
        });

        Schema::table('evidence_tenant_item', function (Blueprint $table) {
            $table->foreign(['tenant_item_id'])->references('id')->on('tenant_items')->cascadeOnDelete();
        });
    }
};
