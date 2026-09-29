<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\Role;
use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Funcionario;
use App\Models\NcDocument;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $itemF1), [
                'evidence' => UploadedFile::fake()->image('docs.png'),
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

    public function test_prontuario_index_shows_funcionario_subitems_under_item4(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Joana', 'matricula' => 'J1']);
        $funcionario->bootstrapProntuarioItems();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('prontuario.index'))
            ->assertOk()
            ->assertSee('Joana')
            ->assertSee('4.1')
            ->assertSee('Gerenciar funcionários');
    }

    public function test_document_selects_funcionario_item_for_section4_and_shows_badge(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Maria', 'matricula' => 'B1']);
        $funcionario->bootstrapProntuarioItems();
        $funcItem = $funcionario->prontuarioItems()->first();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['tenant_item_ids' => [$funcItem->id]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $entry = $document->items()->first();

        // O vínculo deve apontar para o sub-item do funcionário, não para um
        // registro genérico do tenant.
        $this->assertSame($funcItem->id, $entry->tenant_item_id);
        $this->assertSame($funcItem->catalog_item_id, $entry->catalog_item_id);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('nc-documents.show', $document))
            ->assertOk()
            ->assertSee('Func: Maria');
    }

    public function test_document_includes_same_subitem_for_two_employees(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $f1 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Marta', 'matricula' => 'E1']);
        $f1->bootstrapProntuarioItems();
        $f2 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Carla', 'matricula' => 'E2']);
        $f2->bootstrapProntuarioItems();

        $itemA = $f1->prontuarioItems()->whereHas('catalogItem', fn ($q) => $q->where('code', '4.1'))->first();
        $itemB = $f2->prontuarioItems()->whereHas('catalogItem', fn ($q) => $q->where('code', '4.1'))->first();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['tenant_item_ids' => [$itemA->id, $itemB->id]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame(2, $document->items()->count());
        $this->assertSame(2, $document->items()
            ->whereIn('tenant_item_id', [$itemA->id, $itemB->id])
            ->count());
    }

    public function test_funcionario_subitem_can_be_added_after_bootstrap(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Paulo', 'matricula' => 'C1']);
        $funcionario->bootstrapProntuarioItems();

        $this->assertSame(8, $funcionario->prontuarioItems()->count());

        // Catálogo ganhou um sub-item novo (4.9) — o funcionário pode adotá-lo.
        $extra = CatalogItem::create([
            'source' => Source::Prontuario->value,
            'code' => '4.9',
            'n1' => 4,
            'n2' => 9,
            'n3' => 0,
            'n4' => 0,
            'is_section' => false,
            'title' => 'Item 4.9',
            'description' => 'Item 4.9',
        ]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.subitems.store', $funcionario), ['catalog_item_id' => $extra->id])
            ->assertSessionHas('success');

        $this->assertSame(9, $funcionario->prontuarioItems()->count());
    }

    public function test_funcionario_subitem_can_be_deleted_without_links_and_evidences(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Rui', 'matricula' => 'D1']);
        $funcionario->bootstrapProntuarioItems();

        $item4 = $funcionario->items()
            ->whereHas('catalogItem', fn ($q) => $q->where('code', '4.3'))
            ->first();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('funcionarios.subitems.destroy', [$funcionario, $item4]))
            ->assertSessionHas('success');

        $this->assertNull($item4->fresh());
        $this->assertSame(7, $funcionario->prontuarioItems()->count());
    }

    public function test_funcionario_subitem_delete_blocked_when_linked_points_to_document(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Giga', 'matricula' => 'G1']);
        $funcionario->bootstrapProntuarioItems();
        $item4 = $funcionario->prontuarioItems()->first();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['tenant_item_ids' => [$item4->id]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $response = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('funcionarios.subitems.destroy', [$funcionario, $item4]))
            ->assertSessionHasErrors('subitem');

        $html = $response->getSession()->get('errors');
        $this->assertStringContainsString($document->code, $html->first('subitem'));
        $this->assertNotNull($item4->fresh());
    }

    public function test_funcionario_subitem_remap_blocked_when_linked_points_to_document(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Ana', 'matricula' => 'H1']);
        $funcionario->bootstrapProntuarioItems();
        $item4 = $funcionario->prontuarioItems()->first();
        $target = $funcionario->prontuarioItems()->get()->last();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['tenant_item_ids' => [$item4->id]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->put(route('funcionarios.subitems.update', [$funcionario, $item4]), ['catalog_item_id' => $target->catalog_item_id])
            ->assertSessionHasErrors('subitem')
            ->assertSessionHas('errors');

        $this->assertSame($item4->catalog_item_id, $item4->fresh()->catalog_item_id);
        $this->assertSame(1, $document->items()->count());
    }

    public function test_funcionario_deletion_blocked_when_subitem_linked_to_document(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $admin = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);
        $funcionario->bootstrapProntuarioItems();
        $item4 = $funcionario->prontuarioItems()->first();

        $document = NcDocument::create([
            'tenant_id' => $tenant->id,
            'number' => NcDocument::nextNumber($tenant->id),
            'code' => NcDocument::makeCode(NcDocument::nextNumber($tenant->id)),
            'title' => 'Doc de teste',
            'status' => DocumentStatus::Draft,
            'created_by' => $user->id,
        ]);
        $document->items()->create([
            'catalog_item_id' => $item4->catalog_item_id,
            'tenant_item_id' => $item4->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('funcionarios.destroy', $funcionario))
            ->assertSessionHasErrors('funcionario');

        $errors = session()->get('errors');
        $this->assertStringContainsString($document->code, $errors->first('funcionario'));

        $this->assertNotNull($funcionario->fresh());
    }

    public function test_evidence_on_funcionario_item_appears_in_documentos_with_traceability(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Rast', 'matricula' => 'T1']);
        $funcionario->bootstrapProntuarioItems();
        $item4 = $funcionario->prontuarioItems()->first();

        Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $item4), [
                'evidence' => UploadedFile::fake()->image('docs.png'),
            ])
            ->assertSessionHas('success');

        // RNC que contém o sub-item do funcionário (referência por sub-item).
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['tenant_item_ids' => [$item4->id]])
            ->assertRedirect();

        $html = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('docs.png', $html);
        $this->assertStringContainsString('Func: Rast', $html);
        $this->assertStringContainsString('RNC-00001', $html);
    }

    protected function makeTenantWithProntuario(): Tenant
    {
        $tenant = Tenant::create(['name' => 'Cliente Funcionarios']);

        foreach (['1', '2', '3', '4', '5'] as $section) {
            CatalogItem::create([
                'source' => Source::Prontuario->value,
                'code' => $section,
                'n1' => (int) $section,
                'n2' => 0,
                'n3' => 0,
                'n4' => 0,
                'is_section' => true,
                'title' => 'Item '.$section,
                'description' => 'Item '.$section,
            ]);
        }

        foreach (['1.1', '2.1', '3.1', '4.1', '4.2', '4.3', '4.4', '4.5', '4.6', '4.7', '4.8', '5.1'] as $code) {
            $parts = explode('.', $code);
            CatalogItem::create([
                'source' => Source::Prontuario->value,
                'code' => $code,
                'n1' => (int) $parts[0],
                'n2' => (int) $parts[1],
                'n3' => 0,
                'n4' => 0,
                'is_section' => false,
                'title' => 'Item '.$code,
                'description' => 'Item '.$code,
            ]);
        }

        $tenant->bootstrapItems();

        return $tenant;
    }
}
