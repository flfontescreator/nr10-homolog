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

class NcDocumentItemsModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $manager;

    protected User $admin;

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

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function actingAsUser(User $user): self
    {
        return $this->actingAs($user)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true]);
    }

    protected function opSection(int $n1): CatalogItem
    {
        return CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', true)
            ->where('n1', $n1)
            ->firstOrFail();
    }

    protected function opChild(int $n1, int $n2 = 1): CatalogItem
    {
        return CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('n1', $n1)
            ->where('n2', $n2)
            ->firstOrFail();
    }

    protected function genericOpChild(): CatalogItem
    {
        return CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('n1', '!=', 4)
            ->firstOrFail();
    }

    protected function normativaChild(): CatalogItem
    {
        return CatalogItem::query()
            ->where('source', 'cronograma')
            ->where('is_section', false)
            ->orderBy('sort')
            ->firstOrFail();
    }

    protected function createDocument(array $ids): NcDocument
    {
        $this->actingAsUser($this->manager)
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $ids])
            ->assertRedirect();

        return NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
    }

    public function test_items_manager_lists_the_document_items(): void
    {
        $section = $this->opSection(1);
        $child = $this->opChild(1);

        $document = $this->createDocument([$section->id, $child->id]);

        $this->actingAsUser($this->manager)
            ->get(route('nc-documents.items.index', $document))
            ->assertOk()
            ->assertSee('Gerenciar itens do documento')
            ->assertSee($section->code)
            ->assertSee($section->title)
            ->assertSee($child->code)
            ->assertSee($child->title)
            ->assertSee('Adicionar item do catálogo (como cópia)');
    }

    public function test_items_manager_attaches_catalog_item_as_copy(): void
    {
        $child = $this->opChild(1);
        $newSection = $this->opSection(2);
        $newChild = $this->opChild(2);

        $document = $this->createDocument([$child->id]);

        $this->actingAsUser($this->manager)
            ->post(route('nc-documents.items.attach', $document), ['catalog_item_ids' => [$newSection->id, $newChild->id]])
            ->assertSessionHas('success');

        $this->assertTrue($document->items()->where('catalog_item_id', $newSection->id)->exists());
        $this->assertTrue($document->items()->where('catalog_item_id', $newChild->id)->exists());

        // Cópia congelada no documento; o catálogo não mudou.
        $entry = $document->items()->where('catalog_item_id', $newChild->id)->firstOrFail();
        $this->assertSame($newChild->code, $entry->code);
        $this->assertSame($newChild->title, $entry->title);

        $newChild->refresh();
        $this->assertSame($newChild->code, '2.1');
    }

    public function test_items_manager_ignores_duplicate_attach(): void
    {
        $child = $this->opChild(1);

        $document = $this->createDocument([$child->id]);

        $this->actingAsUser($this->manager)
            ->post(route('nc-documents.items.attach', $document), ['catalog_item_ids' => [$child->id]])
            ->assertSessionHas('info');

        $this->assertSame(1, $document->items()->where('catalog_item_id', $child->id)->count());
    }

    public function test_item_copy_edit_does_not_touch_catalog(): void
    {
        $child = $this->opChild(1);

        $document = $this->createDocument([$child->id]);
        $entry = $document->items()->where('catalog_item_id', $child->id)->firstOrFail();

        $this->actingAsUser($this->manager)
            ->put(route('nc-documents.items.update', [$document, $entry]), [
                'code' => '1.1-A',
                'title' => 'Cópia renomeada no documento',
            ])
            ->assertSessionHas('success');

        $entry->refresh();
        $this->assertSame('1.1-A', $entry->code);
        $this->assertSame('Cópia renomeada no documento', $entry->title);

        $child->refresh();
        $this->assertSame('1.1', $child->code);
        $this->assertNotSame('Cópia renomeada no documento', $child->title);

        // O documento exibe a cópia própria (independente do catálogo).
        $this->actingAsUser($this->manager)
            ->get(route('nc-documents.show', $document))
            ->assertOk()
            ->assertSee('1.1-A')
            ->assertSee('Cópia renomeada no documento');
    }

    public function test_section_removal_removes_its_child_items_from_document(): void
    {
        $section = $this->opSection(1);
        $child = $this->opChild(1);

        $document = $this->createDocument([$section->id, $child->id]);
        $sectionEntry = $document->items()->where('catalog_item_id', $section->id)->firstOrFail();

        $this->actingAsUser($this->manager)
            ->delete(route('nc-documents.items.destroy', [$document, $sectionEntry]))
            ->assertSessionHas('success');

        $this->assertSame(0, $document->items()->count());

        // A linha de trabalho do cliente não é apagada.
        $this->assertTrue(TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $child->id)
            ->exists());
    }

    public function test_editing_selection_preserves_avulso_items(): void
    {
        $normativa = $this->normativaChild();
        $child = $this->genericOpChild();

        $document = $this->createDocument([$normativa->id, $child->id]);

        // O catálogo operacional perde o sub-item depois da criação do documento:
        // a cópia dele vira "avulso" (sem vínculo), mas permanece no documento.
        $this->actingAsUser($this->admin)
            ->delete(route('prontuario.catalogo.destroy', $child))
            ->assertSessionHas('success');

        $avulso = $document->items()->whereNull('catalog_item_id')->firstOrFail();

        $this->actingAsUser($this->manager)
            ->put(route('nc-documents.update', $document), [
                'title' => 'Documento atualizado',
                'catalog_item_ids' => [$normativa->id],
                'preserve_item_ids' => [$avulso->id],
            ])
            ->assertSessionHas('success');

        $this->assertTrue($document->items()->where('id', $avulso->id)->exists());
        $this->assertSame($child->code, $avulso->fresh()->code);
        $this->assertSame(2, $document->items()->count());
    }

    public function test_editing_selection_without_preserve_drops_avulso_items(): void
    {
        $normativa = $this->normativaChild();
        $child = $this->genericOpChild();

        $document = $this->createDocument([$normativa->id, $child->id]);

        $this->actingAsUser($this->admin)
            ->delete(route('prontuario.catalogo.destroy', $child))
            ->assertSessionHas('success');

        $avulsoId = $document->items()->whereNull('catalog_item_id')->firstOrFail()->id;

        $this->actingAsUser($this->manager)
            ->put(route('nc-documents.update', $document), [
                'title' => 'Documento atualizado',
                'catalog_item_ids' => [$normativa->id],
            ])
            ->assertSessionHas('success');

        $this->assertFalse($document->items()->where('id', $avulsoId)->exists());
        $this->assertSame(1, $document->items()->count());
    }
}
