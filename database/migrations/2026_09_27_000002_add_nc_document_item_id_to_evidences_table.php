<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidência pode pertencer ao CRONOGRAMA (plano, nc_document_item_id nulo)
     * ou ao TRABALHO de um item DENTRO de um documento (nc_document_item_id
     * preenchido). tenant_item_id continua preenchido sempre (âncora de origem).
     */
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->foreignId('nc_document_item_id')->nullable()->after('tenant_item_id')->constrained('nc_document_items')->cascadeOnDelete();
            $table->index('nc_document_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->dropIndex(['nc_document_item_id']);
            $table->dropForeign(['nc_document_item_id']);
            $table->dropColumn('nc_document_item_id');
        });
    }
};
