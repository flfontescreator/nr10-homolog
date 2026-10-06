<?php

namespace App\Http\Controllers;

use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Evidence;
use App\Models\TenantItem;
use App\Support\CronogramaOptions;
use App\Support\EvidenciaUploadService;
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

        // Itens avulsos: linhas de trabalho criadas a partir de itens do catálogo
        // que foram EXCLUÍDOS da base. Continuam no prontuário do cliente com a
        // cópia congelada (código/título), editáveis normalmente.
        $avulso = TenantItem::query()
            ->withCount('evidences')
            ->where('tenant_id', $tenantId)
            ->whereNull('catalog_item_id')
            ->where('source', Source::Prontuario->value)
            ->orderBy('code')
            ->get();

        $averages = [];
        foreach ($tree as $branch) {
            $averages[$branch->section->n1] = TenantItem::averagePercent($tenantId, Source::Prontuario->value, $branch->section->n1);
        }

        return view('prontuario.index', [
            'tree' => $tree,
            'map' => $map,
            'avulso' => $avulso,
            'averages' => $averages,
            'canWrite' => request()->user()->canWrite(),
        ]);
    }

    public function show(Request $request, TenantItem $item): View|RedirectResponse
    {
        $tenantId = TenantContext::id();
        if ($item->tenant_id !== $tenantId) {
            abort(403);
        }

        $item->load(['catalogItem', 'evidences.uploader']);

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
            'validade_aplica' => ['nullable', 'boolean'],
            'data_validade' => ['nullable', 'date'],
            'percentual' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comentarios' => ['nullable', 'string'],
            'prazo_execucao' => ['nullable', 'date'],
        ]);

        // Checkbox "Se aplica" é a fonte da verdade do campo: desmarcado
        // limpa a data, marcado exige a data.
        $data['validade_aplica'] = $request->boolean('validade_aplica');

        if ($data['validade_aplica'] && empty($data['data_validade'])) {
            throw ValidationException::withMessages([
                'data_validade' => 'Informe a data de validade ou desmarque "Se aplica".',
            ]);
        }

        $data['data_validade'] = $data['validade_aplica'] ? $data['data_validade'] : null;

        $item->fill($data);
        $item->updated_by = $request->user()->id;
        $item->save();

        return back()->with('success', 'Registro do subitem '.$item->display_code.' atualizado.');
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
            'description' => ['nullable', 'string', 'max:255'],
            'validade' => ['nullable', 'date'],
        ]);

        EvidenciaUploadService::storeForTenantItem(
            $request->file('evidence'),
            $item,
            $request->user(),
            $request->filled('description') ? $request->string('description')->toString() : null,
            EvidenciaUploadService::resolveValidade($request),
            EvidenciaUploadService::MODULO_PRONTUARIO,
        );

        return back()->with('success', 'Evid�ncia anexada com sucesso.');
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
