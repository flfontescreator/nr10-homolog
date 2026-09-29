<?php

namespace App\Http\Controllers;

use App\Enums\Source;
use App\Models\Evidence;
use App\Models\NcDocumentItem;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentoController extends Controller
{
    /**
     * Gestão de Documentos: lista todos os arquivos anexados como evidências do cliente ativo.
     */
    public function index(Request $request): View
    {
        $source = $request->query('source');

        $query = Evidence::query()
            ->with([
                'tenantItem.catalogItem',
                'tenantItem.funcionario',
                'uploader',
                'documents',
                'documentItems.tenantItem.catalogItem',
                'tenantItems.catalogItem',
            ])
            ->when($source, fn ($q) => $q->whereHas(
                'tenantItem.catalogItem',
                fn ($c) => $c->where('source', $source)
            ))
            ->latest();

        $documents = $query->paginate(25);

        // Rastreabilidade: RNCs que contêm o sub-item (ex.: 4.x de funcionário) ao
        // qual o arquivo está ancorado — a referência por sub-item, além do badge
        // direto de biblioteca (evidence_document).
        $referencing = NcDocumentItem::query()
            ->whereIn('tenant_item_id', $documents->getCollection()->pluck('tenant_item_id')->filter())
            ->with('document')
            ->get()
            ->groupBy('tenant_item_id')
            ->map(fn ($entries) => $entries->pluck('document'));

        return view('documentos.index', [
            'documents' => $documents,
            'referencing' => $referencing,
            'activeSource' => $source,
            'sources' => Source::cases(),
        ]);
    }

    public function download(Request $request, Evidence $evidence)
    {
        if ($evidence->tenant_id !== TenantContext::id()) {
            abort(403);
        }

        $path = Storage::disk($evidence->disk ?? 'local')->path($evidence->stored_path);

        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path, $evidence->original_name);
    }

    /**
     * Exibe o arquivo inline (mini-preview de imagens nas evidências).
     */
    public function preview(Request $request, Evidence $evidence)
    {
        if ($evidence->tenant_id !== TenantContext::id()) {
            abort(403);
        }

        $path = Storage::disk($evidence->disk ?? 'local')->path($evidence->stored_path);

        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function destroy(Request $request, Evidence $evidence): RedirectResponse
    {
        if (! $request->user()->canDeleteEvidence() || $evidence->tenant_id !== TenantContext::id()) {
            abort(403);
        }

        if ($evidence->linkedToFinalizedDocument()) {
            abort(403);
        }

        Storage::disk($evidence->disk ?? 'local')->delete($evidence->stored_path);
        $evidence->delete();

        return back()->with('success', 'Documento removido.');
    }
}
