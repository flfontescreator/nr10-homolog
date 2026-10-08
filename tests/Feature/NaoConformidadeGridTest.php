<?php

namespace Tests\Feature;

use App\Enums\RncModelo;
use App\Enums\RncStatus;
use App\Enums\Role;
use App\Http\Controllers\NaoConformidadeController;
use App\Models\Rnc;
use App\Models\RncItem;
use App\Models\Situacao;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Datagrid de Não Conformidades (`checklist.index`): fonte RNC, somente
 * leitura, filtro padrão "Não conformidade". Regra de classificação única em
 * `NaoConformidadeController::classificacaoSql()` — este teste é a especificação
 * dela.
 */
class NaoConformidadeGridTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Cliente Grid']);
        $this->manager = User::factory()->create([
            'role' => Role::Manager,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_matrix_de_classificacao_com_filtro_padrao_e_todos(): void
    {
        $rnc = $this->makeRnc($this->tenant, ['data_inspecao' => '2026-06-15']);

        $naoConformidades = [
            'pendente' => $this->makeItem($rnc, ['situacao_id' => $this->situacao('Pendente')]),
            'nao_adequado' => $this->makeItem($rnc, ['situacao_id' => $this->situacao('Não adequado')]),
            'nao_conforme' => $this->makeItem($rnc, ['situacao_id' => $this->situacao('Não conforme')]),
            'vencido_sem_data' => $this->makeItem($rnc, [
                'situacao_id' => $this->situacao('Conforme'),
                'prazo_adequacao' => now()->subDay(),
            ]),
            'sem_prazo' => $this->makeItem($rnc, [
                'situacao_id' => $this->situacao('Conforme'),
                'prazo_adequacao' => null,
            ]),
            'sem_situacao' => $this->makeItem($rnc, ['situacao_id' => null]),
        ];

        $emConformidade = [
            'conforme_futuro' => $this->makeItem($rnc, [
                'situacao_id' => $this->situacao('Conforme'),
                'prazo_adequacao' => now()->addWeek(),
            ]),
            'vencido_adequado' => $this->makeItem($rnc, [
                'situacao_id' => $this->situacao('Conforme'),
                'prazo_adequacao' => now()->subDay(),
                'data_adequacao' => now()->subDays(3),
            ]),
            'nao_avaliado' => $this->makeItem($rnc, [
                'situacao_id' => $this->situacao('Não avaliado'),
                'prazo_adequacao' => now()->addWeek(),
            ]),
        ];

        // Filtro padrão (sem parâmetro): só as não conformidades.
        $resposta = $this->grid();
        foreach ($naoConformidades as $item) {
            $resposta->assertSee($this->label($item));
        }
        foreach ($emConformidade as $item) {
            $resposta->assertDontSee($this->label($item));
        }

        // classificacao= em conformidade: o grupo oposto.
        $resposta = $this->grid(['classificacao' => 'em_conformidade']);
        foreach ($emConformidade as $item) {
            $resposta->assertSee($this->label($item));
        }
        foreach ($naoConformidades as $item) {
            $resposta->assertDontSee($this->label($item));
        }

        // classificacao= (Todos): tudo aparece.
        $resposta = $this->grid(['classificacao' => '']);
        foreach ([...array_values($naoConformidades), ...array_values($emConformidade)] as $item) {
            $resposta->assertSee($this->label($item));
        }
    }

    public function test_tags_de_prazo_situacao_e_arquivada(): void
    {
        $rnc = $this->makeRnc($this->tenant);

        $this->makeItem($rnc, [
            'situacao_id' => $this->situacao('Conforme'),
            'prazo_adequacao' => null,
        ]);
        $this->makeItem($rnc, ['situacao_id' => null]);

        $arquivado = $this->makeRnc($this->tenant);
        $arquivado->update(['status' => RncStatus::Arquivado]);
        $this->makeItem($arquivado, ['situacao_id' => $this->situacao('Pendente')]);

        $this->grid()
            ->assertSee(NaoConformidadeController::TAG_SEM_PRAZO)
            ->assertSee(NaoConformidadeController::TAG_NAO_PREENCHIDA)
            ->assertSee(NaoConformidadeController::TAG_ARQUIVADA);
    }

    public function test_filtro_de_periodo_usa_a_data_de_inspecao(): void
    {
        $antigo = $this->makeRnc($this->tenant, ['data_inspecao' => '2026-01-10']);
        $itemAntigo = $this->makeItem($antigo, ['situacao_id' => $this->situacao('Pendente')]);

        $novo = $this->makeRnc($this->tenant, ['data_inspecao' => '2026-07-20']);
        $itemNovo = $this->makeItem($novo, ['situacao_id' => $this->situacao('Pendente')]);

        $this->grid(['de' => '2026-07-01'])
            ->assertSee($this->label($itemNovo))
            ->assertDontSee($this->label($itemAntigo));

        $this->grid(['ate' => '2026-02-01'])
            ->assertSee($this->label($itemAntigo))
            ->assertDontSee($this->label($itemNovo));
    }

    public function test_ordenacao_padao_e_descendente_e_ascendente_quando_pedida(): void
    {
        $primeiro = $this->makeRnc($this->tenant, ['data_inspecao' => '2026-01-10']);
        $itemAntigo = $this->makeItem($primeiro, ['situacao_id' => $this->situacao('Pendente')]);

        $segundo = $this->makeRnc($this->tenant, ['data_inspecao' => '2026-07-20']);
        $itemNovo = $this->makeItem($segundo, ['situacao_id' => $this->situacao('Pendente')]);

        // Padrão: mais recente primeiro.
        $this->assertBefore($this->grid(), $itemNovo, $itemAntigo);

        // ordem=asc: mais antiga primeiro.
        $this->assertBefore($this->grid(['ordem' => 'asc']), $itemAntigo, $itemNovo);
    }

    public function test_grid_e_visivel_para_visualizador_e_somente_leitura(): void
    {
        $viewer = User::factory()->create([
            'role' => Role::Viewer,
            'tenant_id' => $this->tenant->id,
        ]);

        $rnc = $this->makeRnc($this->tenant);
        $this->makeItem($rnc, ['situacao_id' => $this->situacao('Pendente')]);

        $this->como($viewer)
            ->get(route('checklist.index'))
            ->assertOk()
            ->assertDontSee('>Abrir</a>', false)
            ->assertDontSee('Novo Documento');

        $this->como($this->manager)
            ->get(route('checklist.index'))
            ->assertOk()
            ->assertSee('>Abrir</a>', false);
    }

    public function test_nc_de_outro_cliente_nao_aparece(): void
    {
        $outro = Tenant::create(['name' => 'Cliente Outro']);
        $rncOutro = $this->makeRnc($outro);
        $this->makeItem($rncOutro, [
            'titulo' => 'NC escondida de outro cliente',
            'situacao_id' => $this->situacao('Pendente'),
        ]);

        $this->grid()->assertDontSee('NC escondida de outro cliente');
    }

    public function test_rotas_do_checklist_legado_sumiram(): void
    {
        $this->como($this->manager)->get('/checklist/1')->assertNotFound();
        $this->como($this->manager)->put('/checklist/1', [])->assertNotFound();
        $this->como($this->manager)->post('/checklist/1/evidencias', [])->assertNotFound();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function grid(array $params = []): TestResponse
    {
        return $this->como($this->manager)
            ->get(route('checklist.index', $params));
    }

    private function como(User $user): static
    {
        return $this->actingAs($user)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true]);
    }

    private function assertBefore(TestResponse $resposta, RncItem $primeiro, RncItem $segundo): void
    {
        $html = $resposta->assertOk()->getContent();

        $posPrimeiro = strpos($html, $this->label($primeiro));
        $posSegundo = strpos($html, $this->label($segundo));

        $this->assertNotFalse($posPrimeiro, 'primeiro registro não encontrado no HTML');
        $this->assertNotFalse($posSegundo, 'segundo registro não encontrado no HTML');
        $this->assertLessThan($posSegundo, $posPrimeiro);
    }

    private function label(RncItem $item): string
    {
        return $item->rnc->code.' · NC '.$item->numero;
    }

    private function situacao(string $nome): int
    {
        return Situacao::query()->where('nome', $nome)->firstOrFail()->id;
    }

    private function makeRnc(Tenant $tenant, array $attrs = []): Rnc
    {
        $number = Rnc::nextNumber($tenant->id);

        return Rnc::create(array_merge([
            'tenant_id' => $tenant->id,
            'number' => $number,
            'code' => Rnc::makeCode($number),
            'titulo' => 'Relatório de inspeção',
            'modelo' => RncModelo::Tecnica,
            'responsavel_nome' => 'João da Silva',
            'responsavel_cargo' => 'Engenheiro Eletricista',
            'status' => RncStatus::Rascunho,
            'current_revision' => 0,
        ], $attrs));
    }

    private function makeItem(Rnc $rnc, array $attrs = []): RncItem
    {
        $numero = $rnc->nextItemNumber();

        return $rnc->items()->create(array_merge([
            'tenant_id' => $rnc->tenant_id,
            'numero' => $numero,
            'titulo' => 'Condição encontrada NC '.$numero,
            'descricao' => 'Condição encontrada em campo.',
            'prazo_adequacao' => now()->addWeek(),
        ], $attrs));
    }
}
