<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Endereço estruturado do cliente. `address` continua guardando o logradouro
     * (e, em cadastros antigos, o endereço completo montado à mão) — a string
     * exibida é montada por Tenant::enderecoCompleto().
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('cep', 9)->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 120)->nullable();
            $table->string('bairro', 120)->nullable();
            $table->string('cidade', 120)->nullable();
            $table->char('uf', 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['cep', 'numero', 'complemento', 'bairro', 'cidade', 'uf']);
        });
    }
};
