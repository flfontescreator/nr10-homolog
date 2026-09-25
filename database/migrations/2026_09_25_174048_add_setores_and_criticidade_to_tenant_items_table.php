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
            $table->json('setores')->nullable()->after('setor');
            $table->string('criticidade', 40)->nullable()->after('setor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_items', function (Blueprint $table) {
            $table->dropColumn(['setores', 'criticidade']);
        });
    }
};
