<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Funcionario;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuncionarioModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_funcionario_bootstraps_the_8_item4_subitems(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), [
                'nome' => 'João da Silva',
                'matricula' => 'F100',
            ])
            ->assertRedirect();

        $funcionario = Funcionario::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($funcionario);

        $codes = $funcionario->items()
            ->with('catalogItem')
            ->get()
            ->map(fn ($item) => $item->catalogItem->code)
            ->values();

        $this->assertSame(['4.1', '4.2', '4.3', '4.4', '4.5', '4.6', '4.7', '4.8'], $codes->all());
    }

    public function test_viewer_cannot_create_funcionario(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Viewer, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), ['nome' => 'Sem permissão'])
            ->assertForbidden();

        $this->assertDatabaseCount('funcionarios', 0);
    }

    public function test_matricula_unique_per_tenant(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $otherTenant = Tenant::create(['name' => 'Cliente B']);
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'A', 'matricula' => 'X1']);
        Funcionario::create(['tenant_id' => $otherTenant->id, 'nome' => 'B', 'matricula' => 'X1']);

        // Mesma matricula em outro tenant é aceita.
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), ['nome' => 'Duplicado mesmo tenant', 'matricula' => 'X1'])
            ->assertSessionHasErrors('matricula');
    }

    public function test_funcionario_items_accept_evidences_independently(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $f1 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F1', 'matricula' => 'A1']);
        $f2 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F2', 'matricula' => 'A2']);
        $f1->bootstrapProntuarioItems();
        $f2->bootstrapProntuarioItems();

        $itemF1 = $f1->items()->whereHas('catalogItem', fn ($q) => $q->where('code', '4.1'))->first();
        $itemF2 = $f2->items()->whereHas('catalogItem', fn ($q) => $q->where('code', '4.1'))->first();

        \Illuminate\Support\Facades\Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $itemF1), [
                'evidence' => \Illuminate\Http\UploadedFile::fake()->image('docs.png'),
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, $itemF1->evidences()->count());
        $this->assertSame(0, $itemF2->evidences()->count());
    }

    public function test_prontuario_index_hides_item4_subitems_for_tenant(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        // Itens 4.x NÃO devem nascer por tenant.
        $tenantLevelItem4 = TenantItem::query()
            ->whereNull('funcionario_id')
            ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Prontuario->value)->where('n1', 4))
            ->count();

        $this->assertSame(0, $tenantLevelItem4);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('prontuario.index'))
            ->assertOk()
            ->assertSee('Funcionários');
    }

    protected function makeTenantWithProntuario(): Tenant
    {
        $tenant = Tenant::create(['name' => 'Cliente Funcionarios']);

        foreach (['1.1', '2.1', '3.1', '4.1', '4.2', '4.3', '4.4', '4.5', '4.6', '4.7', '4.8', '5.1'] as $i => $code) {
            $parts = explode('.', $code);
            CatalogItem::create([
                'source' => Source::Prontuario->value,
                'code' => $code,
                'n1' => (int) $parts[0],
                'n2' => (int) $parts[1],
                'n3' => 0,
                'n4' => 0,
                'is_section' => in_array($code, ['1', '2', '3', '4', '5'], true),
                'title' => 'Item '.$code,
                'description' => 'Item '.$code,
            ]);
        }

        $tenant->bootstrapItems();

        return $tenant;
    }
}