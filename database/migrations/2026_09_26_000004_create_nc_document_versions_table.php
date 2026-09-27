<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nc_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('nc_documents')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('selection');
            $table->string('summary')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['document_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nc_document_versions');
    }
};
