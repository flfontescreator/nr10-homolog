<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogos GLOBAIS do relatório RNC (mesma lista para todos os clientes):
     * projetos, criticidades e classificações de risco. Os itens de norma
     * (`norma_itens`) alimentam o picker de referências normativas de cada NC,
     * ligado à sua `norma_tecnica` (ex.: NR-10).
     */
    public function up(): void
    {
        Schema::create('projetos', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 160);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique('nome');
        });

        Schema::create('criticidades', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique('nome');
        });

        Schema::create('classificacoes_risco', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique('nome');
        });

        Schema::create('norma_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('norma_tecnica_id')->constrained('normas_tecnicas')->cascadeOnDelete();
            $table->string('codigo', 30);
            $table->text('descricao')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['norma_tecnica_id', 'codigo']);
        });

        // Referências normativas por não conformidade (N:N).
        Schema::create('rnc_item_norma_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rnc_item_id')->constrained('rnc_items')->cascadeOnDelete();
            $table->foreignId('norma_item_id')->constrained('norma_itens')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['rnc_item_id', 'norma_item_id'], 'rnc_item_norma_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnc_item_norma_item');
        Schema::dropIfExists('norma_itens');
        Schema::dropIfExists('classificacoes_risco');
        Schema::dropIfExists('criticidades');
        Schema::dropIfExists('projetos');
    }
};
