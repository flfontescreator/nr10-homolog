<?php

namespace App\Http\Controllers;

use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Funcionario;
use App\Models\NcDocument;
use App\Models\NcDocumentItem;
use App\Models\TenantItem;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FuncionarioController extends Controller
{
    public function index(): View
    {
        $tenantId = TenantContext::id();

        $funcionarios = Funcionario::query()
            ->withCount('items')
            ->where('tenant_id', $tenantId)
            ->orderBy('nome')
            ->get();

        // Contagem total de evidências somando os sub-itens (item 4) de cada funcionário.
        $evidencesByFuncionario = TenantItem::query()
            ->whereNotNull('funcionario_id')
            ->withCount('evidences')
            ->get()
            ->groupBy('funcionario_id')
            ->map->sum('evidences_count');

        return view('funcionarios.index', [
            'funcionarios' => $funcionarios,
            'evidencesByFuncionario' => $evidencesByFuncionario,
            'canWrite' => request()->user()->canWrite(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeWrite();

        return view('funcionarios.create', [
            'backUrl' => $this->documentBackUrl($request),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeWrite();

        $tenantId = TenantContext::id();

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'matricula' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('funcionarios', 'matricula')->where('tenant_id', $tenantId),
            ],
        ]);

        $funcionario = Funcionario::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        $created = $funcionario->bootstrapProntuarioItems();

        // Mantém o contexto do documento quando o cadastro veio dele, para o
        // "Voltar" da página do funcionário retornar ao documento.
        $redirectParams = ['funcionario' => $funcionario];

        if ($request->query('from') === 'prontuario') {
            $redirectParams['from'] = 'prontuario';
        }

        if ($request->query('from') === 'document') {
            $redirectParams['from'] = 'document';

            if ($request->filled('document_id')) {
                $redirectParams['document_id'] = $request->query('document_id');
            }
        }

        return redirect()
            ->route('funcionarios.show', $redirectParams)
            ->with('success', "Funcionário criado com {$created} sub-itens do item 4 do prontuário.");
    }

    public function show(Request $request, Funcionario $funcionario): View
    {
        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        $items = TenantItem::query()
            ->where('funcionario_id', $funcionario->id)
            ->whereHas('catalogItem', fn ($c) => $c->where('source', Source::Prontuario->value)->where('n1', 4))
            ->with('catalogItem')
            ->withCount('evidences')
            ->get()
            ->sortBy(fn ($item) => $item->catalogItem?->n2)
            ->values();

        // Catálogo 4.x disponível: base para adicionar sub-item e para o seletor
        // de "alterar" de cada sub-item (sem duplicar itens já usados pelo funcionário).
        $catalogPool = CatalogItem::query()
            ->where('source', Source::Prontuario->value)
            ->where('n1', 4)
            ->where('is_section', false)
            ->orderBy('n2')
            ->orderBy('n3')
            ->orderBy('n4')
            ->get();

        $assigned = $items->pluck('catalog_item_id');

        $availableToAdd = $catalogPool
            ->reject(fn ($catalog) => $assigned->contains($catalog->id))
            ->values();

        $catalogOptions = [];

        foreach ($items as $item) {
            $usedByOthers = $items->pluck('catalog_item_id')
                ->reject(fn ($id) => $id === $item->catalog_item_id);

            $catalogOptions[$item->id] = $catalogPool
                ->reject(fn ($catalog) => $usedByOthers->contains($catalog->id))
                ->values();
        }

        return view('funcionarios.show', [
            'funcionario' => $funcionario,
            'items' => $items,
            'catalogPool' => $catalogPool,
            'availableToAdd' => $availableToAdd,
            'catalogOptions' => $catalogOptions,
            'canWrite' => $request->user()->canWrite(),
            'canDeleteEvidence' => $request->user()->canDeleteEvidence(),
            'backUrl' => $this->documentBackUrl($request),
        ]);
    }

    public function edit(Request $request, Funcionario $funcionario): View
    {
        $this->authorizeWrite();

        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        return view('funcionarios.edit', ['funcionario' => $funcionario]);
    }

    public function update(Request $request, Funcionario $funcionario): RedirectResponse
    {
        $this->authorizeWrite();

        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'matricula' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('funcionarios', 'matricula')
                    ->where('tenant_id', $funcionario->tenant_id)
                    ->ignore($funcionario->id),
            ],
        ]);

        $funcionario->update($data);

        return redirect()
            ->route('funcionarios.show', $funcionario)
            ->with('success', 'Funcionário atualizado.');
    }

    public function destroy(Request $request, Funcionario $funcionario): RedirectResponse
    {
        $this->authorizeWrite();

        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        $evidences = $funcionario->items()->withCount('evidences')->get()
            ->sum('evidences_count');

        if ($evidences > 0) {
            return back()->withErrors([
                'funcionario' => 'Não é possível excluir: existem evidências anexadas aos sub-itens deste funcionário. Remova as evidências primeiro.',
            ]);
        }

        $linkedCodes = $this->linkedDocumentCodes($funcionario->items()->pluck('id')->all());

        if (! empty($linkedCodes)) {
            return back()->withErrors([
                'funcionario' => $this->linkedBlockMessage('excluir o funcionário', $linkedCodes),
            ]);
        }

        $funcionario->delete();

        return redirect()
            ->route('funcionarios.index')
            ->with('success', 'Funcionário excluído.');
    }

    /**
     * Adiciona um sub-item 4.x do catálogo a este funcionário (cada funcionário
     * pode ter quantidade própria de sub-itens).
     */
    public function storeSubItem(Request $request, Funcionario $funcionario): RedirectResponse
    {
        $this->authorizeWrite();

        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        $data = $request->validate([
            'catalog_item_id' => ['required', 'integer'],
        ]);

        $catalog = CatalogItem::query()
            ->where('id', $data['catalog_item_id'])
            ->where('source', Source::Prontuario->value)
            ->where('n1', 4)
            ->where('is_section', false)
            ->first();

        if (! $catalog) {
            return back()->withErrors(['catalog_item_id' => 'Sub-item inválido do item 4 do prontuário.']);
        }

        if ($funcionario->items()->where('catalog_item_id', $catalog->id)->exists()) {
            return back()->withErrors(['catalog_item_id' => 'Este funcionário já possui o sub-item '.$catalog->code.'.']);
        }

        TenantItem::firstOrCreate([
            'tenant_id' => $funcionario->tenant_id,
            'funcionario_id' => $funcionario->id,
            'catalog_item_id' => $catalog->id,
        ], [
            'code' => $catalog->code,
            'title' => $catalog->title,
            'source' => Source::Prontuario->value,
        ]);

        return back()->with('success', 'Sub-item '.$catalog->code.' adicionado a '.$funcionario->nome.'.');
    }

    /**
     * Altera o sub-item de um funcionário (troca o item do catálogo mapeado).
     * Bloqueado quando o sub-item está vinculado a documentos, apontando quais.
     */
    public function updateSubItem(Request $request, Funcionario $funcionario, TenantItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        if ($funcionario->tenant_id !== TenantContext::id() || $item->funcionario_id !== $funcionario->id) {
            abort(404);
        }

        $data = $request->validate([
            'catalog_item_id' => ['required', 'integer'],
        ]);

        $catalog = CatalogItem::query()
            ->where('id', $data['catalog_item_id'])
            ->where('source', Source::Prontuario->value)
            ->where('n1', 4)
            ->where('is_section', false)
            ->first();

        if (! $catalog) {
            return back()->withErrors(['catalog_item_id' => 'Sub-item inválido do item 4 do prontuário.']);
        }

        if ((int) $item->catalog_item_id === (int) $catalog->id) {
            return back()->with('success', 'O sub-item selecionado já é o atual.');
        }

        $linkedCodes = $this->linkedDocumentCodes([$item->id]);

        if (! empty($linkedCodes)) {
            return back()->withErrors([
                'subitem' => $this->linkedBlockMessage('alterar o sub-item', $linkedCodes),
            ]);
        }

        if ($funcionario->items()->where('catalog_item_id', $catalog->id)->where('id', '!=', $item->id)->exists()) {
            return back()->withErrors(['catalog_item_id' => 'Este funcionário já possui o sub-item '.$catalog->code.'.']);
        }

        $item->update([
            'catalog_item_id' => $catalog->id,
            'code' => $catalog->code,
            'title' => $catalog->title,
            'source' => Source::Prontuario->value,
        ]);

        return back()->with('success', 'Sub-item alterado para '.$catalog->code.'.');
    }

    /**
     * Exclui um sub-item do funcionário. Bloqueado se houver evidências ou
     * vínculo com documentos — apontando quais documentos prendem o item.
     */
    public function destroySubItem(Request $request, Funcionario $funcionario, TenantItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        if ($funcionario->tenant_id !== TenantContext::id() || $item->funcionario_id !== $funcionario->id) {
            abort(404);
        }

        $linkedCodes = $this->linkedDocumentCodes([$item->id]);

        if (! empty($linkedCodes)) {
            return back()->withErrors([
                'subitem' => $this->linkedBlockMessage('excluir o sub-item', $linkedCodes),
            ]);
        }

        if ($item->evidences()->exists()) {
            return back()->withErrors([
                'subitem' => 'Não é possível excluir: este sub-item possui evidências anexadas. Remova as evidências primeiro.',
            ]);
        }

        $code = $item->display_code;

        $item->delete();

        return back()->with('success', 'Sub-item '.$code.' excluído.');
    }

    /**
     * Média geral dos sub-itens 4.x deste funcionário.
     */
    public static function averagePercent(Funcionario $funcionario): ?float
    {
        $values = TenantItem::query()
            ->where('tenant_items.funcionario_id', $funcionario->id)
            ->whereNotNull('tenant_items.percentual')
            ->pluck('tenant_items.percentual');

        if ($values->isEmpty()) {
            return null;
        }

        return round($values->avg(), 2);
    }

    /**
     * Códigos dos documentos (RNC-XXXXX) que vinculam os sub-itens informados.
     */
    protected function linkedDocumentCodes(array $tenantItemIds): array
    {
        return NcDocumentItem::query()
            ->whereIn('tenant_item_id', array_values(array_filter($tenantItemIds)))
            ->with('document')
            ->get()
            ->pluck('document.code')
            ->unique()
            ->values()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Mensagem de bloqueio que APONTA os documentos vinculados ao usuário.
     */
    protected function linkedBlockMessage(string $action, array $codes): string
    {
        $list = implode(', ', $codes);

        if (count($codes) === 1) {
            return "Não é possível {$action}: o item está vinculado ao documento {$list}. Remova o vínculo primeiro.";
        }

        return "Não é possível {$action}: o item está vinculado aos documentos {$list}. Remova os vínculos primeiro.";
    }

    protected function authorizeWrite(): void
    {
        if (! request()->user()->canWrite()) {
            abort(403);
        }
    }

    /**
     * URL de retorno quando o cadastro/visualização do funcionário veio de um
     * documento de não conformidades (?from=document): o "Voltar" leva de volta
     * à edição daquele documento (ou ao formulário de novo documento). Sem o
     * contexto de documento, retorna null (caindo no padrão, funcionarios.index).
     */
    protected function documentBackUrl(Request $request): ?string
    {
        if ($request->query('from') === 'prontuario') {
            return route('prontuario.index');
        }

        if ($request->query('from') !== 'document') {
            return null;
        }

        $documentId = $request->query('document_id');

        if ($documentId !== null && $documentId !== '') {
            $document = NcDocument::query()->find($documentId);

            if ($document && $document->tenant_id === TenantContext::id()) {
                return route('nc-documents.edit', $document);
            }

            return null;
        }

        return route('nc-documents.create');
    }
}
