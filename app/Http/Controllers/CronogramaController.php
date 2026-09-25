<?php

namespace App\Http\Controllers;

use App\Enums\ItemStatus as Status;
use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Evidence;
use App\Models\TenantItem;
use App\Support\CronogramaOptions;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
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
            ->withCount('evidences')
            ->whereHas('catalogItem', fn ($q) => $q->where('source', Source::Cronograma->value))
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('catalog_item_id');

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

        $item->load(['catalogItem', 'evidences.uploader']);

        return view('cronograma.show', [
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

        $item->fill($data);
        $item->updated_by = $request->user()->id;
        $item->save();

        return back()->with('success', 'Registro do subitem '.$item->catalogItem->code.' atualizado.');
    }

    /**
     * Upload de evidência para um subitem do cronograma.
     */
    public function uploadEvidence(Request $request, TenantItem $item): RedirectResponse
    {
        $this->authorizeWrite();

        $tenantId = TenantContext::id();
        if ($item->tenant_id !== $tenantId) {
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

    protected function authorizeWrite(): void
    {
        if (! request()->user()->canWrite()) {
            abort(403);
        }
    }
}
