<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use App\Support\ChecklistOptions;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistModuleTest extends TestCase
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
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'checklist')->where('code', '1'))
            ->firstOrFail();
    }

    public function test_checklist_index_lists_documents(): void
    {
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('checklist.index'))
            ->assertOk()
            ->assertSee('Não Conformidades')
            ->assertSee('Novo Documento');
    }

    public function test_every_catalog_item_has_a_tenant_row(): void
    {
        $checklistItems = TenantItem::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'checklist')->where('is_section', false))
            ->count();

        $this->assertSame(166, $checklistItems);
    }

    public function test_checklist_item_can_be_updated(): void
    {
        $item = TenantItem::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'checklist')->where('code', '1'))
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('checklist.update', $item), [
                'data_inspecao' => '2026-09-01',
                'id_relatorio' => 'RNC_010',
                'status' => 'Em andamento',
            ])
            ->assertSessionHas('success');

        $this->assertSame('2026-09-01', $item->fresh()->data_inspecao->format('Y-m-d'));
        $this->assertSame('RNC_010', $item->fresh()->id_relatorio);
        $this->assertSame('Em andamento', $item->fresh()->status->value);
    }

    public function test_checklist_show_renders_for_tenant_item(): void
    {
        $item = TenantItem::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'checklist')->where('code', '2'))
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('checklist.show', $item))
            ->assertOk();
    }

    public function test_checklist_options_setores_are_the_15_canonical_values(): void
    {
        $this->assertSame([
            'Engenharia',
            'Engenharia Elétrica',
            'Empresa Especializada',
            'Facilities',
            'Manutenção',
            'Manutenção Elétrica',
            'Manutenção Predial',
            'Mecânica',
            'Meio Ambiente',
            'QSMS',
            'Responsáveis pela Emissão de PT',
            'Serviços Gerais',
            'Segurança do Trabalho',
            'Supervisão da Manutenção',
            'Supervisão Operacional',
        ], ChecklistOptions::setores());
    }

    public function test_catalog_splits_grouped_setores_into_individual_values(): void
    {
        $catalog = $this->item->catalogItem->fresh();

        $this->assertSame([
            'Manutenção Predial',
            'Manutenção Elétrica',
        ], $catalog->setores_list);
    }

    public function test_bootstrap_copies_catalog_setores_to_tenant_item(): void
    {
        $this->assertSame($this->item->catalogItem->setores, $this->item->fresh()->setores);
    }

    public function test_multiple_setores_are_saved(): void
    {
        $setores = ChecklistOptions::setores();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('checklist.update', $this->item), [
                'setores' => [$setores[0], $setores[1]],
            ])
            ->assertSessionHas('success');

        $this->assertSame([$setores[0], $setores[1]], $this->item->fresh()->setores);
    }

    public function test_empty_setores_clears_override_and_falls_back_to_catalog(): void
    {
        $setor = ChecklistOptions::setores()[0];

        $this->item->update(['setores' => [$setor]]);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('checklist.update', $this->item), [
                'setores' => [],
            ])
            ->assertSessionHas('success');

        $fresh = $this->item->fresh();

        $this->assertNull($fresh->setores);
        $this->assertSame($fresh->catalogItem->setores_list, $fresh->setores_list);
    }

    public function test_invalid_setor_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('checklist.update', $this->item), [
                'setores' => ['Setor inexistente'],
            ])
            ->assertSessionHasErrors('setores.0');
    }
}
