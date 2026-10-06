<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            // Descrição do arquivo — obrigatória no upload.
            $table->text('description')->nullable()->after('original_name');

            // Validade do ARQUIVO: opcional, depende do tipo de documento
            // anexado (nem todo documento tem validade).
            $table->date('validade')->nullable()->after('description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('evidences', function (Blueprint $table) {
            $table->dropColumn(['description', 'validade']);
        });
    }
};
