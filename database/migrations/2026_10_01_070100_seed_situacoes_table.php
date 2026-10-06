<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Situações combinadas com o cliente: "Não avaliado" é o default.
     */
    public function up(): void
    {
        $default = [
            ['nome' => 'Não avaliado', 'ordem' => 1],
            ['nome' => 'Pendente', 'ordem' => 2],
            ['nome' => 'Conforme', 'ordem' => 3],
            ['nome' => 'Não conforme', 'ordem' => 4],
            ['nome' => 'Não adequado', 'ordem' => 5],
        ];

        $now = now();

        foreach ($default as $row) {
            DB::table('situacoes')->insert([
                'nome' => $row['nome'],
                'ordem' => $row['ordem'],
                'is_default' => $row['nome'] === 'Não avaliado',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('situacoes')->whereIn('nome', [
            'Não avaliado', 'Pendente', 'Conforme', 'Não conforme', 'Não adequado',
        ])->delete();
    }
};
