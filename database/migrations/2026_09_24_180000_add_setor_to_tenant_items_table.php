<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_items', function (Blueprint $table) {
            $table->string('setor', 200)->nullable()->after('condicao_inicial');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropColumn('setor');
        });
    }
};
