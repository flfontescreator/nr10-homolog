<?php

namespace Database\Seeders;

use App\Models\Cidade;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Base de cidades e bairros (dado público IBGE/Correios) importada de
 * storage/app/imports/cidades.csv e bairros.csv — os mesmos CSVs não
 * versionados do catálogo. A importação é idempotente: o que já existe
 * é ignorado pela chave única.
 *
 * Quando os arquivos não estão na máquina (cópia nova do repositório), o
 * seeding apenas avisa: a base também cresce sozinha a cada busca de CEP
 * feita no cadastro do cliente.
 */
class LocalidadeSeeder extends Seeder
{
    public function run(): void
    {
        $this->importCidades();
        $this->importBairros();
    }

    protected function importCidades(): void
    {
        $now = now();
        $registros = [];

        foreach ($this->linhas('cidades.csv') as $row) {
            $uf = mb_strtoupper(trim((string) ($row[0] ?? '')));
            $nome = trim((string) ($row[1] ?? ''));

            if ($uf === '' || $nome === '' || ! array_key_exists($uf, Cidade::UFS)) {
                continue;
            }

            $registros[] = [
                'uf' => $uf,
                'nome' => $nome,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->gravar('cidades', $registros);
    }

    protected function importBairros(): void
    {
        $now = now();
        $registros = [];

        foreach ($this->linhas('bairros.csv') as $row) {
            $uf = mb_strtoupper(trim((string) ($row[0] ?? '')));
            $cidade = trim((string) ($row[1] ?? ''));
            $nome = trim((string) ($row[2] ?? ''));

            if ($uf === '' || $cidade === '' || $nome === '' || ! array_key_exists($uf, Cidade::UFS)) {
                continue;
            }

            $registros[] = [
                'uf' => $uf,
                'cidade' => $cidade,
                'nome' => $nome,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $this->gravar('bairros', $registros);
    }

    protected function gravar(string $tabela, array $registros): void
    {
        if ($registros === []) {
            return;
        }

        foreach (array_chunk($registros, 500) as $lote) {
            DB::table($tabela)->insertOrIgnore($lote);
        }

        $this->command?->info(count($registros)." linhas de {$tabela} consideradas.");
    }

    /**
     * @return array<int, array<int, string>>
     */
    protected function linhas(string $arquivo): array
    {
        $path = storage_path('app/imports/'.$arquivo);

        if (! is_file($path)) {
            $this->command?->warn("{$arquivo} não encontrado em storage/app/imports — localidades não importadas.");

            return [];
        }

        return CatalogSeeder::csvRows($path);
    }
}
