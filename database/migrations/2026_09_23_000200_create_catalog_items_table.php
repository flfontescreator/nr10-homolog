<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('catalog_items')->nullOnDelete();
            $table->string('source', 20)->index(); // cronograma | prontuario
            $table->string('code', 30)->index();   // ex.: 10.3 | 10.4.13 | 1.1
            $table->unsignedInteger('n1')->default(0);
            $table->unsignedInteger('n2')->default(0);
            $table->unsignedInteger('n3')->default(0);
            $table->unsignedInteger('n4')->default(0);
            $table->boolean('is_section')->default(false)->index(); // agrupador, sem campos de controle
            $table->string('title')->nullable();
            $table->text('description');
            $table->string('criticidade', 40)->nullable();
            $table->string('setor')->nullable();
            $table->text('detalhamento')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['source', 'code']);
            $table->index(['source', 'n1', 'n2', 'n3', 'n4']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};