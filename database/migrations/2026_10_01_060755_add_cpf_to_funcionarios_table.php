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
        Schema::table('funcionarios', function (Blueprint $table) {
            // Só dígitos (11) — a máscara é aplicada na entrada e o CPF é
            // gravado normalizado para validar/unique de forma confiável.
            $table->string('cpf', 11)->nullable()->after('matricula');

            // Único por cliente: dois funcionários do mesmo tenant não
            // compartilham CPF (CPIs de outro tenant podem se repetir).
            $table->unique(['tenant_id', 'cpf']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('funcionarios', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'cpf']);
            $table->dropColumn('cpf');
        });
    }
};
