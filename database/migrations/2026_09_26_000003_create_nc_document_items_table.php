<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nc_document_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('nc_documents')->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_item_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['document_id', 'catalog_item_id']);
            $table->unique(['document_id', 'tenant_item_id']);
            $table->index('tenant_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nc_document_items');
    }
};
