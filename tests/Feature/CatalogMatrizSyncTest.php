<?php

namespace Tests\Feature;

use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Tenant;
use App\Support\CronogramaOptions;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogMatrizSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CatalogSeeder::class);
    }

    private function item(string $code): CatalogItem
    {
        return CatalogItem::query()
            ->where('source', Source::Cronograma->value)
            ->where('code', $code)
            ->firstOrFail();
    }

    public function test_matriz_creates_missing_items_and_sections(): void
    {
        $cronograma = CatalogItem::query()->where('source', Source::Cronograma->value);

        $this->assertSame(133, (clone $cronograma)->where('is_section', false)->count());
        $this->assertSame(16, (clone $cronograma)->where('is_section', true)->count());
        $this->assertSame(149, (clone $cronograma)->count());

        foreach (['10.1.1', '10.1.2', '10.2.1', '10.2.4.1', '10.7.4.2'] as $code) {
            $this->assertNotNull($this->item($code)->id, "item $code deveria existir");
        }

        $this->assertTrue($this->item('10.1')->is_section);
        $this->assertTrue($this->item('10.2')->is_section);

        $tree = CatalogItem::tree(Source::Cronograma);
        $secao101 = $tree->first(fn ($group) => $group->section->code === '10.1');

        $this->assertNotNull($secao101);
        $this->assertCount(2, $secao101->children);
    }

    public function test_new_tenant_bootstrap_includes_created_items(): void
    {
        $tenant = Tenant::create(['name' => 'Cliente Matriz']);
        $tenant->bootstrapItems();

        $this->assertDatabaseHas('tenant_items', [
            'tenant_id' => $tenant->id,
            'catalog_item_id' => $this->item('10.7.4.2')->id,
        ]);
    }

    public function test_matriz_fills_matrix_fields_on_items(): void
    {
        $item = $this->item('10.1.1');

        $this->assertStringStartsWith('10.1.1 Esta Norma estabelece', $item->norma_tecnica);
        $this->assertStringStartsWith('Esta Norma estabelece', $item->description);
        $this->assertNotSame('', $item->interpretacao_tecnica);
        $this->assertNotSame('', $item->sugestao_acao);
        $this->assertSame('Não iniciado', $item->status);
        $this->assertSame('ALTA', $item->criticidade);
        $this->assertSame(['SESMT', 'Engenharia Elétrica', 'SGI'], $item->setores);
        $this->assertSame('SESMT', $item->setor);
    }

    public function test_matriz_converges_divergent_norm_texts(): void
    {
        // 10.7.4.1 do banco vinha contaminado com o texto do 10.7.4.2.
        $item = $this->item('10.7.4.1');
        $this->assertStringNotContainsString('10.7.4.2', $item->description);
        $this->assertStringStartsWith('10.7.4.1 Avaliação prévia deve verificar', $item->norma_tecnica);

        // "Nasinstalações" (sem espaço) corrigido pela planilha.
        $this->assertStringStartsWith('Nas instalações e serviços em eletricidade', $this->item('10.7.7')->description);
    }

    public function test_matriz_reclassifies_criticidade_and_setores(): void
    {
        $this->assertSame('Crítica / Grave e Iminente Risco (GIR)', $this->item('10.3.1')->criticidade);
        $this->assertSame(['SESMT', 'Engenharia Elétrica', 'Produção'], $this->item('10.3.1')->setores);

        $this->assertSame('MÉDIA', $this->item('10.1.2')->criticidade);
        $this->assertSame('BAIXA', $this->item('10.8.4.1.1')->criticidade);
        $this->assertSame(['PLH', 'Engenharia'], $this->item('10.8.4.1.1')->setores);

        $this->assertContains('BAIXA', CronogramaOptions::criticidades());
        $this->assertContains('SGI', CronogramaOptions::setores());
    }

    public function test_matriz_preserves_empty_field_edits_and_detalhamento(): void
    {
        $item = $this->item('10.3.1');

        // detalhamento vem do cronograma.csv e não é tocado pela matriz.
        $this->assertNotSame('', $item->detalhamento);

        // Campos preenchidos depois do seed são preservados no reseed.
        $item->update(['status' => 'Em andamento', 'sugestao_acao' => 'Ação manual']);

        $this->seed(CatalogSeeder::class);

        $item->refresh();
        $this->assertSame('Em andamento', $item->status);
        $this->assertSame('Ação manual', $item->sugestao_acao);
    }

    public function test_seeder_is_idempotent_and_preserves_ids(): void
    {
        $before = CatalogItem::query()
            ->where('source', Source::Cronograma->value)
            ->orderBy('id')
            ->pluck('id', 'code');

        $this->seed(CatalogSeeder::class);

        $after = CatalogItem::query()
            ->where('source', Source::Cronograma->value)
            ->orderBy('id')
            ->pluck('id', 'code');

        $this->assertSame(149, $after->count());
        $this->assertEquals($before, $after);
    }
}
