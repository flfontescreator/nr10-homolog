<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus as Status;
use App\Models\Evidence;
use App\Models\NcDocument;
use App\Models\NcDocumentItem;
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
     * Não Conformidades: lista os documentos do cliente. Cada documento
     * contém a seleção personalizada de itens do Cronograma de Adequação.
     */
    public function index(): View
    {
        $tenantId = TenantContext::id();

        $documents = NcDocument::query()
            ->with('creator')
            ->withCount('items')
            ->orderBy('number')
            ->get();

        $pendingTotal = NcDocumentItem::query()
            ->whereHas('document', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('tenant_item_id')
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'Concluído'))
            ->count();

        // Fontes (Normativa/Operacional) presentes em cada documento, para os
        // badges do grid distinguirem o tipo de relatório por linha.
        $documentSources = NcDocumentItem::query()
            ->whereIn('document_id', $documents->pluck('id'))
            ->join('catalog_items', 'catalog_items.id', '=', 'nc_document_items.catalog_item_id')
            ->select('nc_document_items.document_id', 'catalog_items.source')
            ->distinct()
            ->get()
            ->groupBy('document_id')
            ->map(fn ($rows) => $rows->pluck('source')->values());

        return view('checklist.index', [
            'documents' => $documents,
            'pendingTotal' => $pendingTotal,
            'documentSources' => $documentSources,
            'canWrite' => request()->user()->canWrite(),
            'canDelete' => request()->user()->canDelete(),
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

        // Presente → como enviado ([] = zerado de propósito); ausente + picker
        // exibia setores (usuário removeu todos) → [] persistido; senão não mexe
        // (mantém null = segue o catálogo).
        if ($request->has('setores')) {
            $setores = array_values(array_unique(array_filter(array_map(
                fn ($setor) => trim((string) $setor),
                (array) $request->input('setores', []),
            ))));

            $data['setores'] = $setores;
        } elseif ($item->setores_list !== []) {
            $data['setores'] = [];
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
