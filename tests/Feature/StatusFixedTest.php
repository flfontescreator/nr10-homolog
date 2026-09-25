<?php

namespace Tests\Feature;

use App\Enums\ItemStatus;
use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusFixedTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected TenantItem $item;

    protected User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Cliente Teste']);
        $this->manager = User::factory()->create([
            'role' => 'manager',
            'tenant_id' => $this->tenant->id,
        ]);

        $catalogItem = CatalogItem::create([
            'source' => Source::Cronograma->value,
            'code' => '10.1.1',
            'n1' => 10,
            'n2' => 1,
            'n3' => 1,
            'description' => 'Item de teste',
        ]);

        $this->item = TenantItem::create([
            'tenant_id' => $this->tenant->id,
            'catalog_item_id' => $catalogItem->id,
        ]);
    }

    public function test_accepts_any_fixed_status(): void
    {
        foreach (ItemStatus::cases() as $status) {
            $this->actingAs($this->manager)
                ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
                ->put(route('cronograma.update', $this->item), ['status' => $status->value])
                ->assertSessionHasNoErrors();

            $this->assertSame($status->value, $this->item->fresh()->status->value);
        }
    }

    public function test_rejects_invalid_status(): void
    {
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('cronograma.update', $this->item), ['status' => 'Atrasado'])
            ->assertSessionHasErrors('status');

        $this->assertNull($this->item->fresh()->status);
    }

    public function test_status_can_be_cleared(): void
    {
        $this->item->update(['status' => ItemStatus::Concluido]);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('cronograma.update', $this->item), ['status' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->item->fresh()->status);
    }

    public function test_enum_has_exactly_the_agreed_values(): void
    {
        $this->assertSame(
            ['Pendente', 'Em andamento', 'Concluído', 'Auditoria'],
            array_map(fn (ItemStatus $s) => $s->value, ItemStatus::cases()),
        );
    }
}
