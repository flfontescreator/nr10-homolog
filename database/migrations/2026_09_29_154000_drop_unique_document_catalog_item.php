<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Um documento operacional pode conter o MESMO sub-item do item 4 para
        // funcionários diferentes (ex.: 4.1 do funcionário A e 4.1 do B), cada
        // um com evidência própria. A unicidade (document_id, catalog_item_id)
        // impedia isso; a unicidade (document_id, tenant_item_id) continua.
        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->dropUnique('nc_document_items_document_id_catalog_item_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('nc_document_items', function (Blueprint $table) {
            $table->unique(['document_id', 'catalog_item_id']);
        });
    }
};
