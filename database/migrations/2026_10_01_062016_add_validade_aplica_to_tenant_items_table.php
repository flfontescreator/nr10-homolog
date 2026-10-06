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
        Schema::table('tenant_items', function (Blueprint $table) {
            // Checkbox "Se aplica" do campo de validade: desmarcado por padrão
            // (campo desabilitado). Desmarcado => data_validade é zerado no
            // controller, então o checkbox é a fonte da verdade do campo.
            $table->boolean('validade_aplica')->default(false)->after('data_validade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropColumn('validade_aplica');
        });
    }
};
