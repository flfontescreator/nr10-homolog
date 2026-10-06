<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Itens de documentação do Funcionário. Substituem os antigos subitens
     * 4.x do Prontuário (tabela tenant_items): aqui cada item é sequencial
     * por funcionário, pode ou não ter evidência, e carrega os campos de
     * controle do módulo (validade, situação, prazos, comentário).
     */
    public function up(): void
    {
        Schema::create('funcionario_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('funcionario_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->foreignId('situacao_id')->nullable()->constrained('situacoes')->nullOnDelete();
            $table->boolean('validade_aplica')->default(false);
            $table->date('data_validade')->nullable();
            $table->date('prazo_adequacao')->nullable();
            $table->date('data_adequacao')->nullable();
            $table->date('data_verificacao')->nullable();
            $table->text('comentario')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Numeração sequencial por funcionário, sem repetição.
            $table->unique(['funcionario_id', 'numero']);
            $table->index(['tenant_id', 'funcionario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funcionario_items');
    }
};
