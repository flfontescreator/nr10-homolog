<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reuso da biblioteca POR SUB-ITEM (Opção B, Fase 7): o contexto de anexo
     * único (evidences.nc_document_item_id) vira pivô N:N evidência <-> sub-item
     * do documento, e o plano ganha pivô N:N evidência <-> tenant_item. Assim o
     * MESMO arquivo pode ficar em vários sub-itens, de um ou vários documentos,
     * e em sub-itens do cronograma, sem cópia. tenant_item_id (evidences) segue
     * como ÂNCORA/origem (não é vínculo de card).
     */
    public function up(): void
    {
        Schema::create('evidence_document_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->foreignId('nc_document_item_id')->constrained('nc_document_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['evidence_id', 'nc_document_item_id'], 'evidence_document_item_unique');
            $table->index('nc_document_item_id');
        });

        Schema::create('evidence_tenant_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('evidence_id')->constrained('evidences')->cascadeOnDelete();
            $table->foreignId('tenant_item_id')->constrained('tenant_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['evidence_id', 'tenant_item_id'], 'evidence_tenant_item_unique');
            $table->index('tenant_item_id');
        });

        // O anexo antigo por documento migra para o pivô de sub-item.
        DB::table('evidences')
            ->whereNotNull('nc_document_item_id')
            ->select('tenant_id', 'id', 'nc_document_item_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('evidence_document_item')->insert([
                        'tenant_id' => $row->tenant_id,
                        'evidence_id' => $row->id,
                        'nc_document_item_id' => $row->nc_document_item_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // Arquivos do plano (âncora sem documento) ganham o vínculo EXPLÍCITO de
        // card no plano; a âncora passa a ser apenas a origem.
        DB::table('evidences')
            ->whereNull('nc_document_item_id')
            ->select('tenant_id', 'id', 'tenant_item_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('evidence_tenant_item')->insert([
                        'tenant_id' => $row->tenant_id,
                        'evidence_id' => $row->id,
                        'tenant_item_id' => $row->tenant_item_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

        // A coluna única vira pivô: removida após migrar os dados. No MySQL o
        // índice da FK é necessário para dropar a FK, então a ordem importa.
        Schema::table('evidences', function (Blueprint $table) {
            $table->dropForeign(['nc_document_item_id']);
        });

        Schema::table('evidences', function (Blueprint $table) {
            if (Schema::hasIndex('evidences', ['nc_document_item_id'])) {
                $table->dropIndex(['nc_document_item_id']);
            }

            $table->dropColumn('nc_document_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->foreignId('nc_document_item_id')->nullable()->after('tenant_item_id')->constrained('nc_document_items')->cascadeOnDelete();
            $table->index('nc_document_item_id');
        });

        // Best effort: restaura um sub-item por evidência (o primeiro pivô de documento).
        DB::table('evidence_document_item')
            ->orderBy('id')
            ->get()
            ->each(function ($row) {
                DB::table('evidences')
                    ->where('id', $row->evidence_id)
                    ->whereNull('nc_document_item_id')
                    ->update(['nc_document_item_id' => $row->nc_document_item_id]);
            });

        Schema::dropIfExists('evidence_tenant_item');
        Schema::dropIfExists('evidence_document_item');
    }
};
