<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O "Tipo de RNC" (rnc_types) não existe mais no relatório: o modelo do
     * relatório (técnica|fotográfica) e as classificações passam a vir dos
     * catálogos próprios. A tabela sai depois de a FK em `rncs` ser removida.
     */
    public function up(): void
    {
        Schema::dropIfExists('rnc_types');
    }

    public function down(): void
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
    }
};
