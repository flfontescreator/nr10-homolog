<?php

namespace App\Http\Controllers;

use App\Enums\Source;
use App\Models\Funcionario;
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

    public function create(): View
    {
        $this->authorizeWrite();

        return view('funcionarios.create');
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

        return redirect()
            ->route('funcionarios.show', $funcionario)
            ->with('success', "Funcionário criado com {$created} sub-itens do item 4 do prontuário.");
    }

    public function show(Request $request, Funcionario $funcionario): View
    {
        if ($funcionario->tenant_id !== TenantContext::id()) {
            abort(404);
        }

        $funcionario->bootstrapProntuarioItems();

        $items = TenantItem::query()
            ->where('funcionario_id', $funcionario->id)
            ->whereHas('catalogItem', fn ($c) => $c->where('source', Source::Prontuario->value)->where('n1', 4))
            ->with('catalogItem')
            ->withCount('evidences')
            ->get()
            ->sortBy(fn ($item) => $item->catalogItem?->n2)
            ->values();

        return view('funcionarios.show', [
            'funcionario' => $funcionario,
            'items' => $items,
            'canWrite' => $request->user()->canWrite(),
            'canDeleteEvidence' => $request->user()->canDeleteEvidence(),
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

        $funcionario->delete();

        return redirect()
            ->route('funcionarios.index')
            ->with('success', 'Funcionário excluído.');
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

    protected function authorizeWrite(): void
    {
        if (! request()->user()->canWrite()) {
            abort(403);
        }
    }
}
