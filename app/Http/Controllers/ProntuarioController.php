<?php

namespace App\Http\Controllers;

use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Evidence;
use App\Models\Funcionario;
use App\Models\TenantItem;
use App\Support\CronogramaOptions;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProntuarioController extends Controller
{
    public function index(): View
    {
        $tenantId = TenantContext::id();
        $tree = CatalogItem::tree(Source::Prontuario);

        $map = TenantItem::query()
            ->with('catalogItem')
            ->withCount('evidences')
            ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Prontuario->value))
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('catalog_item_id');

        $averages = [];
        foreach ($tree as $branch) {
            $scope = $branch->section->n1 === 4 ? 'any' : null;
            $averages[$branch->section->n1] = TenantItem::averagePercent($tenantId, Source::Prontuario->value, $branch->section->n1, $scope);
        }

        // Item 4 no grid do prontuário: cada funcionário com seus sub-itens 4.x
        // (evidências, status e percentual próprios — "1 funcionário → N subitens").
        $funcionarios = Funcionario::query()
            ->where('tenant_id', $tenantId)
            ->with(['prontuarioItems' => fn ($q) => $q->orderBy('catalog_item_id')->withCount('evidences')])
            ->orderBy('nome')
            ->get();

        return view('prontuario.index', [
            'tree' => $tree,
            'map' => $map,
            'averages' => $averages,
            'funcionariosTotal' => Funcionario::query()->where('tenant_id', $tenantId)->count(),
            'funcionarios' => $funcionarios,
            'canWrite' => request()->user()->canWrite(),
        ]);
    }

    public function show(Request $request, TenantItem $item): View|RedirectResponse
    {
        $tenantId = TenantContext::id();
        if ($item->tenant_id !== $tenantId) {
            abort(403);
        }

        $item->load(['catalogItem', 'evidences.uploader', 'funcionario']);

        return view('prontuario.show', [
            'item' => $item,
            'canWrite' => $request->user()->canWrite(),
            'canDeleteEvidence' => $request->user()->canDeleteEvidence(),
        ]);
    }

    public function update(Request $request, TenantItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        $tenantId = TenantContext::id();
        if ($item->tenant_id !== $tenantId) {
            abort(403);
        }

        $data = $request->validate([
            'evidencias_status' => ['nullable', 'string', 'max:30', 'in:Digital,Pendente,Nao Aplicado'],
            'condicao_inicial' => ['nullable', 'string', 'max:30', Rule::in(CronogramaOptions::condicoesIniciais())],
            'criticidade' => ['nullable', 'string', Rule::in(CronogramaOptions::criticidades())],
            'data_realizacao' => ['nullable', 'date'],
            'data_validade' => ['nullable', 'date'],
            'percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comentarios' => ['nullable', 'string'],
            'prazo_execucao' => ['nullable', 'date'],
        ]);

        $item->fill($data);
        $item->updated_by = $request->user()->id;
        $item->save();

        return back()->with('success', 'Registro do subitem '.$item->catalogItem->code.' atualizado.');
    }

    public function uploadEvidence(Request $request, TenantItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        $tenantId = TenantContext::id();
        if ($item->tenant_id !== $tenantId) {
            abort(403);
        }

        $request->validate([
            'evidence' => ['required', 'file', 'max:20480'],
        ]);

        $file = $request->file('evidence');
        $this->guardSafeFile($file);

        $stored = $file->store('evidences/tenant-'.$tenantId, ['disk' => 'local']);

        Evidence::create([
            'tenant_id' => $tenantId,
            'tenant_item_id' => $item->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $stored,
            'disk' => 'local',
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        return back()->with('success', 'Evidência anexada com sucesso.');
    }

    public function destroyEvidence(Request $request, Evidence $evidence): RedirectResponse
    {
        if (! $request->user()->canDeleteEvidence() || $evidence->tenant_id !== TenantContext::id()) {
            abort(403);
        }

        Storage::disk($evidence->disk ?? 'local')->delete($evidence->stored_path);
        $evidence->delete();

        return back()->with('success', 'Evidência removida.');
    }

    protected function guardSafeFile($file): void
    {
        $blocked = ['php', 'phar', 'exe', 'bat', 'cmd', 'com', 'msi', 'sh', 'bin', 'dll', 'scr', 'pif', 'cpl', 'js', 'jsp'];
        $ext = strtolower(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION));

        if ($ext !== '' && in_array($ext, $blocked, true)) {
            throw ValidationException::withMessages([
                'evidence' => 'Arquivos executáveis não são permitidos por segurança.',
            ]);
        }
    }

    protected function authorizeWrite(): void
    {
        if (! request()->user()->canWrite()) {
            abort(403);
        }
    }
}
