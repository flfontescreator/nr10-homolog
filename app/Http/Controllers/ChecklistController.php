<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus as Status;
use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Evidence;
use App\Models\TenantItem;
use App\Support\ChecklistOptions;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChecklistController extends Controller
{
    /**
     * Não Conformidades: itens são planos (sem seções hierárquicas),
     * numerados de 1 a N conforme a planilha legada.
     */
    public function index(): View
    {
        $tenantId = TenantContext::id();
        $items = CatalogItem::query()
            ->where('source', Source::Checklist->value)
            ->where('is_section', false)
            ->orderBy('n1')
            ->orderBy('sort')
            ->get();

        $map = TenantItem::query()
            ->with('catalogItem')
            ->withCount('evidences')
            ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Checklist->value))
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('catalog_item_id');

        $critCounts = [
            'alta' => $map->filter(fn (TenantItem $row) => $row->catalogItem->criticidade === 'ALTA')->count(),
            'media' => $map->filter(fn (TenantItem $row) => $row->catalogItem->criticidade === 'MÉDIA')->count(),
            'pendente' => $map->filter(fn (TenantItem $row) => $row->status === Status::Pendente)->count(),
        ];

        return view('checklist.index', [
            'items' => $items,
            'map' => $map,
            'critCounts' => $critCounts,
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

        return view('checklist.show', [
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
            'data_inspecao' => ['nullable', 'date'],
            'condicao_inicial' => ['nullable', 'string', 'max:40'],
            'descricao_nc' => ['nullable', 'string'],
            'id_relatorio' => ['nullable', 'string', 'max:60'],
            'prazo_adequacao' => ['nullable', 'date'],
            'acao' => ['nullable', 'string'],
            'acao_realizada' => ['nullable', 'string'],
            'data_realizacao' => ['nullable', 'date'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'setores' => ['nullable', 'array'],
            'setores.*' => ['nullable', 'string', Rule::in(ChecklistOptions::setores())],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_keys(Status::options()))],
        ]);

        if ($request->filled('setores')) {
            $setores = array_values(array_unique(array_filter(array_map(
                fn ($setor) => trim((string) $setor),
                (array) $request->input('setores'),
            ))));

            $data['setores'] = $setores ?: null;
        }

        $item->fill($data);
        $item->updated_by = $request->user()->id;
        $item->save();

        return back()->with('success', 'Registro do item '.$item->catalogItem->code.' atualizado.');
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
