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

        // Numeração: item novo assume o MENOR número livre. Como as exclusões
        // renumeram o grupo (4.2 vira 4.1, ...), normalmente não há lacunas e o
        // novo item vai para o final (ex.: após excluir o 4.1 -> próximo é 4.10).
        $used = CatalogItem::query()
            ->where('source', $source)
            ->where('n1', $section->n1)
            ->where('is_section', false)
            ->pluck('n2')
            ->map(fn ($n2) => (int) $n2)
            ->all();

        $next = 1;

        while (in_array($next, $used, true)) {
            $next++;
        }

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
        $isSection = $catalogItem->is_section;
        $sectionN1 = (int) $catalogItem->n1;

        $catalogItem->delete();

        // Renumeração do grupo após a exclusão: a lacuna é fechada (4.2 vira 4.1,
        // 4.3 vira 4.2, ...) e um novo item passa a ir para o final.
        if ($isSection) {
            $this->renumberSections($sectionN1);
        } else {
            $this->renumberSubitems($sectionN1);
        }

        return back()->with('success', 'Item '.$code.' removido do catálogo operacional.');
    }

    /**
     * Renumera as seções após uma exclusão: cada seção seguinte assume o número
     * anterior liberado (9 vira 8, 10 vira 9, ...) junto com os sub-itens dela.
     */
    protected function renumberSections(int $deletedN1): void
    {
        $sections = CatalogItem::query()
            ->where('source', Source::Prontuario->value)
            ->where('is_section', true)
            ->orderBy('n1')
            ->get();

        $seq = 1;

        foreach ($sections as $section) {
            $old = (int) $section->n1;
            $new = $seq++;

            if ($new === $old) {
                continue;
            }

            $section->update([
                'n1' => $new,
                'code' => (string) $new,
            ]);

            CatalogItem::query()
                ->where('source', Source::Prontuario->value)
                ->where('is_section', false)
                ->where('code', 'like', $old.'.%')
                ->get()
                ->each(function (CatalogItem $child) use ($old, $new) {
                    $child->update([
                        'n1' => $new,
                        'code' => $new.'.'.substr($child->code, strlen((string) $old) + 1),
                    ]);
                });
        }
    }

    /**
     * Renumera os sub-itens de uma seção após uma exclusão: os itens seguintes
     * sobem para preencher a lacuna (4.3 vira 4.2, 4.4 vira 4.3, ...).
     */
    protected function renumberSubitems(int $sectionN1): void
    {
        $section = CatalogItem::query()
            ->where('source', Source::Prontuario->value)
            ->where('is_section', true)
            ->where('n1', $sectionN1)
            ->first();

        if (! $section) {
            return;
        }

        $seq = 1;

        CatalogItem::query()
            ->where('source', Source::Prontuario->value)
            ->where('n1', $sectionN1)
            ->where('is_section', false)
            ->orderBy('n2')
            ->orderBy('n3')
            ->orderBy('n4')
            ->get()
            ->each(function (CatalogItem $sub) use (&$seq, $sectionN1) {
                if ((int) $sub->n2 === $seq) {
                    $seq++;

                    return;
                }

                $sub->update([
                    'n1' => $sectionN1,
                    'n2' => $seq,
                    'n3' => 0,
                    'n4' => 0,
                    'code' => $sectionN1.'.'.$seq,
                ]);

                $seq++;
            });
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
