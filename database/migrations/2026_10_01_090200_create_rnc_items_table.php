<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Não conformidades do relatório. São as linhas da tabela do RNC:
     * apontamento + ação corretiva. Evidências ancoram em `evidences.rnc_item_id`.
     */
    public function up(): void
    {
        Schema::create('rnc_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rnc_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('numero');

            // A NC encontrada
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('local_equipamento')->nullable();
            $table->string('criticidade', 40)->nullable();

            // A ação corretiva
            $table->text('acao_corretiva')->nullable();
            $table->date('prazo_acao')->nullable();
            $table->date('data_realizacao')->nullable();
            $table->string('responsavel_acao')->nullable();

            $table->string('status', 20)->default('aberta');
            $table->timestamps();

            $table->unique(['rnc_id', 'numero']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnc_items');
    }
};
