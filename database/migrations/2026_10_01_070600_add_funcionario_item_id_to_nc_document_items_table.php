<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Itens de documentação de funcionário entram nos documentos (RNC) pela
     * tabela funcionario_items, e não mais por tenant_items do item 4 do
     * Prontuário.
     */
    public function up(): void
    {
        // Itens de documentação de funcionário não vêm do catálogo operacional:
        // a âncora é funcionario_items, então catalog_item_id fica livre.
        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable()->change();
        });

        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->foreignId('funcionario_item_id')
                ->nullable()
                ->after('tenant_item_id')
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->index('funcionario_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->dropForeign(['funcionario_item_id']);
            $table->dropIndex(['funcionario_item_id']);
            $table->dropColumn('funcionario_item_id');
        });

        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable(false)->change();
        });
    }
};
