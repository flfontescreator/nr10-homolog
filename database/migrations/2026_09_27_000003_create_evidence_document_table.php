<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Biblioteca de documentos: um arquivo (evidência) pode estar vinculado a
     * VÁRIOS documentos DN, e um documento pode referenciar VÁRIOS arquivos.
     * O vínculo é com o DOCUMENTO (badges "Documento de referência"); o item do
     * documento (evidences.nc_document_item_id) continua sendo o contexto de
     * onde o arquivo foi anexado dentro de um trabalho de subitem.
     */
    public function up(): void
    {
        Schema::create('evidence_document', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('nc_documents')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['evidence_id', 'document_id'], 'evidence_document_unique');
            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_document');
    }
};
