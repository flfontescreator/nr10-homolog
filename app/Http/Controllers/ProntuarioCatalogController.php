<?php

namespace App\Http\Controllers;

use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\NcDocumentItem;
use App\Models\Tenant;
use App\Models\TenantItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestão do catálogo OPERACIONAL (Prontuário NR-10): criação, edição e
 * exclusão de seções (títulos) e sub-itens. A numeração segue o padrão do
 * prontuário: seções 1..N (inteiros) e sub-itens "X.Y" (seção.progressivo).
 */
class ProntuarioCatalogController extends Controller
{
    public function index(): View
    {
        $this->authorizeWrite();

        $source = Source::Prontuario->value;

        $sections = CatalogItem::query()
            ->where('source', $source)
            ->where('is_section', true)
            ->orderBy('n1')
            ->orderBy('n2')
            ->orderBy('n3')
            ->orderBy('n4')
            ->get();

        $subitems = CatalogItem::query()
            ->where('source', $source)
            ->where('is_section', false)
            ->orderBy('n1')
            ->orderBy('n2')
            ->orderBy('n3')
            ->orderBy('n4')
            ->get();

        $linkedToDocument = NcDocumentItem::query()
            ->whereIn('catalog_item_id', array_merge(
                $sections->pluck('id')->all(),
                $subitems->pluck('id')->all(),
            ))
            ->pluck('catalog_item_id')
            ->unique()
            ->all();

        return view('prontuario.catalogo.index', [
            'sections' => $sections,
            'subitems' => $subitems,
            'linkedToDocument' => $linkedToDocument,
            'canDelete' => request()->user()->canDelete(),
        ]);
    }

    public function storeSection(Request $request): RedirectResponse
    {
        $this->authorizeWrite();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $source = Source::Prontuario->value;
        $next = (int) (CatalogItem::query()
            ->where('source', $source)
            ->where('is_section', true)
            ->max('n1') ?? 0) + 1;

        $item = CatalogItem::create([
            'source' => $source,
            'code' => (string) $next,
            'n1' => $next,
            'n2' => 0,
            'n3' => 0,
            'n4' => 0,
            'is_section' => true,
            'title' => $data['title'],
            'description' => '',
            'sort' => $this->nextSort($source),
        ]);

        return redirect()->route('prontuario.catalogo.index')
            ->with('success', 'Seção '.$item->code.' criada no catálogo operacional.');
    }

    public function storeItem(Request $request): RedirectResponse
    {
        $this->authorizeWrite();

        $data = $request->validate([
            'section_id' => ['required', 'integer',
                Rule::exists('catalog_items', 'id')
                    ->where('source', Source::Prontuario->value)
                    ->where('is_section', true),
            ],
            'title' => ['required', 'string', 'max:255'],
        ]);

        $source = Source::Prontuario->value;
        $section = CatalogItem::findOrFail($data['section_id']);

        $next = (int) (CatalogItem::query()
            ->where('source', $source)
            ->where('n1', $section->n1)
            ->where('is_section', false)
            ->max('n2') ?? 0) + 1;

        $item = CatalogItem::create([
            'source' => $source,
            'parent_id' => $section->id,
            'code' => $section->code.'.'.$next,
            'n1' => $section->n1,
            'n2' => $next,
            'n3' => 0,
            'n4' => 0,
            'is_section' => false,
            'title' => $data['title'],
            'description' => '',
            'sort' => $this->nextSort($source),
        ]);

        // Mantém o invariante do catálogo: todo sub-item tem tenant_items por cliente.
        foreach (Tenant::query()->pluck('id') as $tenantId) {
            TenantItem::withoutGlobalScopes()->firstOrCreate([
                'tenant_id' => $tenantId,
                'catalog_item_id' => $item->id,
            ]);
        }

        return redirect()->route('prontuario.catalogo.index')
            ->with('success', 'Sub-item '.$item->code.' criado no catálogo operacional.');
    }

    public function update(Request $request, CatalogItem $catalogItem): RedirectResponse
    {
        $this->authorizeWrite();
        $this->assertProntuarioCatalogItem($catalogItem);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        $catalogItem->update(['title' => $data['title']]);

        return back()->with('success', 'Item '.$catalogItem->code.' atualizado.');
    }

    public function destroy(Request $request, CatalogItem $catalogItem): RedirectResponse
    {
        if (! $request->user()->canDelete()) {
            abort(403);
        }

        $this->assertProntuarioCatalogItem($catalogItem);

        if (NcDocumentItem::query()->where('catalog_item_id', $catalogItem->id)->exists()) {
            return back()->with('error', 'O item '.$catalogItem->code.' está vinculado a um documento de não conformidades. Remova o vínculo antes de excluir.');
        }

        if ($catalogItem->is_section) {
            $hasChildren = CatalogItem::query()
                ->where('source', Source::Prontuario->value)
                ->where('is_section', false)
                ->where('code', 'like', $catalogItem->code.'.%')
                ->exists();

            if ($hasChildren) {
                return back()->with('error', 'A seção '.$catalogItem->code.' ainda possui sub-itens. Exclua os sub-itens antes.');
            }
        }

        $code = $catalogItem->code;
        $catalogItem->delete();

        return back()->with('success', 'Item '.$code.' removido do catálogo operacional.');
    }

    protected function nextSort(string $source): int
    {
        return (int) (CatalogItem::query()->where('source', $source)->max('sort') ?? 0) + 1;
    }

    protected function assertProntuarioCatalogItem(CatalogItem $catalogItem): void
    {
        if ($catalogItem->source !== Source::Prontuario) {
            abort(404);
        }
    }

    protected function authorizeWrite(): void
    {
        if (! request()->user()->canWrite()) {
            abort(403);
        }
    }
}
