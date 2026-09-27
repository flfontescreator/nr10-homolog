<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus as Status;
use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Evidence;
use App\Models\EvidenceDocument;
use App\Models\EvidenceDocumentItem;
use App\Models\EvidenceTenantItem;
use App\Models\NcDocument;
use App\Models\NcDocumentItem;
use App\Models\TenantItem;
use App\Support\Audit;
use App\Support\CronogramaOptions;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CronogramaController extends Controller
{
    /**
     * Lista o Cronograma de Adequação: seções da NR-10 com seus subitens e campos de controle.
     */
    public function index(): View
    {
        $tenantId = TenantContext::id();
        $tree = CatalogItem::tree(Source::Cronograma);

        $map = TenantItem::query()
            ->with('catalogItem')
            ->withCount(['evidences' => function ($q) {
                $q->whereDoesntHave('documentItems')
                    ->whereDoesntHave('tenantItems');
            }])
            ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Cronograma->value))
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('catalog_item_id');

        // Arquivos reutilizados da biblioteca no plano somam na contagem do card
        // (filtrando a âncora do mesmo item para não contar o arquivo 2x).
        $pivotCounts = EvidenceTenantItem::query()
            ->selectRaw('tenant_item_id, count(*) as total')
            ->groupBy('tenant_item_id')
            ->pluck('total', 'tenant_item_id');

        $map->each(function ($row) use ($pivotCounts) {
            $row->setAttribute('evidences_count', (int) $row->evidences_count + (int) ($pivotCounts[$row->id] ?? 0));
        });

        return view('cronograma.index', [
            'tree' => $tree,
            'map' => $map,
            'canWrite' => request()->user()->canWrite(),
        ]);
    }

    public function show(Request $request, TenantItem $item): View|RedirectResponse
    {
        $tenantId = TenantContext::id();
        if ($item->tenant_id !== $tenantId) {
            abort(403);
        }

        $item->load(['catalogItem']);

        $document = $this->documentContext($request);
        $working = $this->workingUnit($document, $item);
        $locked = $this->isLocked($document, $working);

        $evidences = Evidence::query()
            ->with('uploader')
            ->when(
                $working instanceof NcDocumentItem,
                fn ($q) => $q->whereHas('documentItems', fn ($i) => $i->where('nc_document_item_id', $working->id)),
            )
            ->when(
                $working instanceof TenantItem,
                fn ($q) => $q->where(function ($inner) use ($item) {
                    $inner->where('tenant_item_id', $item->id)
                        ->whereDoesntHave('documentItems')
                        ->orWhereHas('tenantItems', fn ($i) => $i->where('tenant_item_id', $item->id));
                }),
            )
            ->orderByDesc('id')
            ->get();

        $backUrl = route('cronograma.index');

        $uploadRoute = route('cronograma.evidencia.upload', $item);

        $libraryAttachRoute = route('cronograma.biblioteca.attach', $item);

        $destroyRouteParams = [];

        if ($document) {
            $backUrl = route('nc-documents.show', $document);
            $uploadRoute = route('cronograma.evidencia.upload', [$item, 'from' => 'document', 'document_id' => $document->id]);
            $libraryAttachRoute = route('cronograma.biblioteca.attach', [$item, 'from' => 'document', 'document_id' => $document->id]);
            $destroyRouteParams = ['from' => 'document', 'document_id' => $document->id];
        }

        $library = Evidence::query()->with(['uploader', 'documents'])->orderByDesc('id')->get();

        $linkedIds = $working instanceof NcDocumentItem
            ? EvidenceDocumentItem::query()->where('nc_document_item_id', $working->id)->pluck('evidence_id')->all()
            : array_merge(
                EvidenceTenantItem::query()->where('tenant_item_id', $item->id)->pluck('evidence_id')->all(),
                Evidence::query()->where('tenant_item_id', $item->id)->whereDoesntHave('documentItems')->pluck('id')->all(),
            );

        $libraryAvailable = $library->whereNotIn('id', $linkedIds)->values();

        return view('cronograma.show', [
            'item' => $item,
            'working' => $working,
            'evidences' => $evidences,
            'canWrite' => $request->user()->canWrite() && ! $locked,
            'canDeleteEvidence' => $request->user()->canDeleteEvidence() && ! $locked,
            'lockedByDocument' => $locked,
            'backUrl' => $backUrl,
            'uploadRoute' => $uploadRoute,
            'libraryAttachRoute' => $libraryAttachRoute,
            'libraryAvailable' => $libraryAvailable,
            'destroyRouteParams' => $destroyRouteParams,
        ]);
    }

    public function update(Request $request, TenantItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        $tenantId = TenantContext::id();
        if ($item->tenant_id !== $tenantId) {
            abort(403);
        }

        $document = $this->documentContext($request);
        $working = $this->workingUnit($document, $item);

        if ($this->isLocked($document, $working)) {
            abort(403);
        }

        $data = $request->validate([
            'data_inspecao' => ['nullable', 'date'],
            'condicao_inicial' => ['nullable', 'string', Rule::in(CronogramaOptions::condicoesIniciais())],
            'setor' => ['nullable', 'string', Rule::in(CronogramaOptions::setores())],
            'setores' => ['nullable', 'array'],
            'setores.*' => ['nullable', 'string', Rule::in(CronogramaOptions::setores())],
            'criticidade' => ['nullable', 'string', Rule::in(CronogramaOptions::criticidades())],
            'descricao_nc' => ['nullable', 'string'],
            'id_relatorio' => ['nullable', 'string', 'max:60'],
            'prazo_adequacao' => ['nullable', 'date'],
            'acao' => ['nullable', 'string'],
            'acao_realizada' => ['nullable', 'string'],
            'data_realizacao' => ['nullable', 'date'],
            'responsavel' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_keys(Status::options()))],
        ]);

        if ($request->filled('setores')) {
            $setores = array_values(array_unique(array_filter(array_map(
                fn ($setor) => trim((string) $setor),
                (array) $request->input('setores'),
            ))));
            $data['setores'] = $setores ?: null;
        } elseif ($request->filled('setor')) {
            $data['setores'] = [trim((string) $request->input('setor'))];
        }

        if ($working instanceof NcDocumentItem) {
            $keys = [
                'data_inspecao', 'condicao_inicial', 'setor', 'setores', 'criticidade',
                'descricao_nc', 'id_relatorio', 'prazo_adequacao', 'acao', 'acao_realizada',
                'data_realizacao', 'responsavel', 'status',
            ];

            $before = $working->only($keys);

            $working->fill($data);
            $working->updated_by = $request->user()->id;
            $working->save();

            Audit::record(
                'nc_document_item.updated',
                sprintf('Documento %s — subitem %s atualizado.', $document->code, $working->catalogItem->code),
                $working,
                $tenantId,
                $before,
                $working->only($keys),
                $request->user(),
            );
        } else {
            $item->fill($data);
            $item->updated_by = $request->user()->id;
            $item->save();
        }

        return back()->with('success', 'Registro do subitem '.$item->catalogItem->code.' atualizado.');
    }

    /**
     * Upload de evidência para um subitem: o arquivo fica vinculado ao
     * trabalho do documento (quando o contexto é o documento) ou ao plano
     * do cronograma (quando o acesso é direto).
     */
    public function uploadEvidence(Request $request, TenantItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        $tenantId = TenantContext::id();
        if ($item->tenant_id !== $tenantId) {
            abort(403);
        }

        $document = $this->documentContext($request);
        $working = $this->workingUnit($document, $item);

        if ($this->isLocked($document, $working)) {
            abort(403);
        }

        $request->validate([
            'evidence' => ['required', 'file', 'max:20480'], // 20 MB
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('evidence');
        $this->guardSafeFile($file);

        $stored = $file->store('evidences/tenant-'.$tenantId, [
            'disk' => 'local',
        ]);

        $evidence = Evidence::create([
            'tenant_id' => $tenantId,
            'tenant_item_id' => $item->id,
            'uploaded_by' => $request->user()->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $stored,
            'disk' => 'local',
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ]);

        // Em contexto de documento, o arquivo entra na BIBLIOTECA vinculado ao
        // sub-item do documento (pivô) e ao DN (badge em Gestão de Documentos).
        // No plano, ganha o vínculo de card do subitem (pivô evidence_tenant_item).
        if ($document) {
            EvidenceDocumentItem::firstOrCreate(
                ['evidence_id' => $evidence->id, 'nc_document_item_id' => $working->id],
                ['tenant_id' => $tenantId],
            );

            EvidenceDocument::firstOrCreate(
                ['evidence_id' => $evidence->id, 'document_id' => $document->id],
                ['tenant_id' => $tenantId],
            );

            Audit::record(
                'nc_document.evidence_linked',
                sprintf('Arquivo "%s" anexado e vinculado a %s.', $evidence->original_name, $document->code),
                $document,
                $tenantId,
                [],
                ['evidence_id' => $evidence->id, 'nc_document_item_id' => $working->id],
                $request->user(),
            );
        } else {
            EvidenceTenantItem::firstOrCreate(
                ['evidence_id' => $evidence->id, 'tenant_item_id' => $item->id],
                ['tenant_id' => $tenantId],
            );
        }

        return back()->with('success', 'Evidência anexada com sucesso.');
    }

    /**
     * Vincula arquivos JÁ EXISTENTES da biblioteca a este sub-item (sem cópia).
     * Em contexto de documento, o vínculo é com o sub-item do documento (+ badge
     * do DN); no plano, com o sub-item do cronograma.
     */
    public function attachLibraryEvidence(Request $request, TenantItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        $tenantId = TenantContext::id();
        if ($item->tenant_id !== $tenantId) {
            abort(403);
        }

        $document = $this->documentContext($request);
        $working = $this->workingUnit($document, $item);

        if ($this->isLocked($document, $working)) {
            abort(403);
        }

        $data = $request->validate([
            'evidence_ids' => ['required', 'array', 'min:1'],
            'evidence_ids.*' => ['integer', Rule::exists('evidences', 'id')->where('tenant_id', $tenantId)],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['evidence_ids'])));

        if ($working instanceof NcDocumentItem) {
            foreach ($ids as $evidenceId) {
                EvidenceDocumentItem::firstOrCreate(
                    ['evidence_id' => $evidenceId, 'nc_document_item_id' => $working->id],
                    ['tenant_id' => $tenantId],
                );

                EvidenceDocument::firstOrCreate(
                    ['evidence_id' => $evidenceId, 'document_id' => $document->id],
                    ['tenant_id' => $tenantId],
                );
            }

            Audit::record(
                'nc_document.evidence_item_linked',
                sprintf('%d arquivo(s) da biblioteca vinculado(s) ao subitem %s de %s.', count($ids), $working->catalogItem->code, $document->code),
                $document,
                $tenantId,
                [],
                ['evidence_ids' => $ids, 'nc_document_item_id' => $working->id],
                $request->user(),
            );
        } else {
            foreach ($ids as $evidenceId) {
                EvidenceTenantItem::firstOrCreate(
                    ['evidence_id' => $evidenceId, 'tenant_item_id' => $item->id],
                    ['tenant_id' => $tenantId],
                );
            }
        }

        return back()->with('success', 'Arquivo(s) da biblioteca vinculado(s) ao subitem.');
    }

    /**
     * Remove o vínculo do arquivo com ESTE sub-item. O arquivo só é apagado
     * quando não sobra nenhum vínculo (badge de DN, sub-item de documento ou do
     * plano) — arquivos compartilhados nunca são destruídos por aqui.
     */
    public function destroyEvidenceLink(Request $request, TenantItem $item, Evidence $evidence): RedirectResponse
    {
        if (! $request->user()->canDeleteEvidence() || $evidence->tenant_id !== TenantContext::id() || $item->tenant_id !== TenantContext::id()) {
            abort(403);
        }

        if ($evidence->linkedToFinalizedDocument()) {
            abort(403);
        }

        $document = $this->documentContext($request);
        $working = $this->workingUnit($document, $item);

        if ($working instanceof NcDocumentItem) {
            EvidenceDocumentItem::query()
                ->where('evidence_id', $evidence->id)
                ->where('nc_document_item_id', $working->id)
                ->delete();
        } else {
            EvidenceTenantItem::query()
                ->where('evidence_id', $evidence->id)
                ->where('tenant_item_id', $item->id)
                ->delete();
        }

        if ($evidence->hasAnyLink()) {
            return back()->with('success', 'Vínculo removido. O arquivo permanece na biblioteca.');
        }

        Storage::disk($evidence->disk ?? 'local')->delete($evidence->stored_path);
        $evidence->delete();

        return back()->with('success', 'Evidência removida.');
    }

    /**
     * Segurança: nenhum arquivo executável pode ser anexado.
     */
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

    /**
     * Documento informado na URL (?from=document&document_id=) quando existe e
     * pertence ao cliente ativo.
     */
    protected function documentContext(Request $request): ?NcDocument
    {
        if ($request->query('from') !== 'document' || ! $request->filled('document_id')) {
            return null;
        }

        $document = NcDocument::query()->find($request->query('document_id'));

        if ($document && $document->tenant_id === TenantContext::id()) {
            return $document;
        }

        return null;
    }

    /**
     * Unidade de trabalho: dentro de um documento é o NcDocumentItem (estado
     * próprio); acesso direto ao cronograma é o próprio TenantItem (plano).
     */
    protected function workingUnit(?NcDocument $document, TenantItem $item): Model
    {
        if (! $document) {
            return $item;
        }

        return NcDocumentItem::query()
            ->where('document_id', $document->id)
            ->where('tenant_item_id', $item->id)
            ->firstOrFail();
    }

    /**
     * Bloqueio agora é POR DOCUMENTO: apenas quando o documento em questão
     * (contexto da URL) está finalizado. O plano do cronograma, sem contexto,
     * nunca fica bloqueado — um OUTRO documento pode trabalhar o mesmo subitem.
     */
    protected function isLocked(?NcDocument $document, Model $working): bool
    {
        return $working instanceof NcDocumentItem && $document?->isFinalized();
    }

    protected function authorizeWrite(): void
    {
        if (! request()->user()->canWrite()) {
            abort(403);
        }
    }
}
