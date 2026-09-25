<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropUnique('tenant_items_tenant_id_catalog_item_id_unique');
        });

        Schema::table('tenant_items', function (Blueprint $table) {
            $table->foreignId('funcionario_id')->nullable()->after('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->index(['tenant_id', 'funcionario_id']);
            $table->unique(['tenant_id', 'funcionario_id', 'catalog_item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'funcionario_id', 'catalog_item_id']);
            $table->dropIndex(['tenant_id', 'funcionario_id']);
            $table->dropConstrainedForeignId('funcionario_id');
        });

        Schema::table('tenant_items', function (Blueprint $table) {
            $table->unique(['tenant_id', 'catalog_item_id']);
        });
    }
};
