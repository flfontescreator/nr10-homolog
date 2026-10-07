<?php

namespace App\Http\Controllers;

use App\Models\ClassificacaoRisco;
use App\Models\Criticidade;
use App\Models\Projeto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Administração das CATEGORIAS globais do relatório RNC: projetos,
 * criticidades e classificações de risco. Restrito a Admin/SuperAdmin
 * (diferente do catálogo operacional, que também aceita Manager). Situações
 * e normas técnicas seguem geridas por migrações/seeders.
 */
class RncCategoriaController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin();

        $aba = (string) $request->query('aba', 'projetos');
        $aba = in_array($aba, ['projetos', 'criticidades', 'classificacoes'], true) ? $aba : 'projetos';

        return view('rnc.categoria.index', [
            'aba' => $aba,
            'projetos' => Projeto::query()->orderBy('nome')->get(),
            'criticidades' => Criticidade::query()->orderBy('ordem')->orderBy('nome')->get(),
            'classificacoes' => ClassificacaoRisco::query()->orderBy('ordem')->orderBy('nome')->get(),
        ]);
    }

    // ------------------------------------------------------------------
    // Projetos
    // ------------------------------------------------------------------

    public function storeProjeto(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:160', Rule::unique('projetos', 'nome')],
        ]);

        Projeto::create(['nome' => $data['nome'], 'ativo' => $request->boolean('ativo', true)]);

        return back()->with('success', 'Projeto "'.$data['nome'].'" criado.');
    }

    public function updateProjeto(Request $request, Projeto $projeto): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:160', Rule::unique('projetos', 'nome')->ignore($projeto->id)],
        ]);

        $projeto->update(['nome' => $data['nome'], 'ativo' => $request->boolean('ativo')]);

        return back()->with('success', 'Projeto atualizado.');
    }

    public function destroyProjeto(Request $request, Projeto $projeto): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($projeto->rncs()->exists()) {
            return back()->with('error', 'Não é possível excluir: há RNCs vinculados a este projeto.');
        }

        $projeto->delete();

        return back()->with('success', 'Projeto removido.');
    }

    // ------------------------------------------------------------------
    // Criticidades
    // ------------------------------------------------------------------

    public function storeCriticidade(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:60', Rule::unique('criticidades', 'nome')],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        Criticidade::create(['nome' => $data['nome'], 'ordem' => $data['ordem'] ?? 0]);

        return back()->with('success', 'Criticidade "'.$data['nome'].'" criada.');
    }

    public function updateCriticidade(Request $request, Criticidade $criticidade): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:60', Rule::unique('criticidades', 'nome')->ignore($criticidade->id)],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $criticidade->update(['nome' => $data['nome'], 'ordem' => $data['ordem'] ?? 0]);

        return back()->with('success', 'Criticidade atualizada.');
    }

    public function destroyCriticidade(Request $request, Criticidade $criticidade): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($criticidade->rncItems()->exists()) {
            return back()->with('error', 'Não é possível excluir: há não conformidades usando esta criticidade.');
        }

        $criticidade->delete();

        return back()->with('success', 'Criticidade removida.');
    }

    // ------------------------------------------------------------------
    // Classificações de risco
    // ------------------------------------------------------------------

    public function storeClassificacao(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:60', Rule::unique('classificacoes_risco', 'nome')],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        ClassificacaoRisco::create(['nome' => $data['nome'], 'ordem' => $data['ordem'] ?? 0]);

        return back()->with('success', 'Classificação de risco "'.$data['nome'].'" criada.');
    }

    public function updateClassificacao(Request $request, ClassificacaoRisco $classificacao): RedirectResponse
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'nome' => ['required', 'string', 'max:60', Rule::unique('classificacoes_risco', 'nome')->ignore($classificacao->id)],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $classificacao->update(['nome' => $data['nome'], 'ordem' => $data['ordem'] ?? 0]);

        return back()->with('success', 'Classificação de risco atualizada.');
    }

    public function destroyClassificacao(Request $request, ClassificacaoRisco $classificacao): RedirectResponse
    {
        $this->authorizeAdmin();

        if ($classificacao->rncItems()->exists()) {
            return back()->with('error', 'Não é possível excluir: há não conformidades usando esta classificação.');
        }

        $classificacao->delete();

        return back()->with('success', 'Classificação de risco removida.');
    }

    protected function authorizeAdmin(): void
    {
        abort_unless(request()->user()?->isAdmin(), 403);
    }
}
