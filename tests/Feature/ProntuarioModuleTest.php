<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProntuarioModuleTest extends TestCase
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
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'prontuario')->where('code', '1.1'))
            ->firstOrFail();
    }

    protected function actingAsManager(): self
    {
        return $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true]);
    }

    public function test_prontuario_show_renders_data_validade_as_date_input(): void
    {
        $this->actingAsManager()
            ->get(route('prontuario.show', $this->item))
            ->assertOk()
            ->assertSee('name="data_validade"', false)
            ->assertSee('type="date"', false);
    }

    public function test_valid_data_validade_is_saved_as_date(): void
    {
        $this->actingAsManager()
            ->put(route('prontuario.update', $this->item), [
                'data_validade' => '2026-12-31',
            ])
            ->assertSessionHas('success');

        $this->assertSame('2026-12-31', $this->item->fresh()->data_validade?->format('Y-m-d'));
    }

    public function test_empty_data_validade_clears_the_field(): void
    {
        $this->item->update(['data_validade' => '2026-12-31']);

        $this->actingAsManager()
            ->put(route('prontuario.update', $this->item), [
                'data_validade' => '',
            ])
            ->assertSessionHas('success');

        $this->assertNull($this->item->fresh()->data_validade);
    }

    public function test_invalid_data_validade_is_rejected(): void
    {
        $this->actingAsManager()
            ->put(route('prontuario.update', $this->item), [
                'data_validade' => 'Conforme Revisão',
            ])
            ->assertSessionHasErrors('data_validade');

        $this->assertNull($this->item->fresh()->data_validade);
    }

    public function test_prontuario_index_formats_data_validade_as_dmy(): void
    {
        $this->item->update(['data_validade' => '2026-12-31']);

        $this->actingAsManager()
            ->get(route('prontuario.index'))
            ->assertOk()
            ->assertSee('31/12/2026');
    }

    public function test_prontuario_show_renders_condicao_inicial_select(): void
    {
        $this->actingAsManager()
            ->get(route('prontuario.show', $this->item))
            ->assertOk()
            ->assertSee('name="condicao_inicial"', false)
            ->assertSee('<option value="">-</option>', false);
    }

    public function test_valid_condicao_inicial_is_saved(): void
    {
        $this->actingAsManager()
            ->put(route('prontuario.update', $this->item), [
                'condicao_inicial' => 'Adequado',
            ])
            ->assertSessionHas('success');

        $this->assertSame('Adequado', $this->item->fresh()->condicao_inicial);
    }

    public function test_legacy_condicao_inicial_values_are_rejected(): void
    {
        $this->actingAsManager()
            ->put(route('prontuario.update', $this->item), [
                'condicao_inicial' => 'Não Adequada',
            ])
            ->assertSessionHasErrors('condicao_inicial');

        $this->assertNull($this->item->fresh()->condicao_inicial);
    }

    public function test_prontuario_index_renders_condicao_inicial(): void
    {
        $this->item->update(['condicao_inicial' => 'Não avaliado']);

        $this->actingAsManager()
            ->get(route('prontuario.index'))
            ->assertOk()
            ->assertSee('Não avaliado');
    }

    public function test_prontuario_show_renders_criticidade_select(): void
    {
        $this->actingAsManager()
            ->get(route('prontuario.show', $this->item))
            ->assertOk()
            ->assertSee('name="criticidade"', false)
            ->assertSee('ALTA')
            ->assertSee('MÉDIA')
            ->assertSee('BAIXA');
    }

    public function test_valid_criticidade_is_saved_following_normativa_pattern(): void
    {
        $this->actingAsManager()
            ->put(route('prontuario.update', $this->item), [
                'criticidade' => 'ALTA',
            ])
            ->assertSessionHas('success');

        $this->assertSame('ALTA', $this->item->fresh()->criticidade);
    }

    public function test_criticidade_atual_falls_back_to_catalog_criticidade(): void
    {
        $this->item->catalogItem->update(['criticidade' => 'MÉDIA']);

        $this->assertSame('MÉDIA', $this->item->fresh()->criticidade_atual);
    }

    public function test_invalid_criticidade_is_rejected(): void
    {
        $this->actingAsManager()
            ->put(route('prontuario.update', $this->item), [
                'criticidade' => 'Crítica Absoluta',
            ])
            ->assertSessionHasErrors('criticidade');

        $this->assertNull($this->item->fresh()->criticidade);
    }
}
