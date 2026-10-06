<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Uma linha por "Publicar". Cada revisão é IMUTÁVEL: guarda o snapshot
     * completo do relatório no momento da publicação, o Markdown gerado, o PDF
     * em disco e o link público (token + validade de 7 dias).
     */
    public function up(): void
    {
        Schema::create('rnc_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rnc_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');

            $table->string('label', 20);
            $table->json('snapshot');
            $table->longText('markdown');
            $table->string('pdf_path')->nullable();
            $table->text('notas')->nullable();

            $table->uuid('public_token')->nullable()->unique();
            $table->timestamp('public_expires_at')->nullable();

            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['rnc_id', 'revision']);
            $table->index(['tenant_id', 'public_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rnc_revisions');
    }
};
