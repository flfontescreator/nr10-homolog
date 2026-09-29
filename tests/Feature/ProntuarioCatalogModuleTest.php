<?php

namespace Tests\Feature;

use App\Models\CatalogItem;
use App\Models\NcDocument;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProntuarioCatalogModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $tenantB;

    protected User $manager;

    protected User $admin;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);

        $this->tenant = Tenant::create(['name' => 'Cliente Teste']);
        $this->tenant->bootstrapItems();

        $this->tenantB = Tenant::create(['name' => 'Cliente B']);
        $this->tenantB->bootstrapItems();

        $this->manager = User::factory()->create([
            'role' => 'manager',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->viewer = User::factory()->create([
            'role' => 'viewer',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function actingAsUser(User $user): self
    {
        return $this->actingAs($user)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true]);
    }

    protected function prontuarioSection(int $n1): CatalogItem
    {
        return CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', true)
            ->where('n1', $n1)
            ->firstOrFail();
    }

    public function test_catalog_index_lists_sections_and_subitems(): void
    {
        $this->actingAsUser($this->manager)
            ->get(route('prontuario.catalogo.index'))
            ->assertOk()
            ->assertSee('name="section_id"', false)
            ->assertSee('1.1');
    }

    public function test_section_can_be_created_with_next_numbering(): void
    {
        $last = (int) CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', true)
            ->max('n1');

        $this->actingAsUser($this->manager)
            ->post(route('prontuario.catalogo.section.store'), ['title' => 'Nova seção'])
            ->assertRedirect(route('prontuario.catalogo.index'))
            ->assertSessionHas('success');

        $item = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('code', (string) ($last + 1))
            ->where('is_section', true)
            ->firstOrFail();

        $this->assertSame('Nova seção', $item->title);
    }

    public function test_subitem_can_be_created_with_operational_numbering(): void
    {
        $section = $this->prontuarioSection(1);

        $lastN2 = (int) CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('n1', 1)
            ->where('is_section', false)
            ->max('n2');

        $this->actingAsUser($this->manager)
            ->post(route('prontuario.catalogo.item.store'), [
                'section_id' => $section->id,
                'title' => 'Procedimento novo',
            ])
            ->assertRedirect(route('prontuario.catalogo.index'))
            ->assertSessionHas('success');

        $item = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('code', '1.'.($lastN2 + 1))
            ->firstOrFail();

        $this->assertSame('Procedimento novo', $item->title);
        $this->assertSame($section->id, $item->parent_id);
        $this->assertFalse($item->is_section);

        // Invariante do catálogo: sub-item novo tem tenant_items em todos os clientes.
        $this->assertSame(Tenant::query()->count(), TenantItem::withoutGlobalScopes()
            ->where('catalog_item_id', $item->id)
            ->count());
    }

    public function test_deleting_subitem_renumbers_remaining_subitems(): void
    {
        $second = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('n1', 1)
            ->where('is_section', false)
            ->where('n2', 2)
            ->firstOrFail();
        $last = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('n1', 1)
            ->where('is_section', false)
            ->where('n2', 11)
            ->firstOrFail();

        $first = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('n1', 1)
            ->where('is_section', false)
            ->where('n2', 1)
            ->firstOrFail();

        $this->actingAsUser($this->admin)
            ->delete(route('prontuario.catalogo.destroy', $first))
            ->assertSessionHas('success');
        $this->assertNull($first->fresh());

        // A lacuna foi fechada: 1.2 virou 1.1 e 1.11 virou 1.10.
        $second->refresh();
        $last->refresh();

        $this->assertSame('1.1', $second->code);
        $this->assertSame(1, $second->n2);
        $this->assertSame('1.10', $last->code);
        $this->assertSame(10, $last->n2);
    }

    public function test_deleting_middle_subitem_renumbers_the_tail(): void
    {
        $before = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('n1', 1)
            ->where('is_section', false)
            ->where('n2', 2)
            ->firstOrFail();
        $third = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('n1', 1)
            ->where('is_section', false)
            ->where('n2', 3)
            ->firstOrFail();
        $last = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('n1', 1)
            ->where('is_section', false)
            ->where('n2', 11)
            ->firstOrFail();

        $this->actingAsUser($this->admin)
            ->delete(route('prontuario.catalogo.destroy', $third))
            ->assertSessionHas('success');

        // 1.4 virou 1.3 e 1.11 virou 1.10; os anteriores (1.1, 1.2) intocados.
        $thirdRenumbered = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('n1', 1)
            ->where('is_section', false)
            ->where('n2', 3)
            ->firstOrFail();

        $this->assertSame('1.3', $thirdRenumbered->code);
        $this->assertSame('1.10', $last->fresh()->code);
        $this->assertSame('1.2', $before->fresh()->code);
    }

    public function test_subitem_created_after_deletion_goes_to_the_end(): void
    {
        $section = $this->prontuarioSection(1);

        $first = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('n1', 1)
            ->where('is_section', false)
            ->where('n2', 1)
            ->firstOrFail();

        $this->actingAsUser($this->admin)
            ->delete(route('prontuario.catalogo.destroy', $first))
            ->assertSessionHas('success');

        $lastN2 = (int) CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('n1', 1)
            ->where('is_section', false)
            ->max('n2');

        $this->actingAsUser($this->manager)
            ->post(route('prontuario.catalogo.item.store'), [
                'section_id' => $section->id,
                'title' => 'Vai para o final',
            ])
            ->assertRedirect(route('prontuario.catalogo.index'));

        $item = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('code', '1.'.($lastN2 + 1))
            ->where('is_section', false)
            ->firstOrFail();

        $this->assertSame('Vai para o final', $item->title);
        $this->assertSame($lastN2 + 1, $item->n2);
    }

    public function test_deleting_section_renumbers_following_sections_and_children(): void
    {
        $this->actingAsUser($this->manager)
            ->post(route('prontuario.catalogo.section.store'), ['title' => 'Seção a excluir']);

        $toDelete = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', true)
            ->where('title', 'Seção a excluir')
            ->firstOrFail();

        $this->actingAsUser($this->manager)
            ->post(route('prontuario.catalogo.section.store'), ['title' => 'Seção final']);

        $following = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', true)
            ->where('title', 'Seção final')
            ->firstOrFail();

        $this->actingAsUser($this->manager)
            ->post(route('prontuario.catalogo.item.store'), [
                'section_id' => $following->id,
                'title' => 'Subitem da seção final',
            ]);

        $child = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('title', 'Subitem da seção final')
            ->firstOrFail();

        $this->actingAsUser($this->admin)
            ->delete(route('prontuario.catalogo.destroy', $toDelete))
            ->assertSessionHas('success');
        $this->assertNull($toDelete->fresh());

        // A seção seguinte assume o número liberado junto com o subitem dela.
        $following->refresh();
        $child->refresh();

        $this->assertSame($toDelete->n1, $following->n1);
        $this->assertSame((string) $toDelete->n1, $following->code);
        $this->assertSame($toDelete->n1, $child->n1);
        $this->assertSame($toDelete->n1.'.1', $child->code);

        // Nenhuma seção ficou duplicada após a renumeração.
        $this->assertSame(1, CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', true)
            ->where('n1', $toDelete->n1)
            ->count());
    }

    public function test_subitem_rejects_cronograma_section(): void
    {
        $cronogramaSection = CatalogItem::query()
            ->where('source', 'cronograma')
            ->where('is_section', true)
            ->firstOrFail();

        $this->actingAsUser($this->manager)
            ->post(route('prontuario.catalogo.item.store'), [
                'section_id' => $cronogramaSection->id,
                'title' => 'Inválido',
            ])
            ->assertSessionHasErrors('section_id');
    }

    public function test_item_title_can_be_updated(): void
    {
        $item = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('code', '1.1')
            ->firstOrFail();

        $this->actingAsUser($this->manager)
            ->put(route('prontuario.catalogo.update', $item), ['title' => 'Novo título'])
            ->assertSessionHas('success');

        $this->assertSame('Novo título', $item->fresh()->title);
    }

    public function test_subitem_deletion_blocked_when_linked_to_document(): void
    {
        $child = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('n1', '!=', 4)
            ->firstOrFail();

        $this->actingAsUser($this->manager)
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$child->id]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame(1, $document->items()->count());

        $this->actingAsUser($this->admin)
            ->delete(route('prontuario.catalogo.destroy', $child))
            ->assertSessionHas('error');

        $this->assertNotNull($child->fresh());
    }

    public function test_subitem_deletion_works_after_document_removed(): void
    {
        $child = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('n1', '!=', 4)
            ->firstOrFail();

        $this->actingAsUser($this->manager)
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$child->id]]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAsUser($this->admin)
            ->delete(route('nc-documents.destroy', $document))
            ->assertRedirect();

        $this->actingAsUser($this->admin)
            ->delete(route('prontuario.catalogo.destroy', $child))
            ->assertSessionHas('success');

        $this->assertNull($child->fresh());
        $this->assertSame(0, TenantItem::withoutGlobalScopes()->where('catalog_item_id', $child->id)->count());
    }

    public function test_section_deletion_blocked_when_it_has_children(): void
    {
        $section = $this->prontuarioSection(1);
        $this->assertTrue(CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('code', 'like', '1.%')
            ->exists());

        $this->actingAsUser($this->admin)
            ->delete(route('prontuario.catalogo.destroy', $section))
            ->assertSessionHas('error');

        $this->assertNotNull($section->fresh());
    }

    public function test_section_without_children_can_be_deleted(): void
    {
        $this->actingAsUser($this->manager)
            ->post(route('prontuario.catalogo.section.store'), ['title' => 'Seção vazia']);

        $section = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', true)
            ->where('title', 'Seção vazia')
            ->firstOrFail();

        $this->actingAsUser($this->admin)
            ->delete(route('prontuario.catalogo.destroy', $section))
            ->assertSessionHas('success');

        $this->assertNull($section->fresh());
    }

    public function test_viewer_cannot_manage_catalog(): void
    {
        $this->actingAsUser($this->viewer)
            ->get(route('prontuario.catalogo.index'))
            ->assertForbidden();

        $this->actingAsUser($this->viewer)
            ->post(route('prontuario.catalogo.section.store'), ['title' => 'X'])
            ->assertForbidden();
    }

    public function test_manager_cannot_delete_items(): void
    {
        $child = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('n1', '!=', 4)
            ->firstOrFail();

        $this->actingAsUser($this->manager)
            ->delete(route('prontuario.catalogo.destroy', $child))
            ->assertForbidden();
    }
}
