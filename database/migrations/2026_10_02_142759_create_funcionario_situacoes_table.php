<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo PRÓPRIO da "Situação" do funcionário (vínculo: Ativo/Inativo).
     *
     * Não confundir com `situacoes`: aquele é o catálogo de avaliação de ITENS
     * (`funcionario_items.situacao_id`, RNC técnico/fotográfico, default
     * "Não avaliado"). Misturar os dois faria "Ativo"/"Inativo" aparecerem nos
     * dropdowns de avaliação e "Não avaliado" na situação do funcionário.
     */
    public function up(): void
    {
        Schema::create('funcionario_situacoes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 60);
            $table->string('slug', 30)->unique();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique('nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funcionario_situacoes');
    }
};
