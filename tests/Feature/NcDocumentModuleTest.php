<?php

namespace Tests\Feature;

use App\Enums\Source;
use App\Models\AuditLog;
use App\Models\CatalogItem;
use App\Models\Evidence;
use App\Models\EvidenceDocument;
use App\Models\NcDocument;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NcDocumentModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $manager;

    protected User $viewer;

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

        $this->viewer = User::factory()->create([
            'role' => 'viewer',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function catalogIds(int $count): array
    {
        return CatalogItem::query()
            ->where('source', 'cronograma')
            ->where('is_section', false)
            ->orderBy('sort')
            ->limit($count)
            ->pluck('id')
            ->all();
    }

    protected function asUser(User $user, Tenant $tenant): array
    {
        return [
            'user' => $user,
            'session' => ['tenant_id' => $tenant->id, 'two_step_verified' => true],
        ];
    }

    public function test_show_renders_source_badge_per_item(): void
    {
        $normativaId = $this->catalogIds(1)[0];

        $operacionalId = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('n1', '!=', 4)
            ->firstOrFail()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$normativaId, $operacionalId]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('nc-documents.show', $document))
            ->assertOk()
            ->assertSee('badge-blue', false)
            ->assertSee('Normativa')
            ->assertSee('badge-green', false)
            ->assertSee('Operacional');
    }

    public function test_document_grid_orders_items_by_catalog_numbering(): void
    {
        $sec4 = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', true)
            ->where('code', '4')
            ->firstOrFail();

        $item43 = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('code', '4.3')
            ->firstOrFail();

        $sec8 = CatalogItem::create([
            'source' => 'prontuario',
            'code' => '8',
            'n1' => 8,
            'n2' => 0,
            'n3' => 0,
            'n4' => 0,
            'is_section' => true,
            'title' => 'Seção nova no final',
            'description' => '',
        ]);

        $item81 = CatalogItem::create([
            'source' => 'prontuario',
            'parent_id' => $sec8->id,
            'code' => '8.1',
            'n1' => 8,
            'n2' => 1,
            'n3' => 0,
            'n4' => 0,
            'is_section' => false,
            'title' => 'Sub-item novo',
            'description' => '',
        ]);

        // Ordem enviada pelo formulário fora da sequência: 8, 4.3, 4, 8.1.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), [
                'catalog_item_ids' => [$sec8->id, $item43->id, $sec4->id, $item81->id],
            ])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $html = $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('nc-documents.show', $document))
            ->assertOk()
            ->getContent();

        $posSec4 = strpos($html, 'documentação comprobatória');
        $posItem43 = strpos($html, '<strong>4.3</strong>');
        $posSec8 = strpos($html, 'Seção nova no final');
        $posItem81 = strpos($html, '<strong>8.1</strong>');

        // Grid segue a sequência do catálogo, não a ordem do formulário:
        // seção 4, 4.3, seção 8, 8.1 (a nova no final).
        $this->assertNotFalse($posSec4);
        $this->assertNotFalse($posItem43);
        $this->assertNotFalse($posSec8);
        $this->assertNotFalse($posItem81);
        $this->assertLessThan($posItem43, $posSec4);
        $this->assertLessThan($posSec8, $posItem43);
        $this->assertLessThan($posItem81, $posSec8);
    }

    public function test_checklist_grid_shows_report_type_badges_per_document(): void
    {
        $normativaId = $this->catalogIds(1)[0];

        $operacionalId = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('n1', '!=', 4)
            ->firstOrFail()->id;

        // Um documento só normativo, um só operacional e um misto.
        foreach ([[$normativaId], [$operacionalId], [$normativaId, $operacionalId]] as $selection) {
            $this->actingAs($this->manager)
                ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
                ->post(route('nc-documents.store'), ['catalog_item_ids' => $selection])
                ->assertRedirect();
        }

        $html = $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('checklist.index'))
            ->assertOk()
            ->getContent();

        // Normativa aparece no doc normativo e no misto; Operacional no doc
        // operacional e no misto. Se o tipo de relatório fosse marcado errado,
        // as contagens divergiriam.
        $this->assertSame(2, substr_count($html, 'Normativa'));
        $this->assertSame(2, substr_count($html, 'Operacional'));
    }

    public function test_manager_can_create_document_with_selection(): void
    {
        $ids = $this->catalogIds(3);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $ids])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertSame('RNC-00001', $document->code);
        $this->assertSame('draft', $document->status->value);
        $this->assertSame(3, $document->items()->count());
        $this->assertSame(1, $document->versions()->count());

        foreach ($document->items()->get() as $entry) {
            $this->assertNotNull($entry->tenant_item_id);
        }
    }

    public function test_manager_can_create_document_with_operacional_items(): void
    {
        $childId = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('n1', '!=', 4)
            ->firstOrFail()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $entry = $document->items()->where('catalog_item_id', $childId)->firstOrFail();
        $this->assertSame('prontuario', $entry->catalogItem->source->value);
        $this->assertNotNull($entry->tenant_item_id);

        $tenantItem = TenantItem::withoutGlobalScopes()->findOrFail($entry->tenant_item_id);
        $this->assertSame($this->tenant->id, $tenantItem->tenant_id);
        $this->assertSame($childId, $tenantItem->catalog_item_id);
    }

    public function test_store_accepts_mixed_normativa_and_operacional_items(): void
    {
        $id = $this->catalogIds(1)[0];

        $childId = CatalogItem::query()
            ->where('source', 'prontuario')
            ->where('is_section', false)
            ->where('n1', '!=', 4)
            ->firstOrFail()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$id, $childId]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $this->assertSame(2, $document->items()->count());
    }

    public function test_selection_rejects_checklist_items(): void
    {
        $checklistId = CatalogItem::query()
            ->where('source', 'checklist')
            ->where('is_section', false)
            ->firstOrFail()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$checklistId]])
            ->assertSessionHasErrors('catalog_item_ids.*');

        $this->assertSame(0, NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_create_page_renders_both_tabs(): void
    {
        $response = $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('nc-documents.create'))
            ->assertOk();

        $response->assertSee('data-tab-button="normativa"', false)
            ->assertSee('data-tab-button="operacional"', false)
            ->assertSee('data-tab-panel="normativa"', false)
            ->assertSee('data-tab-panel="operacional"', false);
    }

    public function test_create_page_has_sticky_action_bar_with_count_and_shortcut_hint(): void
    {
        $response = $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('nc-documents.create'))
            ->assertOk();

        $response->assertSee('data-form-actions', false)
            ->assertSee('data-selected-count', false)
            ->assertSee('item(ns) selecionado(s)')
            ->assertSee('Atalhos:')
            ->assertSee('requestSubmit', false)
            ->assertSee('Criar documento');
    }

    public function test_sections_can_be_selected_as_cover_items(): void
    {
        $tree = CatalogItem::tree(Source::Cronograma);
        $branch = $tree->first();
        $this->assertNotNull($branch);

        $sectionId = $branch->section->id;
        $childId = $branch->children->first()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$sectionId, $childId]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $sectionItem = $document->items()->where('catalog_item_id', $sectionId)->first();
        $childItem = $document->items()->where('catalog_item_id', $childId)->first();

        $this->assertNotNull($sectionItem);
        $this->assertNull($sectionItem->tenant_item_id);

        $this->assertNotNull($childItem);
        $this->assertNotNull($childItem->tenant_item_id);

        $this->assertSame(2, $document->items()->count());
    }

    public function test_second_document_increments_sequential_number(): void
    {
        $ids = $this->catalogIds(2);

        foreach (['RNC-00001', 'RNC-00002'] as $expectedCode) {
            $this->actingAs($this->manager)
                ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
                ->post(route('nc-documents.store'), ['catalog_item_ids' => $ids])
                ->assertRedirect();
        }

        $codes = NcDocument::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->orderBy('number')
            ->pluck('code')
            ->all();

        $this->assertSame(['RNC-00001', 'RNC-00002'], $codes);
    }

    public function test_selection_requires_at_least_one_item(): void
    {
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => []])
            ->assertSessionHasErrors('catalog_item_ids');

        $this->assertSame(0, NcDocument::withoutGlobalScopes()->count());
    }

    public function test_update_records_a_new_version_snapshot(): void
    {
        $ids = $this->catalogIds(2);
        $thirdId = $this->catalogIds(3)[2];

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $ids]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('nc-documents.update', $document), [
                'title' => 'Revisão de setembro',
                'catalog_item_ids' => [...$ids, $thirdId],
            ])
            ->assertRedirect();

        $fresh = $document->fresh();
        $this->assertSame('Revisão de setembro', $fresh->title);
        $this->assertSame(2, $fresh->versions()->count());
        $this->assertSame(3, $fresh->items()->count());

        $latest = $fresh->versions()->orderByDesc('version')->first();
        $this->assertSame(3, count($latest->selection));
    }

    public function test_removing_items_keeps_tenant_work_data(): void
    {
        $ids = $this->catalogIds(2);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $ids]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('nc-documents.update', $document), [
                'catalog_item_ids' => [$ids[0]],
            ])
            ->assertRedirect();

        $this->assertSame(1, $document->fresh()->items()->count());
        $this->assertSame(2, TenantItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->whereIn('catalog_item_id', $ids)->count());
    }

    public function test_finalized_document_cannot_be_edited(): void
    {
        $ids = $this->catalogIds(2);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $ids]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.finalize', $document))
            ->assertSessionHas('success');

        $this->assertTrue($document->fresh()->isFinalized());
        $this->assertNotNull($document->fresh()->finalized_at);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('nc-documents.update', $document), ['catalog_item_ids' => $ids])
            ->assertSessionHas('warning');

        $this->assertSame(2, $document->fresh()->versions()->count());
    }

    public function test_reopened_document_can_be_edited_again(): void
    {
        $ids = $this->catalogIds(2);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $ids]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.finalize', $document));

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.reopen', $document))
            ->assertSessionHas('success');

        $reopened = $document->fresh();
        $this->assertFalse($reopened->isFinalized());
        $this->assertNull($reopened->finalized_at);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('nc-documents.update', $reopened), ['catalog_item_ids' => $ids])
            ->assertSessionHas('success');

        $this->assertSame(3, $reopened->fresh()->versions()->count());
    }

    public function test_viewer_cannot_create_finalize_or_reopen(): void
    {
        $ids = $this->catalogIds(2);

        $this->actingAs($this->viewer)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $ids])
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $ids]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->viewer)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.finalize', $document))
            ->assertForbidden();
    }

    public function test_cross_tenant_document_is_not_accessible(): void
    {
        $ids = $this->catalogIds(2);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $ids]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $otherTenant = Tenant::create(['name' => 'Outro Cliente']);
        $otherManager = User::factory()->create(['role' => 'manager', 'tenant_id' => $otherTenant->id]);

        $this->actingAs($otherManager)
            ->withSession(['tenant_id' => $otherTenant->id, 'two_step_verified' => true])
            ->get(route('nc-documents.show', $document))
            ->assertNotFound();
    }

    public function test_working_item_from_document_backs_to_document(): void
    {
        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', [$item, 'from' => 'document', 'document_id' => $document->id]))
            ->assertOk()
            ->assertSee(route('nc-documents.show', $document), false);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', $item))
            ->assertOk()
            ->assertSee(route('cronograma.index'), false);
    }

    public function test_edit_form_from_document_carries_context_and_saves_to_document_item(): void
    {
        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $entry = $document->items()->firstOrFail();

        $item = TenantItem::withoutGlobalScopes()->findOrFail($entry->tenant_item_id);

        // O form de edição aberto dentro do documento precisa manter o contexto
        // (from=document&document_id) na action, para o save ir para o NcDocumentItem.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', [$item, 'from' => 'document', 'document_id' => $document->id]))
            ->assertOk()
            ->assertSee(route('cronograma.update', ['item' => $item, 'from' => 'document', 'document_id' => $document->id]));

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('cronograma.update', ['item' => $item, 'from' => 'document', 'document_id' => $document->id]), [
                'criticidade' => 'ALTA',
                'condicao_inicial' => 'Não adequado',
                'status' => 'Concluído',
            ])
            ->assertSessionHas('success');

        $this->assertSame('ALTA', $entry->fresh()->criticidade);
        $this->assertSame('Não adequado', $entry->fresh()->condicao_inicial);
        $this->assertSame('Concluído', $entry->fresh()->status->value);
        $this->assertNull($item->fresh()->status);
        $this->assertNull($item->fresh()->criticidade);
    }

    public function test_document_item_zeroed_setores_do_not_fall_back_to_catalog(): void
    {
        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $entry = $document->items()->firstOrFail();

        $item = TenantItem::withoutGlobalScopes()->findOrFail($entry->tenant_item_id);

        // Item novo do documento herda o catálogo como padrão exibido (setores não tocados).
        $this->assertNull($entry->setores);
        $this->assertSame($entry->catalogItem->setores_list, $entry->setores_list);

        // Remover todos os badges (requisição sem setores) persiste vazio: não volta ao catálogo.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('cronograma.update', ['item' => $item, 'from' => 'document', 'document_id' => $document->id]), [
                'status' => 'Em andamento',
            ])
            ->assertSessionHas('success');

        $this->assertSame([], $entry->fresh()->setores);
        $this->assertSame([], $entry->fresh()->setores_list);
    }

    public function test_finalized_document_blocks_its_own_item_but_plan_stays_free(): void
    {
        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.finalize', $document))
            ->assertSessionHas('success');

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', [$item, 'from' => 'document', 'document_id' => $document->id]))
            ->assertOk()
            ->assertSee('documento finalizado', false)
            ->assertDontSee('name="status"');

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('cronograma.update', [$item, 'from' => 'document', 'document_id' => $document->id]), ['status' => 'Concluído'])
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', $item))
            ->assertOk()
            ->assertSee('name="status"', false);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('cronograma.update', $item), ['status' => 'Em andamento'])
            ->assertSessionHas('success');
    }

    public function test_second_document_can_work_item_independently_while_first_is_finalized(): void
    {
        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $doc1 = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.finalize', $doc1))
            ->assertSessionHas('success');

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $doc2 = NcDocument::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('number', 2)
            ->firstOrFail();

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('cronograma.update', [$item, 'from' => 'document', 'document_id' => $doc2->id]), ['status' => 'Concluído'])
            ->assertSessionHas('success');

        $entry1 = $doc1->items()->where('tenant_item_id', $item->id)->first();
        $entry2 = $doc2->items()->where('tenant_item_id', $item->id)->first();

        $this->assertNull($entry1->fresh()->status);
        $this->assertSame('Concluído', $entry2->fresh()->status->value);
        $this->assertNull($item->fresh()->status);
    }

    public function test_document_working_state_starts_from_tenant_and_diverges(): void
    {
        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('cronograma.update', $item), ['status' => 'Em andamento', 'condicao_inicial' => 'Não adequado'])
            ->assertSessionHas('success');

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();
        $entry = $document->items()->where('tenant_item_id', $item->id)->firstOrFail();

        $this->assertSame('Em andamento', $entry->fresh()->status->value);
        $this->assertSame('Não adequado', $entry->fresh()->condicao_inicial);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('cronograma.update', [$item, 'from' => 'document', 'document_id' => $document->id]), ['status' => 'Concluído'])
            ->assertSessionHas('success');

        $this->assertSame('Concluído', $entry->fresh()->status->value);
        $this->assertSame('Em andamento', $item->fresh()->status->value);
    }

    public function test_document_evidence_is_separate_from_plan_evidence(): void
    {
        Storage::fake('local');

        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', $item), ['evidence' => UploadedFile::fake()->image('plano.png')])
            ->assertSessionHas('success');

        $planEvidence = Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', [$item, 'from' => 'document', 'document_id' => $document->id]), ['evidence' => UploadedFile::fake()->image('documento.png')])
            ->assertSessionHas('success');

        $docEvidence = Evidence::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('id', '!=', $planEvidence->id)
            ->firstOrFail();

        $entry = $document->items()->where('tenant_item_id', $item->id)->firstOrFail();

        $this->assertDatabaseHas('evidence_tenant_item', ['evidence_id' => $planEvidence->id, 'tenant_item_id' => $item->id]);
        $this->assertDatabaseHas('evidence_document_item', ['evidence_id' => $docEvidence->id, 'nc_document_item_id' => $entry->id]);

        // Como viewer (sem picker de biblioteca), a listagem do plano mostra só
        // o arquivo do plano; a do documento mostra só o arquivo do documento.
        $this->actingAs($this->viewer)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', $item))
            ->assertOk()
            ->assertSee('plano.png')
            ->assertDontSee('documento.png');

        $this->actingAs($this->viewer)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', [$item, 'from' => 'document', 'document_id' => $document->id]))
            ->assertOk()
            ->assertSee('documento.png')
            ->assertDontSee('plano.png');
    }

    public function test_document_creation_is_audited(): void
    {
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => $this->catalogIds(2)]);

        $audit = AuditLog::withoutGlobalScopes()
            ->where('action', 'nc_document.created')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('RNC-00001', $audit->summary);
        $this->assertSame($this->manager->id, $audit->user_id);
    }

    public function test_upload_in_document_context_links_evidence_to_document(): void
    {
        Storage::fake('local');

        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', [$item, 'from' => 'document', 'document_id' => $document->id]), ['evidence' => UploadedFile::fake()->image('nc-foto.png')])
            ->assertSessionHas('success');

        $evidence = Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $entry = $document->items()->where('tenant_item_id', $item->id)->firstOrFail();

        $this->assertDatabaseHas('evidence_document', [
            'evidence_id' => $evidence->id,
            'document_id' => $document->id,
        ]);

        $this->assertDatabaseHas('evidence_document_item', [
            'evidence_id' => $evidence->id,
            'nc_document_item_id' => $entry->id,
        ]);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('RNC-00001');
    }

    public function test_upload_form_from_document_screen_carries_context_and_lists_evidence(): void
    {
        Storage::fake('local');

        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        // A tela do documento monta o upload JÁ com o contexto (from=document&document_id=).
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', [$item, 'from' => 'document', 'document_id' => $document->id]))
            ->assertSee('from=document&document_id='.$document->id);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', $item))
            ->assertDontSee('from=document&document_id=');

        // Anexando pelo contexto do documento: cria vínculo e o arquivo aparece
        // na listagem de miniaturas da mesma tela.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', [$item, 'from' => 'document', 'document_id' => $document->id]), ['evidence' => UploadedFile::fake()->image('doc-tela.png')])
            ->assertSessionHas('success');

        $evidence = Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertDatabaseHas('evidence_document', [
            'evidence_id' => $evidence->id,
            'document_id' => $document->id,
        ]);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', [$item, 'from' => 'document', 'document_id' => $document->id]))
            ->assertOk()
            ->assertSee('doc-tela.png');

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('RNC-00001');
    }

    public function test_reuse_library_evidence_when_creating_document(): void
    {
        Storage::fake('local');

        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', $item), ['evidence' => UploadedFile::fake()->image('plano.png')])
            ->assertSessionHas('success');

        $evidence = Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), [
                'catalog_item_ids' => [$childId],
                'evidence_ids' => [$evidence->id],
            ])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->assertDatabaseHas('evidence_document', [
            'evidence_id' => $evidence->id,
            'document_id' => $document->id,
        ]);

        // Sem duplicação: o mesmo arquivo é reutilizado, não copiado.
        $this->assertSame(1, Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('RNC-00001');
    }

    public function test_same_evidence_linked_to_two_documents_shows_both_badges(): void
    {
        Storage::fake('local');

        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', $item), ['evidence' => UploadedFile::fake()->image('compartilhada.png')]);

        $evidence = Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        foreach ([1, 2] as $_) {
            $this->actingAs($this->manager)
                ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
                ->post(route('nc-documents.store'), [
                    'catalog_item_ids' => [$childId],
                    'evidence_ids' => [$evidence->id],
                ])
                ->assertRedirect();
        }

        $this->assertSame(2, EvidenceDocument::query()->where('evidence_id', $evidence->id)->count());
        $this->assertSame(1, Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('RNC-00001')
            ->assertSee('RNC-00002');
    }

    public function test_removing_shared_evidence_from_document_keeps_file_for_other_documents(): void
    {
        Storage::fake('local');

        $ids = $this->catalogIds(2);
        $childId = $ids[0];
        $otherId = $ids[1];

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId, $otherId]]);

        $doc1 = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', [$item, 'from' => 'document', 'document_id' => $doc1->id]), ['evidence' => UploadedFile::fake()->image('compartilhada.png')]);

        $evidence = Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        // Segundo documento reutiliza o MESMO arquivo via biblioteca.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), [
                'catalog_item_ids' => [$childId],
                'evidence_ids' => [$evidence->id],
            ]);

        $doc2 = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('number', 2)->firstOrFail();

        $this->assertSame(2, EvidenceDocument::query()->where('evidence_id', $evidence->id)->count());

        $entry1 = $doc1->items()->where('tenant_item_id', $item->id)->firstOrFail();

        // Remove o item do doc1: o arquivo continua para o doc2.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('nc-documents.update', $doc1), ['catalog_item_ids' => [$otherId]])
            ->assertRedirect();

        $this->assertDatabaseMissing('evidence_document_item', ['evidence_id' => $evidence->id, 'nc_document_item_id' => $entry1->id]);
        $this->assertDatabaseMissing('evidence_document', ['evidence_id' => $evidence->id, 'document_id' => $doc1->id]);
        $this->assertSame([$doc2->id], EvidenceDocument::query()
            ->where('evidence_id', $evidence->id)
            ->pluck('document_id')
            ->all());
        $this->assertDatabaseHas('evidences', ['id' => $evidence->id]);
        $this->assertTrue(Storage::disk('local')->exists($evidence->fresh()->stored_path));
    }

    public function test_cannot_link_evidence_from_another_tenant(): void
    {
        Storage::fake('local');

        $otherTenant = Tenant::create(['name' => 'Outro Cliente']);
        $otherTenant->bootstrapItems();

        $otherItem = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $otherTenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'cronograma')->where('is_section', false))
            ->firstOrFail();

        $otherEvidence = Evidence::create([
            'tenant_id' => $otherTenant->id,
            'tenant_item_id' => $otherItem->id,
            'uploaded_by' => $this->manager->id,
            'original_name' => 'alheio.png',
            'stored_path' => 'evidences/tenant-'.$otherTenant->id.'/alheio.png',
            'disk' => 'local',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
        ]);

        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), [
                'catalog_item_ids' => [$childId],
                'evidence_ids' => [$otherEvidence->id],
            ])
            ->assertSessionHasErrors('evidence_ids.0');
    }

    public function test_document_destroy_keeps_shared_library_evidence(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create(['role' => 'admin', 'tenant_id' => $this->tenant->id]);

        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', $item), ['evidence' => UploadedFile::fake()->image('compartilhada.png')]);

        $evidence = Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), [
                'catalog_item_ids' => [$childId],
                'evidence_ids' => [$evidence->id],
            ]);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), [
                'catalog_item_ids' => [$childId],
                'evidence_ids' => [$evidence->id],
            ]);

        $doc2 = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('number', 2)->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->delete(route('nc-documents.destroy', $doc2))
            ->assertRedirect();

        $this->assertDatabaseHas('evidences', ['id' => $evidence->id]);
        $this->assertSame(1, EvidenceDocument::query()->where('evidence_id', $evidence->id)->count());
    }

    public function test_library_file_can_be_reused_in_another_document_subitem(): void
    {
        Storage::fake('local');

        $branch = CatalogItem::tree(Source::Cronograma)->first();
        $childId = $branch->children->first()->id;

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $doc1 = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('catalog_item_id', $childId)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', [$item, 'from' => 'document', 'document_id' => $doc1->id]), ['evidence' => UploadedFile::fake()->image('reuso.png')])
            ->assertSessionHas('success');

        $evidence = Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$childId]]);

        $doc2 = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('number', 2)->firstOrFail();

        // Reusa o arquivo no sub-item do segundo documento via biblioteca.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.biblioteca.attach', [$item, 'from' => 'document', 'document_id' => $doc2->id]), ['evidence_ids' => [$evidence->id]])
            ->assertSessionHas('success');

        $entry1 = $doc1->items()->where('tenant_item_id', $item->id)->firstOrFail();
        $entry2 = $doc2->items()->where('tenant_item_id', $item->id)->firstOrFail();

        // Mesmo arquivo físico vinculado a sub-items de documentos diferentes.
        $this->assertDatabaseHas('evidence_document_item', ['evidence_id' => $evidence->id, 'nc_document_item_id' => $entry1->id]);
        $this->assertDatabaseHas('evidence_document_item', ['evidence_id' => $evidence->id, 'nc_document_item_id' => $entry2->id]);
        $this->assertDatabaseHas('evidence_document', ['evidence_id' => $evidence->id, 'document_id' => $doc2->id]);
        $this->assertSame(1, Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        // Aparece na tela de trabalho do doc2 e acumula os badges.
        $this->actingAs($this->viewer)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', [$item, 'from' => 'document', 'document_id' => $doc2->id]))
            ->assertOk()
            ->assertSee('reuso.png');

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee('RNC-00001')
            ->assertSee('RNC-00002')
            ->assertSee($item->catalogItem->code);
    }

    public function test_library_file_can_be_linked_to_plan_subitem(): void
    {
        Storage::fake('local');

        $ids = $this->catalogIds(2);

        $itemA = TenantItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('catalog_item_id', $ids[0])->firstOrFail();
        $itemB = TenantItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('catalog_item_id', $ids[1])->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', $itemA), ['evidence' => UploadedFile::fake()->image('origem.png')])
            ->assertSessionHas('success');

        $evidence = Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.biblioteca.attach', $itemB), ['evidence_ids' => [$evidence->id]])
            ->assertSessionHas('success');

        // O mesmo arquivo fica no card de origem E no card B do plano.
        $this->assertDatabaseHas('evidence_tenant_item', ['evidence_id' => $evidence->id, 'tenant_item_id' => $itemA->id]);
        $this->assertDatabaseHas('evidence_tenant_item', ['evidence_id' => $evidence->id, 'tenant_item_id' => $itemB->id]);
        $this->assertSame(1, Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());

        $this->actingAs($this->viewer)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', $itemA))
            ->assertOk()
            ->assertSee('origem.png');

        $this->actingAs($this->viewer)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', $itemB))
            ->assertOk()
            ->assertSee('origem.png');

        // Gestão de Documentos: badges na coluna Item com AMBOS os itens vinculados.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->assertSee($itemA->catalogItem->code)
            ->assertSee($itemB->catalogItem->code);
    }

    public function test_removing_last_link_deletes_file_but_shared_link_keeps_it(): void
    {
        Storage::fake('local');

        $ids = $this->catalogIds(2);

        $itemA = TenantItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('catalog_item_id', $ids[0])->firstOrFail();
        $itemB = TenantItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('catalog_item_id', $ids[1])->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', $itemA), ['evidence' => UploadedFile::fake()->image('compartilhada.png')]);

        $evidence = Evidence::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.biblioteca.attach', $itemB), ['evidence_ids' => [$evidence->id]]);

        // Remove do card A: ainda vinculado no card B → arquivo permanece.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->delete(route('cronograma.evidencia.destroy', [$itemA, $evidence]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('evidence_tenant_item', ['evidence_id' => $evidence->id, 'tenant_item_id' => $itemA->id]);
        $this->assertDatabaseHas('evidence_tenant_item', ['evidence_id' => $evidence->id, 'tenant_item_id' => $itemB->id]);
        $this->assertDatabaseHas('evidences', ['id' => $evidence->id]);

        // Remove do card B: último vínculo → arquivo e linha são apagados.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->delete(route('cronograma.evidencia.destroy', [$itemB, $evidence]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('evidences', ['id' => $evidence->id]);
        $this->assertFalse(Storage::disk('local')->exists($evidence->stored_path));
    }

    public function test_attach_library_form_present_on_document_and_plan_screens(): void
    {
        Storage::fake('local');

        $ids = $this->catalogIds(2);

        $itemA = TenantItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('catalog_item_id', $ids[0])->firstOrFail();
        $itemB = TenantItem::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('catalog_item_id', $ids[1])->firstOrFail();

        // Arquivo ancorado em OUTRO card do plano, disponível para reuso.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.evidencia.upload', $itemB), ['evidence' => UploadedFile::fake()->image('disponivel.png')]);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$ids[0]]]);

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        // Plano: o form de reuso aparece e lista o arquivo disponível.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', $itemA))
            ->assertOk()
            ->assertSee(route('cronograma.biblioteca.attach', $itemA), false)
            ->assertSee('disponivel.png');

        // Documento: o form de reuso mantém o contexto (from=document&document_id).
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('cronograma.show', [$itemA, 'from' => 'document', 'document_id' => $document->id]))
            ->assertOk()
            ->assertSee(htmlspecialchars(route('cronograma.biblioteca.attach', [$itemA, 'from' => 'document', 'document_id' => $document->id])), false)
            ->assertSee('disponivel.png');
    }

    public function test_cannot_attach_evidence_from_another_tenant_to_plan(): void
    {
        $otherTenant = Tenant::create(['name' => 'Outro Cliente']);
        $otherTenant->bootstrapItems();

        $otherItem = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $otherTenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'cronograma')->where('is_section', false))
            ->firstOrFail();

        $otherEvidence = Evidence::create([
            'tenant_id' => $otherTenant->id,
            'tenant_item_id' => $otherItem->id,
            'uploaded_by' => $this->manager->id,
            'original_name' => 'alheio-plan.png',
            'stored_path' => 'evidences/tenant-'.$otherTenant->id.'/alheio-plan.png',
            'disk' => 'local',
            'mime_type' => 'image/png',
            'size_bytes' => 1024,
        ]);

        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'cronograma')->where('is_section', false))
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('cronograma.biblioteca.attach', $item), ['evidence_ids' => [$otherEvidence->id]])
            ->assertSessionHasErrors('evidence_ids.0');
    }

    public function test_tenant_item_update_is_audited(): void
    {
        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'checklist')->where('code', '1'))
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('checklist.update', $item), [
                'data_inspecao' => '2026-09-01',
                'status' => 'Em andamento',
            ])
            ->assertSessionHas('success');

        $audit = AuditLog::withoutGlobalScopes()
            ->where('action', 'tenantitem.updated')
            ->where('tenant_id', $this->tenant->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringContainsString('1 —', $audit->summary);
        $this->assertSame('2026-09-01 00:00:00', $audit->data_new['data_inspecao']);
    }

    public function test_document_grid_badge_reflects_criticidade_worked_in_the_document(): void
    {
        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'cronograma')->where('is_section', false))
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$item->catalog_item_id]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        // Sem trabalho realizado, o grid ainda mostra o valor fixo do catálogo.
        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('nc-documents.show', $document))
            ->assertDontSee('crit-em-partes', false);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->put(route('cronograma.update', ['item' => $item, 'from' => 'document', 'document_id' => $document->id]), [
                'criticidade' => 'Em partes',
            ])
            ->assertSessionHas('success');

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('nc-documents.show', $document))
            ->assertSee('crit-em-partes', false);
    }

    public function test_document_grid_badge_falls_back_to_plan_criticidade(): void
    {
        $item = TenantItem::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->whereHas('catalogItem', fn ($q) => $q->where('source', 'cronograma')->where('is_section', false))
            ->firstOrFail();

        // Criticidade fixada no plano; o documento não trabalha o item.
        $item->update(['criticidade' => 'Em partes']);

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['catalog_item_ids' => [$item->catalog_item_id]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->firstOrFail();

        $this->actingAs($this->manager)
            ->withSession(['tenant_id' => $this->tenant->id, 'two_step_verified' => true])
            ->get(route('nc-documents.show', $document))
            ->assertSee('crit-em-partes', false);
    }
}
