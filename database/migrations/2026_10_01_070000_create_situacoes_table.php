<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cadastro compartilhado de Situação: usado no Funcionário (item de
        // controle), no RNC Técnico e no RNC Fotográfico. Administrado por
        // Admin/Super Admin, alimentado pela GreenJob.
        Schema::create('situacoes', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique('nome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('situacoes');
    }
};
