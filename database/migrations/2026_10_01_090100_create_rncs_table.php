<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * RNC = relatório formal de não conformidade, MÓDULO NOVO e independente de
     * "Não Conformidades" (`nc_documents`). Numeração `RNC_0001` por tenant.
     * A revisão só nasce em "Publicar" (marco oficial) — ver `rnc_revisions`.
     */
    public function up(): void
    {
        Schema::create('rncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('number');
            $table->string('code', 30);

            // Cabeçalho do relatório
            $table->string('titulo');
            $table->text('descricao')->nullable();
            $table->string('obra_unidade')->nullable();
            $table->date('data_emissao')->nullable();
            $table->string('responsavel_nome')->nullable();
            $table->string('responsavel_cargo')->nullable();

            $table->foreignId('rnc_type_id')->nullable()->constrained('rnc_types')->nullOnDelete();
            $table->foreignId('norma_tecnica_id')->nullable()->constrained('normas_tecnicas')->nullOnDelete();

            // Estado: rascunho é livre; "publicado" congela a revisão atual.
            $table->string('status', 20)->default('rascunho');
            $table->unsignedInteger('current_revision')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'number']);
            $table->unique(['tenant_id', 'code']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rncs');
    }
};
