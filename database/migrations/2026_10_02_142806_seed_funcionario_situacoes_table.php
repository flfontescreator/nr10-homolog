<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Situações de vínculo do funcionário. "Ativo" é o default do cadastro;
     * "Inativo" é exibido em cinza na tela do funcionário.
     */
    public function up(): void
    {
        $now = now();

        DB::table('funcionario_situacoes')->insert([
            ['nome' => 'Ativo', 'slug' => 'ativo', 'ordem' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['nome' => 'Inativo', 'slug' => 'inativo', 'ordem' => 2, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        DB::table('funcionario_situacoes')->whereIn('slug', ['ativo', 'inativo'])->delete();
    }
};
