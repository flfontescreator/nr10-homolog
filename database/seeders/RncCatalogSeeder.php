<?php

namespace Database\Seeders;

use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\ClassificacaoRisco;
use App\Models\Criticidade;
use App\Models\NormaItem;
use App\Models\NormaTecnica;
use App\Models\Projeto;
use Illuminate\Database\Seeder;

class RncCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedProjetos();
        $this->seedCriticidades();
        $this->seedClassificacoesRisco();
        $this->seedNormas();
        $this->seedNormaItens();
        $this->seedNormaItensNbr5410();
    }

    protected function seedProjetos(): void
    {
        Projeto::updateOrCreate(
            ['nome' => 'Consultoria NR-10'],
            ['ativo' => true]
        );
    }

    protected function seedCriticidades(): void
    {
        $criticidades = [
            ['nome' => 'Alta', 'ordem' => 1],
            ['nome' => 'Média', 'ordem' => 2],
            ['nome' => 'Baixa', 'ordem' => 3],
            ['nome' => 'Não aplicada', 'ordem' => 4],
        ];

        foreach ($criticidades as $criticidade) {
            Criticidade::updateOrCreate(['nome' => $criticidade['nome']], $criticidade);
        }
    }

    protected function seedClassificacoesRisco(): void
    {
        $classificacoes = [
            ['nome' => 'Alto', 'ordem' => 1],
            ['nome' => 'Médio', 'ordem' => 2],
            ['nome' => 'Baixo', 'ordem' => 3],
        ];

        foreach ($classificacoes as $classificacao) {
            ClassificacaoRisco::updateOrCreate(['nome' => $classificacao['nome']], $classificacao);
        }
    }

    protected function seedNormas(): void
    {
        $normas = [
            ['codigo' => 'NR-10', 'nome' => 'Segurança em Instalações e Serviços em Eletricidade', 'descricao' => 'Norma regulamentadora que estabelece requisitos de segurança para instalações e serviços em eletricidade.'],
            ['codigo' => 'NR-35', 'nome' => 'Trabalho em Altura', 'descricao' => 'Requisitos de segurança para trabalho em altura.'],
            ['codigo' => 'NR-12', 'nome' => 'Máquinas e Equipamentos', 'descricao' => 'Segurança no trabalho em máquinas e equipamentos.'],
            ['codigo' => 'NR-01', 'nome' => 'Disposições Gerais e Gerenciamento de Riscos Ocupacionais', 'descricao' => 'Regras gerais de segurança e saúde do trabalho e gerenciamento de riscos.'],
            ['codigo' => 'NBR 5410', 'nome' => 'Instalações Elétricas de Baixa Tensão', 'descricao' => 'Norma técnica de instalações elétricas de baixa tensão.'],
            ['codigo' => 'NBR 5419', 'nome' => 'Proteção contra Sobretensões', 'descricao' => 'Norma técnica de proteção contra surtos e sobretensões.'],
            ['codigo' => 'NBR IEC 60529', 'nome' => 'Graus de Proteção (IP)', 'descricao' => 'Graus de proteção fornecidos por invólucros (código IP).'],
        ];

        foreach ($normas as $norma) {
            $registro = NormaTecnica::firstOrNew(['codigo' => $norma['codigo']]);
            $registro->fill($norma);
            $registro->ativo = true;
            $registro->save();
        }
    }

    /**
     * Itens da NR-10 vêm da matriz do `catalog_items` (source=cronograma): o
     * campo `norma_tecnica` guarda o texto com o código inicial (ex.: "10.3.1
     * No processo..."). Cada subitem vira um `NormaItem` da NR-10, pronto para
     * ser referenciado pelas não conformidades.
     */
    protected function seedNormaItens(): void
    {
        $nr10 = NormaTecnica::where('codigo', 'NR-10')->first();

        if (! $nr10) {
            return;
        }

        $itens = CatalogItem::query()
            ->where('source', Source::Cronograma->value)
            ->where('is_section', false)
            ->whereNotNull('norma_tecnica')
            ->orderBy('n1')
            ->orderBy('n2')
            ->orderBy('n3')
            ->orderBy('n4')
            ->get();

        $ordem = 0;

        foreach ($itens as $item) {
            $norma = trim((string) $item->norma_tecnica);

            if ($norma === '' || ! preg_match('/^([\d]+(?:\.[\d]+)*)\s*(.*)$/su', $norma, $m)) {
                continue;
            }

            $ordem++;

            NormaItem::updateOrCreate(
                ['norma_tecnica_id' => $nr10->id, 'codigo' => $m[1]],
                [
                    'descricao' => trim(preg_replace('/\s+/u', ' ', $m[2]) ?? ''),
                    'ordem' => $ordem,
                ]
            );
        }
    }

    /**
     * Itens da ABNT NBR 5410 vêm do CSV versionado em database/seeders/data/
     * nbr5410.csv (colunas codigo,titulo): sumário das cláusulas 1..9 em até 3
     * níveis. Idempotente — reexecutar só atualiza descrição/ordem.
     */
    protected function seedNormaItensNbr5410(): void
    {
        $nbr = NormaTecnica::where('codigo', 'NBR 5410')->first();
        $arquivo = database_path('seeders/data/nbr5410.csv');

        if ($nbr === null || ! is_file($arquivo)) {
            return;
        }

        $ordem = 0;

        foreach (CatalogSeeder::csvRows($arquivo) as $linha) {
            $codigo = trim((string) ($linha[0] ?? ''));
            $titulo = trim(preg_replace('/\s+/u', ' ', (string) ($linha[1] ?? '')) ?? '');

            if ($codigo === 'codigo') {
                continue;
            }

            if ($codigo === '' || $titulo === '') {
                continue;
            }

            $ordem++;

            NormaItem::updateOrCreate(
                ['norma_tecnica_id' => $nbr->id, 'codigo' => $codigo],
                ['descricao' => $titulo, 'ordem' => $ordem]
            );
        }
    }
}
