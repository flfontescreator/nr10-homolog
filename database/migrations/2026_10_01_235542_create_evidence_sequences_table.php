<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contadores GLOBAIS da nomenclatura de arquivos anexados.
     *
     * Uma linha por CATEGORIA (não por módulo e não por cliente): `img` para
     * imagens e `doc` para documentos/PDF. O próximo número é o mesmo para
     * qualquer módulo que anexe aquela categoria, o que mantém a ordenação
     * cronológica e evita colisão de nomes entre telas.
     */
    public function up(): void
    {
        Schema::create('evidence_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('categoria', 3)->unique();
            $table->unsignedBigInteger('ultimo')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_sequences');
    }
};
