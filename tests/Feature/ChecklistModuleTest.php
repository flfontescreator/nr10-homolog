<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Itens do catálogo com `source = checklist` (histórico legado). A tela de
 * Não Conformidades (`checklist.index`) virou o datagrid agregado — ver
 * `NaoConformidadeGridTest`.
 */
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

    public function test_every_catalog_item_has_a_tenant_row(): void
    {
        $checklistItems = TenantItem::query()
            ->where('tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'checklist')->where('is_section', false))
            ->count();

        $this->assertSame(166, $checklistItems);
    }

    public function test_catalog_splits_grouped_setores_into_individual_values(): void
    {
        $catalog = $this->item->catalogItem->fresh();

        $this->assertSame([
            'Manutenção Predial',
            'Manutenção Elétrica',
        ], $catalog->setores_list);
    }

    public function test_bootstrap_leaves_setores_untouched_defaulting_to_catalog(): void
    {
        $fresh = $this->item->fresh();

        $this->assertNull($fresh->setores);
        $this->assertSame($fresh->catalogItem->setores_list, $fresh->setores_list);
    }

    public function test_legacy_checklist_routes_were_removed(): void
    {
        $this->assertFalse(Route::has('checklist.show'));
        $this->assertFalse(Route::has('checklist.update'));
        $this->assertFalse(Route::has('checklist.evidencia.upload'));
        $this->assertFalse(Route::has('evidencia.destroy-checklist'));
        $this->assertTrue(Route::has('checklist.index'));
    }
}
