<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base de bairros por cidade (dado público IBGE/Correios) para o campo
     * bairro do cadastro do cliente. É complementada em runtime pelo que a
     * busca de CEP retorna — o bairro não está no cadastro da empresa.
     */
    public function up(): void
    {
        Schema::create('bairros', function (Blueprint $table) {
            $table->id();
            $table->char('uf', 2);
            $table->string('cidade', 150);
            $table->string('nome', 150);
            $table->timestamps();
            $table->unique(['uf', 'cidade', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bairros');
    }
};
