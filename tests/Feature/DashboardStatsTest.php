<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\ItemStatus;
use App\Models\CatalogItem;
use App\Models\NcDocument;
use App\Models\NcDocumentItem;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $manager;

    /** @var array<int, int> */
    protected array $subitemCatalogIds = [];

    protected int $sectionCatalogId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Cliente Teste']);
        $this->tenant->bootstrapItems();

        $this->manager = User::factory()->create([
            'role' => 'manager',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->subitemCatalogIds = CatalogItem::query()
            ->where('source', 'cronograma')
            ->where('is_section', false)
            ->orderBy('sort')
            ->limit(4)
            ->pluck('id')
            ->all();

        $this->sectionCatalogId = CatalogItem::query()
            ->where('source', 'cronograma')
            ->where('is_section', true)
            ->value('id');
    }

    protected function makeDocument(): NcDocument
    {
        return NcDocument::create([
            'tenant_id' => $this->tenant->id,
            'number' => NcDocument::nextNumber($this->tenant->id),
            'code' => NcDocument::makeCode(NcDocument::nextNumber($this->tenant->id)),
            'title' => 'Documento de teste',
            'status' => DocumentStatus::Draft,
            'created_by' => $this->manager->id,
        ]);
    }

    protected function tenantItemFor(int $catalogId): TenantItem
    {
        return TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $catalogId)
            ->firstOrFail();
    }

    protected function addItem(NcDocument $document, int $catalogIndex, array $attrs = []): NcDocumentItem
    {
        $catalogId = $this->subitemCatalogIds[$catalogIndex];

        return $document->items()->create(array_merge([
            'catalog_item_id' => $catalogId,
            'tenant_item_id' => $this->tenantItemFor($catalogId)->id,
        ], $attrs));
    }

    protected function addItemBySource(NcDocument $document, string $source, int $offset, array $attrs = []): NcDocumentItem
    {
        $catalogId = CatalogItem::query()
            ->where('source', $source)
            ->where('is_section', false)
            ->orderBy('sort')
            ->offset($offset)
            ->value('id');

        return $document->items()->create(array_merge([
            'catalog_item_id' => $catalogId,
            'tenant_item_id' => $this->tenantItemFor($catalogId)->id,
        ], $attrs));
    }

    public function test_dashboard_renders_new_layout_with_kpis_and_secondary_cards(): void
    {
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total de NCs')
            ->assertSee('% Conformidade')
            ->assertSee('Status das NCs')
            ->assertSee('NCs por criticidade')
            ->assertSee('Evolução da conformidade (6 meses)')
            ->assertSee('Alertas de prazo')
            ->assertSee('Previsões e riscos')
            ->assertSee('Indicadores do plano e da biblioteca')
            ->assertSee('Não conformidades por catálogo')
            ->assertSee('Pendências de adequação (pré-NC)')
            ->assertSee('Não conformidades ativas (prazo vencido)')
            ->assertSee('Subitens no Cronograma')
            ->assertSee('Evidências recentes')
            ->assertSee('js/dashboard.js', false);
    }

    public function test_stats_endpoint_returns_kpis_charts_and_fragments(): void
    {
        $document = $this->makeDocument();

        $this->addItem($document, 0, [
            'status' => ItemStatus::Concluido,
            'criticidade' => 'ALTA',
            'data_realizacao' => Carbon::today()->subDays(2)->toDateString(),
        ]);
        $this->addItem($document, 1, [
            'status' => ItemStatus::Pendente,
            'criticidade' => 'ALTA',
            'descricao_nc' => 'EPIs vencidos na área elétrica',
            'prazo_adequacao' => Carbon::today()->subDay()->toDateString(),
            'responsavel' => 'João Silva',
            'setores' => ['Manutenção Elétrica'],
        ]);
        $this->addItem($document, 2, [
            'status' => null,
            'criticidade' => 'MÉDIA',
            'prazo_adequacao' => Carbon::today()->addDays(60)->toDateString(),
        ]);

        $response = $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->getJson(route('dashboard.stats'));

        $response->assertOk()
            ->assertJsonPath('kpi.total', 3)
            ->assertJsonPath('kpi.conformidade', 33.3)
            ->assertJsonPath('kpi.atraso', 1)
            ->assertJsonPath('kpi.alta', 1)
            ->assertJsonPath('kpi.total_delta', null)
            ->assertJsonPath('charts.status.labels', ['Pendente', 'Em andamento', 'Auditoria', 'Atrasada', 'Concluído'])
            ->assertJsonPath('charts.status.values', [1, 0, 0, 1, 1])
            ->assertJsonPath('charts.criticidade.values', [1, 1, 0])
            ->assertJsonPath('previsao.risco7', 1)
            ->assertJsonStructure([
                'generated_at',
                'kpi',
                'charts' => ['status', 'criticidade', 'evolucao', 'prazos'],
                'previsao' => ['projecao30', 'projecao60', 'projecao90', 'risco7', 'tendencia'],
                'secundarios' => ['cronogramaTotal', 'prontuarioTotal', 'checklistTotal', 'evidenciasCount'],
                'ncs' => [
                    'pendencia' => ['total', 'normativa', 'operacional'],
                    'ativas' => ['total', 'normativa', 'operacional'],
                ],
                'html' => ['alertas', 'setores', 'responsaveis', 'evidencias'],
            ]);

        $this->assertStringContainsString('EPIs vencidos na área elétrica', $response->json('html.alertas'));
        $this->assertStringContainsString('Manutenção Elétrica', $response->json('html.setores'));
        $this->assertStringContainsString('João Silva', $response->json('html.responsaveis'));
        $this->assertStringContainsString('Nenhuma evidência anexada ainda.', $response->json('html.evidencias'));
    }

    public function test_stats_excludes_sections_and_other_tenants(): void
    {
        $document = $this->makeDocument();
        $this->addItem($document, 0);

        // Seção (capa) entra no documento SEM tenant_item — fora da contagem.
        $document->items()->create([
            'catalog_item_id' => $this->sectionCatalogId,
            'tenant_item_id' => null,
        ]);

        // Documento de OUTRO cliente não entra.
        $otherTenant = Tenant::create(['name' => 'Outro Cliente']);
        $otherTenant->bootstrapItems();
        $otherDoc = NcDocument::create([
            'tenant_id' => $otherTenant->id,
            'number' => 1,
            'code' => 'RNC-00001',
            'title' => 'Doc alheio',
            'status' => DocumentStatus::Draft,
            'created_by' => $this->manager->id,
        ]);
        $otherCatalogId = CatalogItem::query()
            ->where('source', 'cronograma')
            ->where('is_section', false)
            ->orderBy('sort')
            ->value('id');
        $otherDoc->items()->create([
            'catalog_item_id' => $otherCatalogId,
            'tenant_item_id' => TenantItem::withoutGlobalScopes()
                ->where('tenant_id', $otherTenant->id)
                ->where('catalog_item_id', $otherCatalogId)
                ->value('id'),
        ]);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->getJson(route('dashboard.stats'))
            ->assertOk()
            ->assertJsonPath('kpi.total', 1);
    }

    public function test_stats_blocks_count_pre_nc_and_active_nc_with_catalog_breakdown(): void
    {
        $document = $this->makeDocument();

        // Normativa: uma pré-NC (prazo em dia) e uma NC ativa (prazo vencido).
        $this->addItem($document, 0, ['prazo_adequacao' => Carbon::today()->addDays(30)->toDateString()]);
        $this->addItem($document, 1, ['prazo_adequacao' => Carbon::today()->subDay()->toDateString()]);

        // Operacional: uma pré-NC (sem prazo) e uma NC ativa (prazo vencido).
        $this->addItemBySource($document, 'prontuario', 0, ['prazo_adequacao' => null]);
        $this->addItemBySource($document, 'prontuario', 1, ['prazo_adequacao' => Carbon::today()->subDays(5)->toDateString()]);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->getJson(route('dashboard.stats'))
            ->assertOk()
            ->assertJsonPath('ncs.pendencia.total', 2)
            ->assertJsonPath('ncs.pendencia.normativa', 1)
            ->assertJsonPath('ncs.pendencia.operacional', 1)
            ->assertJsonPath('ncs.ativas.total', 2)
            ->assertJsonPath('ncs.ativas.normativa', 1)
            ->assertJsonPath('ncs.ativas.operacional', 1);
    }

    public function test_stats_alerts_are_ordered_by_deadline(): void
    {
        $document = $this->makeDocument();

        $this->addItem($document, 0, [
            'prazo_adequacao' => Carbon::today()->addDays(45)->toDateString(),
            'descricao_nc' => 'Prazo distante',
        ]);
        $this->addItem($document, 1, [
            'prazo_adequacao' => Carbon::today()->subDays(3)->toDateString(),
            'descricao_nc' => 'Prazo vencido primeiro',
        ]);

        $html = $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->getJson(route('dashboard.stats'))
            ->assertOk()
            ->json('html.alertas');

        $vencido = strpos($html, 'Prazo vencido primeiro');
        $distante = strpos($html, 'Prazo distante');

        $this->assertNotFalse($vencido);
        $this->assertNotFalse($distante);
        $this->assertLessThan($distante, $vencido);
    }

    public function test_stats_without_tenant_returns_404(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);

        $this->actingAs($superAdmin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('dashboard.stats'))
            ->assertNotFound();
    }

    public function test_dashboard_without_tenant_shows_platform_overview(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => null]);

        $this->actingAs($superAdmin)
            ->withSession(['two_step_verified' => true])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($this->tenant->name);
    }
}
