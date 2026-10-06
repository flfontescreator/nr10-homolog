<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Base de cidades por UF (dado público IBGE/Correios) usada para
     * sugerir a cidade no cadastro do cliente e filtrar os bairros.
     */
    public function up(): void
    {
        Schema::create('cidades', function (Blueprint $table) {
            $table->id();
            $table->char('uf', 2);
            $table->string('nome', 150);
            $table->timestamps();
            $table->unique(['uf', 'nome']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cidades');
    }
};
