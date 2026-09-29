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
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->text('norma_tecnica')->nullable()->after('description');
            $table->text('interpretacao_tecnica')->nullable()->after('norma_tecnica');
            $table->text('sugestao_acao')->nullable()->after('interpretacao_tecnica');
            $table->string('status', 32)->nullable()->after('sugestao_acao');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn([
                'norma_tecnica', 'interpretacao_tecnica', 'sugestao_acao', 'status',
            ]);
        });
    }
};
