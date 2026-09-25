<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            // Campos de controle — Cronograma Adequação NR-10
            $table->date('data_inspecao')->nullable();
            $table->string('condicao_inicial', 40)->nullable();
            $table->text('descricao_nc')->nullable();
            $table->string('id_relatorio', 60)->nullable();
            $table->date('prazo_adequacao')->nullable();
            $table->text('acao')->nullable();
            $table->text('acao_realizada')->nullable();
            $table->date('data_realizacao')->nullable();
            $table->string('responsavel')->nullable();
            $table->string('status', 40)->nullable();

            // Campos de controle — Prontuário NR-10
            $table->string('evidencias_status', 30)->nullable(); // Digital | Pendente | Nao Aplicado
            $table->string('data_validade', 40)->nullable();     // "1 ano", "Conforme Revisao" ou data
            $table->decimal('percentual', 5, 2)->nullable();     // 0 a 100
            $table->text('comentarios')->nullable();
            $table->date('prazo_execucao')->nullable();

            $table->timestamps();

            $table->unique(['tenant_id', 'catalog_item_id']);
            $table->index(['tenant_id', 'catalog_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_items');
    }
};