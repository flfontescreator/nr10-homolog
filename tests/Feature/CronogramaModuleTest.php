<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use App\Support\CronogramaOptions;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronogramaModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $manager;

    protected TenantItem $item;

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

        $this->item = TenantItem::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'cronograma')->where('code', '10.3.1'))
            ->firstOrFail();
    }

    protected function actingAsManager(): self
    {
        return $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true]);
    }

    public function test_cronograma_index_lists_all_sections(): void
    {
        $this->actingAsManager()
            ->get(route('cronograma.index'))
            ->assertOk()
            ->assertSee('Cronograma de Adequação NR-10');
    }

    public function test_cronograma_show_renders_default_criticidade_and_setor_badges(): void
    {
        $this->actingAsManager()
            ->get(route('cronograma.show', $this->item))
            ->assertOk()
            ->assertSee('Campos de controle')
            ->assertSee('badge-setor', false)
            ->assertSee('Criticidade');
    }

    public function test_multiple_setores_are_saved(): void
    {
        $setores = CronogramaOptions::setores();

        $this->actingAsManager()
            ->put(route('cronograma.update', $this->item), [
                'setores' => [$setores[0], $setores[1]],
            ])
            ->assertSessionHas('success');

        $this->assertSame([$setores[0], $setores[1]], $this->item->fresh()->setores);
    }

    public function test_duplicated_setores_are_removed(): void
    {
        $setor = CronogramaOptions::setores()[0];

        $this->actingAsManager()
            ->put(route('cronograma.update', $this->item), [
                'setores' => [$setor, $setor],
            ])
            ->assertSessionHas('success');

        $this->assertSame([$setor], $this->item->fresh()->setores);
    }

    public function test_empty_setores_override_is_kept_empty(): void
    {
        $setor = CronogramaOptions::setores()[0];

        $this->item->update(['setores' => [$setor]]);

        $this->actingAsManager()
            ->put(route('cronograma.update', $this->item), [
                'setores' => [],
            ])
            ->assertSessionHas('success');

        $fresh = $this->item->fresh();

        $this->assertSame([], $fresh->setores);
        $this->assertSame([], $fresh->setores_list);
    }

    public function test_removing_all_badges_persists_zeroed_setores(): void
    {
        $setor = CronogramaOptions::setores()[0];

        $this->item->update(['setores' => [$setor]]);

        $this->actingAsManager()
            ->put(route('cronograma.update', $this->item), [])
            ->assertSessionHas('success');

        $fresh = $this->item->fresh();

        $this->assertSame([], $fresh->setores);
        $this->assertSame([], $fresh->setores_list);
    }

    public function test_catalog_splits_grouped_setores_into_individual_values(): void
    {
        $catalog = $this->item->catalogItem->fresh();

        $this->assertSame([
            'SESMT',
            'Segurança do Trabalho',
            'Engenharia',
            'Manutenção Elétrica',
        ], $catalog->setores_list);
    }

    public function test_bootstrap_leaves_setores_untouched_defaulting_to_catalog(): void
    {
        $fresh = $this->item->fresh();

        $this->assertNull($fresh->setores);
        $this->assertSame($fresh->catalogItem->setores_list, $fresh->setores_list);
    }

    public function test_untouched_item_follows_catalog_setores_live(): void
    {
        $this->item->catalogItem->update(['setores' => ['SESMT']]);

        $fresh = $this->item->fresh();

        $this->assertNull($fresh->setores);
        $this->assertSame(['SESMT'], $fresh->setores_list);
    }

    public function test_setores_options_cover_planilha_values(): void
    {
        $setores = CronogramaOptions::setores();

        $expected = [
            'SESMT', 'Segurança do Trabalho', 'Engenharia', 'Manutenção Elétrica',
            'Engenharia Elétrica', 'Operação', 'Manutenção', 'RH', 'Departamento Pessoal',
            'Saúde Ocupacional', 'PCMSO', 'Suprimentos', 'Compras', 'Gestão', 'Contratos',
            'Prestadores', 'Treinamento', 'Gestão Operacional', 'PLH – Profissional Legalmente Habilitado',
            'Supervisor', 'Trabalhador Autorizado', 'Responsável Técnico', 'Responsável pela Autorização',
            'Gestor da Atividade', 'Gestão de Pessoas', 'EPI', 'EPC', 'Metrologia', 'Qualidade',
            'Profissional Autorizado', 'Comissionamento', 'Organização Contratante', 'Almoxarifado',
            'Documentação',
        ];

        sort($expected);

        $this->assertSame($expected, $setores);
    }

    public function test_invalid_setor_is_rejected(): void
    {
        $this->actingAsManager()
            ->put(route('cronograma.update', $this->item), [
                'setores' => ['Setor inexistente'],
            ])
            ->assertSessionHasErrors('setores.0');
    }

    public function test_criticidade_can_be_set(): void
    {
        $this->actingAsManager()
            ->put(route('cronograma.update', $this->item), [
                'criticidade' => 'Não aplicada',
            ])
            ->assertSessionHas('success');

        $fresh = $this->item->fresh();

        $this->assertSame('Não aplicada', $fresh->criticidade);
        $this->assertSame('Não aplicada', $fresh->criticidade_atual);
    }

    public function test_criticidade_em_partes_can_be_set(): void
    {
        $this->actingAsManager()
            ->put(route('cronograma.update', $this->item), [
                'criticidade' => 'Em partes',
            ])
            ->assertSessionHas('success');

        $this->assertSame('Em partes', $this->item->fresh()->criticidade);
    }

    public function test_criticidade_falls_back_to_catalog_default(): void
    {
        $this->assertNull($this->item->criticidade);
        $this->assertSame($this->item->catalogItem->criticidade, $this->item->criticidade_atual);
    }

    public function test_invalid_criticidade_is_rejected(): void
    {
        $this->actingAsManager()
            ->put(route('cronograma.update', $this->item), [
                'criticidade' => 'Inexistente',
            ])
            ->assertSessionHasErrors('criticidade');
    }

    public function test_regular_update_still_works(): void
    {
        $this->actingAsManager()
            ->put(route('cronograma.update', $this->item), [
                'data_inspecao' => '2026-09-01',
                'condicao_inicial' => 'Não Adequada',
                'status' => 'Em andamento',
            ])
            ->assertSessionHas('success');

        $fresh = $this->item->fresh();

        $this->assertSame('2026-09-01', $fresh->data_inspecao->format('Y-m-d'));
        $this->assertSame('Não Adequada', $fresh->condicao_inicial);
        $this->assertSame('Em andamento', $fresh->status->value);
    }
}
