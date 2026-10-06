<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogos GLOBAIS do módulo RNC (sem tenant_id): são a mesma lista de
     * tipos e normas para todos os clientes, igual ao `catalog_items`.
     */
    public function up(): void
    {
        Schema::create('rnc_types', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->string('codigo', 20)->unique();
            $table->string('criticidade_padrao', 40)->nullable();
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });

        Schema::create('normas_tecnicas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 60)->unique();
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('normas_tecnicas');
        Schema::dropIfExists('rnc_types');
    }
};
