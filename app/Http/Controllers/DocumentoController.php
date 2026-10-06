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
                'funcionarioItem.funcionario',
                'uploader',
                'documents',
                'documentItems.tenantItem.catalogItem',
                'tenantItems.catalogItem',
            ])
            ->when($source === 'funcionarios', fn ($q) => $q->whereNotNull('funcionario_item_id'))
            ->when($source && $source !== 'funcionarios', fn ($q) => $q
                ->whereNull('funcionario_item_id')
                ->whereHas('tenantItem.catalogItem', fn ($c) => $c->where('source', $source)))
            ->latest();

        $documents = $query->paginate(25);

        $collection = $documents->getCollection();

        // Rastreabilidade: RNCs que contêm o item ao qual o arquivo está ancorado
        // — a referência por item, além do badge direto de biblioteca
        // (evidence_document). Funcionários e catálogo usam âncoras distintas.
        $referencing = collect();

        foreach ([
            'tenant_item_id' => $collection->pluck('tenant_item_id')->filter(),
            'funcionario_item_id' => $collection->pluck('funcionario_item_id')->filter(),
        ] as $column => $anchorIds) {
            if ($anchorIds->isEmpty()) {
                continue;
            }

            $referencing = $referencing->merge(
                NcDocumentItem::query()
                    ->whereIn($column, $anchorIds)
                    ->with('document')
                    ->get()
                    ->groupBy($column)
                    ->map(fn ($entries) => $entries->pluck('document'))
            );
        }

        return view('documentos.index', [
            'documents' => $documents,
            'referencing' => $referencing,
            'activeSource' => $source,
            'sources' => Source::cases(),
            'canHardDelete' => $request->user()->canDeleteEvidence(),
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

    /**
     * Exclusão de um anexo na Gestão de Documentos (SuperAdmin/Admin/Manager).
     * O arquivo é apagado do disco e a linha some — inclusive quando está preso
     * a um documento finalizado ou a uma RNC publicada.
     */
    public function destroy(Request $request, Evidence $evidence): RedirectResponse
    {
        if (! $request->user()->canDeleteEvidence() || $evidence->tenant_id !== TenantContext::id()) {
            abort(403);
        }

        Storage::disk($evidence->disk ?? 'local')->delete($evidence->stored_path);
        $evidence->delete();

        return back()->with('success', 'Documento removido.');
    }
}
