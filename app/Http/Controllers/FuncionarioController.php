<?php

namespace App\Http\Controllers;

use App\Models\Evidence;
use App\Models\Funcionario;
use App\Models\FuncionarioItem;
use App\Models\FuncionarioSituacao;
use App\Models\NcDocument;
use App\Models\Situacao;
use App\Support\Cpf;
use App\Support\EvidenciaUploadService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FuncionarioController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = TenantContext::id();

        $funcionarios = Funcionario::query()
            ->where('tenant_id', $tenantId)
            ->with('situacao')
            ->withCount('items')
            ->orderBy('nome')
            ->get();

        $evidencesByFuncionario = FuncionarioItem::query()
            ->where('tenant_id', $tenantId)
            ->withCount('evidences')
            ->get()
            ->groupBy('funcionario_id')
            ->map->sum('evidences_count');

        return view('funcionarios.index', [
            'funcionarios' => $funcionarios,
            'evidencesByFuncionario' => $evidencesByFuncionario,
            'canWrite' => $request->user()->canWrite(),
            'canHardDelete' => $this->canHardDelete($request),
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

        $this->normalizeCpfInput($request);

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'matricula' => [
                'nullable',
                'string',
                'max:60',
                Rule::unique('funcionarios', 'matricula')->where('tenant_id', $tenantId),
            ],
            'cpf' => [
                'nullable',
                Rule::unique('funcionarios', 'cpf')->where('tenant_id', $tenantId),
            ],
            'data_admissao' => ['nullable', 'date'],
        ]);

        $data['cpf'] = Cpf::validateOrFail($data['cpf'] ?? null);

        // A Situação de vínculo não é escolhida na tela: todo funcionário novo
        // entra como Ativo. O valor pode ser ajustado direto no banco.
        $data['situacao_id'] = FuncionarioSituacao::default()?->id;

        $funcionario = Funcionario::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        $redirectParams = ['funcionario' => $funcionario];

        foreach (['prontuario', 'document'] as $from) {
            if ($request->query('from') === $from) {
                $redirectParams['from'] = $from;

                if ($from === 'document' && $request->filled('document_id')) {
                    $redirectParams['document_id'] = $request->query('document_id');
                }
            }
        }

        return redirect()
            ->route('funcionarios.show', $redirectParams)
            ->with('success', 'Funcionário criado. Agora são possíveis adicionar os itens de documentação.');
    }

    public function show(Request $request, Funcionario $funcionario): View
    {
        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        $items = $funcionario->items()
            ->with('situacao')
            ->withCount('evidences')
            ->get();

        return view('funcionarios.show', [
            'funcionario' => $funcionario,
            'items' => $items,
            'situacoes' => Situacao::query()->orderBy('ordem')->get(),
            'canWrite' => $request->user()->canWrite(),
            'canHardDelete' => $this->canHardDelete($request),
            'backUrl' => $this->documentBackUrl($request),
        ]);
    }

    public function edit(Request $request, Funcionario $funcionario): View
    {
        $this->authorizeWrite();

        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        return view('funcionarios.edit', [
            'funcionario' => $funcionario,
        ]);
    }

    public function update(Request $request, Funcionario $funcionario): RedirectResponse
    {
        $this->authorizeWrite();

        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        $this->normalizeCpfInput($request);

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
            'cpf' => [
                'nullable',
                Rule::unique('funcionarios', 'cpf')
                    ->where('tenant_id', $funcionario->tenant_id)
                    ->ignore($funcionario->id),
            ],
            'data_admissao' => ['nullable', 'date'],
        ]);

        $data['cpf'] = Cpf::validateOrFail($data['cpf'] ?? null);

        // A Situação não é editável pela tela: preserva o valor gravado.
        $funcionario->update($data);

        return redirect()
            ->route('funcionarios.show', $funcionario)
            ->with('success', 'Funcionário atualizado.');
    }

    /**
     * Exclusão definitiva do funcionário e dos seus itens. As evidências NÃO
     * são apagadas: elas são desvinculadas e continuam disponíveis na Gestão de
     * Documentos. Disponível para Gestor/Admin/SuperAdmin.
     */
    public function destroy(Request $request, Funcionario $funcionario): RedirectResponse
    {
        if (! $this->canHardDelete($request)) {
            abort(403);
        }

        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        $nome = $funcionario->nome;

        foreach ($funcionario->items()->with('evidences')->get() as $item) {
            $item->evidences()->update(['funcionario_item_id' => null]);

            $item->delete();
        }

        $funcionario->delete();

        return redirect()
            ->route('funcionarios.index')
            ->with('success', "Funcionário {$nome} excluído. As evidências permanecem na Gestão de Documentos.");
    }

    /**
     * Adiciona um item de documentação. A numeração é sequencial por
     * funcionário e nunca reusada.
     */
    public function storeItem(Request $request, Funcionario $funcionario): RedirectResponse
    {
        $this->authorizeWrite();

        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:5000'],
        ]);

        $defaultSituacao = Situacao::default();

        $item = FuncionarioItem::create([
            'tenant_id' => $funcionario->tenant_id,
            'funcionario_id' => $funcionario->id,
            'numero' => $funcionario->nextItemNumber(),
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'situacao_id' => $defaultSituacao?->id,
        ]);

        return redirect()
            ->route('funcionarios.item.show', [$funcionario, $item])
            ->with('success', 'Item '.$item->numero.' criado.');
    }

    /**
     * Tela de um item: campos de controle + evidências anexadas.
     */
    public function showItem(Request $request, Funcionario $funcionario, FuncionarioItem $item): View
    {
        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        $this->authorizeItem($funcionario, $item);

        $item->load('situacao');
        $evidences = $item->evidences()->latest()->get();

        return view('funcionarios.item', [
            'funcionario' => $funcionario,
            'item' => $item,
            'situacoes' => Situacao::query()->orderBy('ordem')->get(),
            'evidences' => $evidences,
            'canWrite' => $request->user()->canWrite(),
            'canDeleteEvidence' => $request->user()->canDeleteEvidence(),
        ]);
    }

    /**
     * Atualiza os campos de controle do item. O checkbox "Se aplica" é a
     * fonte da verdade da validade: desmarcado limpa a data, marcado exige data.
     */
    public function updateItem(Request $request, Funcionario $funcionario, FuncionarioItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        $this->authorizeItem($funcionario, $item);

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string', 'max:5000'],
            'situacao_id' => ['nullable', 'integer', Rule::exists('situacoes', 'id')],
            'prazo_adequacao' => ['nullable', 'date'],
            'data_adequacao' => ['nullable', 'date'],
            'data_verificacao' => ['nullable', 'date'],
            'comentario' => ['nullable', 'string', 'max:5000'],
        ]);

        $item->fill([
            'titulo' => $data['titulo'],
            'descricao' => $data['descricao'] ?? null,
            'situacao_id' => $data['situacao_id'] ?? null,
            'prazo_adequacao' => $data['prazo_adequacao'] ?? null,
            'data_adequacao' => $data['data_adequacao'] ?? null,
            'data_verificacao' => $data['data_verificacao'] ?? null,
            'comentario' => $data['comentario'] ?? null,
        ]);

        $item->updated_by = $request->user()->id;
        $item->save();

        return back()->with('success', 'Item '.$item->numero.' atualizado.');
    }

    public function destroyItem(Request $request, Funcionario $funcionario, FuncionarioItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        $this->authorizeItem($funcionario, $item);

        $numero = $item->numero;

        // As evidências não são apagadas: desvincula do item (que deixa de
        // existir) para que continuem disponíveis na Gestão de Documentos.
        $item->evidences()->update(['funcionario_item_id' => null]);

        $item->delete();

        return redirect()
            ->route('funcionarios.show', $funcionario)
            ->with('success', 'Item '.$numero.' excluído. As evidências permanecem na Gestão de Documentos.');
    }

    /**
     * Evidência do item do funcionário: arquivo + descrição obrigatória e
     * validade com "Se aplica" (nem todo documento tem prazo de validade).
     */
    public function uploadItemEvidence(Request $request, Funcionario $funcionario, FuncionarioItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        $this->authorizeItem($funcionario, $item);

        $validade = EvidenciaUploadService::resolveValidade($request, 'evidence-validade');

        $request->validate([
            'evidence' => ['required', 'file', 'max:20480'],
            'description' => ['required', 'string', 'max:255'],
            'validade' => ['nullable', 'date'],
        ]);

        EvidenciaUploadService::storeForFuncionarioItem(
            $request->file('evidence'),
            $item,
            $request->user(),
            $request->string('description')->toString(),
            $validade,
        );

        return back()->with('success', 'Evidência anexada com sucesso.');
    }

    public function destroyItemEvidence(Request $request, Funcionario $funcionario, FuncionarioItem $item, Evidence $evidence): RedirectResponse
    {
        if (! $request->user()->canDeleteEvidence()) {
            abort(403);
        }

        $this->authorizeItem($funcionario, $item);

        if ((int) $evidence->funcionario_item_id !== (int) $item->id) {
            abort(404);
        }

        EvidenciaUploadService::deleteEvidence($evidence);

        return back()->with('success', 'Evidência removida.');
    }

    protected function authorizeItem(Funcionario $funcionario, FuncionarioItem $item): void
    {
        if ($funcionario->tenant_id !== TenantContext::id() || (int) $item->funcionario_id !== (int) $funcionario->id) {
            abort(404);
        }
    }

    protected function authorizeWrite(): void
    {
        if (! request()->user()->canWrite()) {
            abort(403);
        }
    }

    /**
     * Exclusão definitiva do funcionário: Gestor/Admin/SuperAdmin.
     */
    protected function canHardDelete(Request $request): bool
    {
        return $request->user()->canWrite();
    }

    /**
     * O checkbox "Se aplica" é a fonte da verdade da validade da evidência
     * (desmarcado limpa a data, marcado exige data) — comportamento comum a
     * todos os módulos via `EvidenciaUploadService::resolveValidade()`.
     */

    /**
     * Tira a máscara do CPF ANTES das regras de validação: o `unique` consulta o
     * banco com o valor recebido, e lá o CPF está gravado só com dígitos.
     */
    protected function normalizeCpfInput(Request $request): void
    {
        $cpf = $request->input('cpf');

        if (is_string($cpf) && $cpf !== '') {
            $request->merge(['cpf' => Cpf::normalize($cpf) ?? $cpf]);
        }
    }

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
