<?php

namespace App\Http\Controllers;

use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Evidence;
use App\Models\EvidenceDocument;
use App\Models\EvidenceDocumentItem;
use App\Models\NcDocument;
use App\Models\NcDocumentItem;
use App\Models\TenantItem;
use App\Support\Audit;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class NcDocumentController extends Controller
{
    /**
     * Itens disponíveis para compor um documento na aba Normativa: a árvore do
     * Cronograma de Adequação (seções/títulos com os respectivos sub-itens).
     */
    protected function availableItems()
    {
        return CatalogItem::tree(Source::Cronograma);
    }

    /**
     * Itens disponíveis para a aba Operacional: a árvore do Prontuário NR-10.
     */
    protected function availableOperacionalItems()
    {
        return CatalogItem::tree(Source::Prontuario);
    }

    /**
     * Biblioteca: todos os arquivos (evidências) do cliente ativo com os
     * documentos aos quais já estão vinculados (badges do picker).
     */
    protected function libraryEvidences()
    {
        return Evidence::query()
            ->with(['uploader', 'documents'])
            ->orderByDesc('id')
            ->get();
    }

    public function create(): View
    {
        $this->authorizeWrite();

        return view('nc-documents.create', [
            'items' => $this->availableItems(),
            'operacional' => $this->availableOperacionalItems(),
            'library' => $this->libraryEvidences(),
            'selectedEvidenceIds' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeWrite();

        $data = $this->validateSelection($request);

        $tenantId = TenantContext::id();
        $user = $request->user();

        $document = DB::transaction(function () use ($tenantId, $user, $data) {
            $number = NcDocument::nextNumber($tenantId);

            $document = NcDocument::create([
                'tenant_id' => $tenantId,
                'number' => $number,
                'code' => NcDocument::makeCode($number),
                'title' => ($data['title'] ?? null) ?: ('Documento de Não Conformidades '.NcDocument::makeCode($number)),
                'description' => $data['description'] ?? null,
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $this->syncItems($document, $data['catalog_item_ids'], $tenantId);
            $this->syncLibrary($document, $data['evidence_ids'] ?? []);

            $document->recordVersion('Criação do documento', $user->id);

            return $document;
        });

        Audit::record(
            'nc_document.created',
            sprintf('Documento %s criado com %d itens.', $document->code, count($data['catalog_item_ids'])),
            $document,
            $tenantId,
            [],
            ['catalog_item_ids' => $data['catalog_item_ids']],
            $request->user(),
        );

        return redirect()->route('nc-documents.show', $document)
            ->with('success', 'Documento '.$document->code.' criado. Agora é possível trabalhar os itens.');
    }

    public function show(NcDocument $document): View|RedirectResponse
    {
        $this->assertSameTenant($document);

        $document->load(['items.catalogItem', 'versions.creator', 'creator']);

        $pending = $document->items
            ->filter(fn (NcDocumentItem $entry) => $entry->tenant_item_id && $entry->status?->value !== 'Concluído')
            ->count();

        $entryIds = $document->items->pluck('id');

        $evidenceCounts = collect();

        if ($entryIds->isNotEmpty()) {
            $evidenceCounts = EvidenceDocumentItem::query()
                ->selectRaw('nc_document_item_id, count(*) as total')
                ->whereIn('nc_document_item_id', $entryIds)
                ->groupBy('nc_document_item_id')
                ->pluck('total', 'nc_document_item_id');
        }

        return view('nc-documents.show', [
            'document' => $document,
            'canWrite' => request()->user()->canWrite(),
            'canDelete' => request()->user()->canDelete(),
            'pending' => $pending,
            'evidenceCounts' => $evidenceCounts,
            'libraryFiles' => $document->libraryFiles()
                ->with('uploader')
                ->orderByDesc('evidences.created_at')
                ->get(),
        ]);
    }

    public function edit(NcDocument $document): View|RedirectResponse
    {
        $this->assertSameTenant($document);
        $this->authorizeWrite();

        if ($document->isFinalized()) {
            return back()->with('warning', 'O documento '.$document->code.' já foi finalizado. Reabra a edição para alterar.');
        }

        $selected = $document->items()->pluck('catalog_item_id')->all();

        return view('nc-documents.edit', [
            'document' => $document,
            'items' => $this->availableItems(),
            'operacional' => $this->availableOperacionalItems(),
            'selected' => $selected,
            'library' => $this->libraryEvidences(),
            'selectedEvidenceIds' => $document->libraryFiles()->pluck('evidences.id')->all(),
        ]);
    }

    public function update(Request $request, NcDocument $document): RedirectResponse
    {
        $this->assertSameTenant($document);
        $this->authorizeWrite();

        if ($document->isFinalized()) {
            return back()->with('warning', 'O documento '.$document->code.' está finalizado e não pode ser alterado.');
        }

        $data = $this->validateSelection($request);

        $tenantId = TenantContext::id();
        $before = $document->items()->pluck('catalog_item_id')->all();

        DB::transaction(function () use ($tenantId, $document, $data, $request) {
            $document->fill([
                'title' => ($data['title'] ?? null) ?: ('Documento de Não Conformidades '.$document->code),
                'description' => $data['description'] ?? null,
                'updated_by' => $request->user()->id,
            ])->save();

            $this->syncItems($document, $data['catalog_item_ids'], $tenantId);
            $this->syncLibrary($document, $data['evidence_ids'] ?? []);
            $document->recordVersion('Atualização do documento', $request->user()->id);
        });

        Audit::record(
            'nc_document.updated',
            sprintf('Documento %s atualizado com %d itens.', $document->code, count($data['catalog_item_ids'])),
            $document->fresh(),
            $tenantId,
            ['catalog_item_ids' => $before],
            ['catalog_item_ids' => $data['catalog_item_ids']],
            $request->user(),
        );

        return redirect()->route('nc-documents.show', $document)
            ->with('success', 'Documento '.$document->code.' atualizado e nova versão registrada.');
    }

    public function finalize(Request $request, NcDocument $document): RedirectResponse
    {
        $this->assertSameTenant($document);
        $this->authorizeWrite();

        if ($document->isFinalized()) {
            return back()->with('warning', 'O documento '.$document->code.' já está finalizado.');
        }

        if ($document->items()->count() === 0) {
            return back()->with('error', 'Um documento precisa ter ao menos um item para ser finalizado.');
        }

        $document->forceFill([
            'status' => 'finalized',
            'finalized_at' => now(),
            'updated_by' => $request->user()->id,
        ])->save();

        $document->recordVersion('Documento finalizado', $request->user()->id);

        Audit::record('nc_document.finalized', 'Documento '.$document->code.' finalizado.', $document, $document->tenant_id);

        return back()->with('success', 'Documento '.$document->code.' finalizado.');
    }

    public function reopen(Request $request, NcDocument $document): RedirectResponse
    {
        $this->assertSameTenant($document);
        $this->authorizeWrite();

        if (! $document->isFinalized()) {
            return back()->with('warning', 'O documento '.$document->code.' não está finalizado.');
        }

        $document->forceFill([
            'status' => 'draft',
            'finalized_at' => null,
            'updated_by' => $request->user()->id,
        ])->save();

        Audit::record('nc_document.reopened', 'Documento '.$document->code.' reaberto para edição.', $document, $document->tenant_id);

        return back()->with('success', 'Documento '.$document->code.' reaberto para edição.');
    }

    public function destroy(Request $request, NcDocument $document): RedirectResponse
    {
        $this->assertSameTenant($document);

        if (! $request->user()->canDelete()) {
            abort(403);
        }

        $code = $document->code;

        DB::transaction(function () use ($document) {
            $entryIds = $document->items()->pluck('id');

            $evidenceIds = EvidenceDocumentItem::query()
                ->whereIn('nc_document_item_id', $entryIds)
                ->pluck('evidence_id')
                ->unique();

            EvidenceDocumentItem::query()
                ->whereIn('nc_document_item_id', $entryIds)
                ->delete();

            foreach ($evidenceIds as $evidenceId) {
                $evidence = Evidence::query()->find($evidenceId);

                if (! $evidence) {
                    continue;
                }

                // Vínculos que permanecem: badge de OUTRO documento, sub-item de
                // outro documento ou sub-item do plano. O badge deste documento
                // some junto com o cascade do delete.
                $remaining = EvidenceDocument::query()
                    ->where('evidence_id', $evidence->id)
                    ->where('document_id', '!=', $document->id)
                    ->exists()
                    || $evidence->documentItems()->exists()
                    || $evidence->tenantItems()->exists();

                if (! $remaining) {
                    Storage::disk($evidence->disk ?? 'local')->delete($evidence->stored_path);
                    $evidence->delete();
                }
            }

            $document->delete();
        });

        Audit::record('nc_document.deleted', 'Documento '.$code.' excluído.', null, TenantContext::id());

        return redirect()->route('checklist.index')
            ->with('success', 'Documento '.$code.' excluído.');
    }

    /**
     * Desvincula um arquivo da biblioteca deste documento (não apaga o arquivo:
     * ele continua na biblioteca e nos demais documentos).
     */
    public function detachLibraryEvidence(Request $request, NcDocument $document, Evidence $evidence): RedirectResponse
    {
        $this->assertSameTenant($document);
        $this->authorizeWrite();

        if ($evidence->tenant_id !== TenantContext::id()) {
            abort(403);
        }

        if ($document->isFinalized()) {
            return back()->with('warning', 'O documento '.$document->code.' está finalizado e não pode ser alterado.');
        }

        EvidenceDocument::query()
            ->where('document_id', $document->id)
            ->where('evidence_id', $evidence->id)
            ->delete();

        Audit::record(
            'nc_document.evidence_unlinked',
            sprintf('Arquivo "%s" desvinculado de %s.', $evidence->original_name, $document->code),
            $document,
            $document->tenant_id,
            ['evidence_id' => $evidence->id],
            [],
            $request->user(),
        );

        return back()->with('success', 'Vínculo da biblioteca removido. O arquivo permanece na biblioteca.');
    }

    /**
     * Valida a seleção de itens (seções e sub-itens dos catálogos NORMATIVO —
     * Cronograma de Adequação — e OPERACIONAL — Prontuário NR-10) e os arquivos
     * da biblioteca opcionais a vincular ao documento.
     */
    protected function validateSelection(Request $request): array
    {
        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'catalog_item_ids' => ['required', 'array', 'min:1'],
            'catalog_item_ids.*' => [
                'required',
                'integer',
                Rule::exists('catalog_items', 'id')->whereIn('source', [
                    Source::Cronograma->value,
                    Source::Prontuario->value,
                ]),
            ],
            'evidence_ids' => ['nullable', 'array'],
            'evidence_ids.*' => [
                'integer',
                Rule::exists('evidences', 'id')->where('tenant_id', TenantContext::id()),
            ],
        ]);
    }

    /**
     * Reescreve a seleção do documento via UPSERT: os itens que continuam na
     * seleção PRESERVAM o estado de trabalho por documento (ids estáveis);
     * itens novos são inicializados a partir do estado atual do TenantItem do
     * cronograma. As seções (títulos) entram sem TenantItem: são a "capa" do
     * documento no PDF.
     */
    protected function syncItems(NcDocument $document, array $catalogItemIds, int $tenantId): void
    {
        $existingBefore = $document->items()->pluck('catalog_item_id')->all();

        $existing = $document->items()->get()->keyBy('catalog_item_id');

        $selectedIds = array_values(array_unique(array_map('intval', $catalogItemIds)));

        $removed = $existing->filter(fn (NcDocumentItem $entry) => ! in_array($entry->catalog_item_id, $selectedIds, true));

        foreach ($removed as $entry) {
            $this->removeItemLinks($entry);

            $entry->delete();
        }

        $catalogs = CatalogItem::query()->whereIn('id', $selectedIds)->get()->keyBy('id');

        $sort = 0;

        foreach ($selectedIds as $catalogItemId) {
            $catalog = $catalogs->get($catalogItemId);
            $entry = $existing->get($catalogItemId);

            $fields = [];
            $tenantItemId = null;

            if ($catalog && ! $catalog->is_section) {
                $tenantItem = TenantItem::firstOrCreate(
                    ['tenant_id' => $tenantId, 'catalog_item_id' => $catalogItemId],
                );

                $tenantItemId = $tenantItem->id;

                if (! $entry) {
                    $fields = $this->importTenantState($tenantItem);
                }
            }

            if ($entry) {
                $entry->update(['sort_order' => $sort]);
            } else {
                $document->items()->create(array_merge([
                    'catalog_item_id' => $catalogItemId,
                    'tenant_item_id' => $tenantItemId,
                    'sort_order' => $sort,
                ], $fields));
            }

            $sort++;
        }

        $removedIds = array_values(array_diff($existingBefore, $selectedIds));

        if (! empty($removedIds)) {
            // TenantItems de trabalho não são apagados: ficam para registros históricos.
            Audit::record('nc_document.items_removed', sprintf('%d item(ns) removidos do documento %s.', count($removedIds), $document->code), $document, $tenantId, $removedIds, []);
        }
    }

    /**
     * Sincroniza os vínculos de BIBLIOTECA do documento com a seleção enviada
     * pelo picker: cria os vínculos novos e remove os desmarcados. Nunca apaga
     * arquivos — o vínculo é apenas o registro de referência ao DN.
     */
    protected function syncLibrary(NcDocument $document, array $evidenceIds): void
    {
        $target = array_values(array_filter(array_unique(array_map('intval', $evidenceIds)), fn ($id) => $id > 0));

        $current = EvidenceDocument::query()
            ->where('document_id', $document->id)
            ->pluck('evidence_id')
            ->all();

        $toAttach = array_values(array_diff($target, $current));
        $toDetach = array_values(array_diff($current, $target));

        foreach ($toAttach as $evidenceId) {
            EvidenceDocument::firstOrCreate(
                ['evidence_id' => $evidenceId, 'document_id' => $document->id],
                ['tenant_id' => $document->tenant_id],
            );
        }

        if (! empty($toDetach)) {
            EvidenceDocument::query()
                ->where('document_id', $document->id)
                ->whereIn('evidence_id', $toDetach)
                ->delete();
        }

        $user = request()->user();

        if (! empty($toAttach)) {
            Audit::record(
                'nc_document.evidence_linked',
                sprintf('%d arquivo(s) da biblioteca vinculado(s) a %s.', count($toAttach), $document->code),
                $document,
                $document->tenant_id,
                [],
                ['evidence_ids' => $toAttach],
                $user,
            );
        }

        if (! empty($toDetach)) {
            Audit::record(
                'nc_document.evidence_unlinked',
                sprintf('%d arquivo(s) desvinculado(s) de %s.', count($toDetach), $document->code),
                $document,
                $document->tenant_id,
                ['evidence_ids' => $toDetach],
                [],
                $user,
            );
        }
    }

    /**
     * Remove os vínculos de biblioteca de um sub-item que deixou a seleção:
     * apaga o pivô de sub-item e, se não restar nenhuma referência a ESTE
     * documento, o badge do DN. O arquivo só é apagado se ficar órfão de todo
     * vínculo (badge, sub-item de documento ou sub-item do plano).
     */
    protected function removeItemLinks(NcDocumentItem $entry): void
    {
        $evidenceIds = EvidenceDocumentItem::query()
            ->where('nc_document_item_id', $entry->id)
            ->pluck('evidence_id');

        EvidenceDocumentItem::query()
            ->where('nc_document_item_id', $entry->id)
            ->delete();

        foreach ($evidenceIds as $evidenceId) {
            $evidence = Evidence::query()->find($evidenceId);

            if (! $evidence) {
                continue;
            }

            $remainingInDocument = $evidence->documentItems()
                ->whereHas('document', fn ($q) => $q->whereKey($entry->document_id))
                ->exists();

            // Só mantém o badge do DN se ainda houver OUTRO sub-item deste
            // documento apontando para o arquivo; senão, cai com o último link.
            if (! $remainingInDocument) {
                EvidenceDocument::query()
                    ->where('evidence_id', $evidence->id)
                    ->where('document_id', $entry->document_id)
                    ->delete();
            }

            if (! $evidence->hasAnyLink()) {
                Storage::disk($evidence->disk ?? 'local')->delete($evidence->stored_path);
                $evidence->delete();
            }
        }
    }

    /**
     * Estado inicial do item no documento: uma cópia dos campos de controle
     * atuais do TenantItem (o documento "nasce" refletindo o cronograma).
     */
    protected function importTenantState(TenantItem $tenantItem): array
    {
        return [
            'data_inspecao' => $tenantItem->data_inspecao,
            'condicao_inicial' => $tenantItem->condicao_inicial,
            'setor' => $tenantItem->setor,
            'setores' => $tenantItem->setores,
            'criticidade' => $tenantItem->criticidade,
            'descricao_nc' => $tenantItem->descricao_nc,
            'id_relatorio' => $tenantItem->id_relatorio,
            'prazo_adequacao' => $tenantItem->prazo_adequacao,
            'acao' => $tenantItem->acao,
            'acao_realizada' => $tenantItem->acao_realizada,
            'data_realizacao' => $tenantItem->data_realizacao,
            'responsavel' => $tenantItem->responsavel,
            'status' => $tenantItem->status,
        ];
    }

    protected function assertSameTenant(NcDocument $document): void
    {
        if ($document->tenant_id !== TenantContext::id()) {
            abort(403);
        }
    }

    protected function authorizeWrite(): void
    {
        if (! request()->user()->canWrite()) {
            abort(403);
        }
    }
}
