<?php

namespace App\Http\Controllers;

use App\Enums\RncModelo;
use App\Enums\RncStatus;
use App\Mail\RncMail;
use App\Models\ClassificacaoRisco;
use App\Models\Criticidade;
use App\Models\Evidence;
use App\Models\NormaTecnica;
use App\Models\Projeto;
use App\Models\Rnc;
use App\Models\RncItem;
use App\Models\RncRevision;
use App\Models\Situacao;
use App\Support\Audit;
use App\Support\EvidenciaUploadService;
use App\Support\Rnc\RncPdfRenderer;
use App\Support\Rnc\RncPublicationService;
use App\Support\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Módulo RNC — relatório formal de não conformidade, NOVO e independente de
 * "Não Conformidades" (`NcDocumentController`). Numeração `RNC_0001` por tenant;
 * dois modelos de layout (técnica/fotográfica); existe UMA revisão (`Rev:0001`),
 * criada em "Publicar" e reemitida por "Atualizar publicação". A edição é livre
 * antes e depois da publicação.
 */
class RncController extends Controller
{
    public function __construct(
        protected RncPublicationService $publication,
        protected RncPdfRenderer $pdf,
    ) {}

    // ------------------------------------------------------------------
    // Listagem e relatório
    // ------------------------------------------------------------------

    public function index(Request $request): View
    {
        $this->authorizeWrite();

        $situacao = $request->query('situacao', 'todos');
        $situacao = in_array($situacao, ['todos', 'rascunho', 'publicado'], true) ? $situacao : 'todos';

        $query = Rnc::query()
            ->where('tenant_id', TenantContext::id())
            ->with('projeto')
            ->withCount(['items', 'revisions']);

        if ($situacao === 'rascunho') {
            $query->where('status', RncStatus::Rascunho->value);
        } elseif ($situacao === 'publicado') {
            $query->where('status', RncStatus::Publicado->value);
        }

        if ($busca = trim((string) $request->query('q', ''))) {
            $query->where(fn ($q) => $q
                ->where('code', 'like', '%'.$busca.'%')
                ->orWhere('titulo', 'like', '%'.$busca.'%'));
        }

        $rncs = $query->orderByDesc('number')->get();

        return view('rnc.index', [
            'rncs' => $rncs,
            'situacao' => $situacao,
            'busca' => $busca ?: null,
            'pdfAvailable' => $this->pdf->available(),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeWrite();

        $modelo = (string) $request->query('modelo', RncModelo::Tecnica->value);
        $modelo = array_key_exists($modelo, RncModelo::options()) ? $modelo : RncModelo::Tecnica->value;

        return view('rnc.create', [
            'projetos' => $this->projetos(),
            'modelos' => RncModelo::options(),
            'modeloSelecionado' => $modelo,
            'nextCode' => Rnc::makeCode(Rnc::nextNumber(TenantContext::id())),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeWrite();

        $data = $this->validatedRnc($request);

        $user = $request->user();

        $rnc = DB::transaction(function () use ($data, $user) {
            $number = Rnc::nextNumber(TenantContext::id());

            $rnc = Rnc::create($data + [
                'tenant_id' => TenantContext::id(),
                'number' => $number,
                'code' => Rnc::makeCode($number),
                'status' => RncStatus::Rascunho,
                'current_revision' => 0,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            Audit::record(
                'rnc.create',
                sprintf('RNC %s criado', $rnc->code),
                $rnc,
                null,
                [],
                [],
                $user
            );

            return $rnc;
        });

        return redirect()
            ->route('rnc.show', $rnc)
            ->with('success', sprintf('RNC %s criado como rascunho. Adicione as não conformidades e publique quando estiver pronto.', $rnc->code));
    }

    public function show(Request $request, Rnc $rnc): View
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);

        $rnc->load(['projeto', 'creator', 'publisher'])
            ->load([
                'items.evidences',
                'items.criticidade',
                'items.classificacaoRisco',
                'items.situacao',
                'items.normaItens',
                'revisions.publisher',
            ]);

        return view('rnc.show', [
            'rnc' => $rnc,
            'latestRevision' => $rnc->latestRevision(),
            'publicacaoPendente' => $this->publication->hasPendingChanges($rnc),
            'pdfAvailable' => $this->pdf->available(),
            'criticidades' => $this->criticidades(),
            'classificacoes' => $this->classificacoesRisco(),
            'situacoes' => $this->situacoes(),
            'normas' => $this->normasComItens(),
        ]);
    }

    public function edit(Request $request, Rnc $rnc): View
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);

        return view('rnc.edit', [
            'rnc' => $rnc,
            'projetos' => $this->projetos(),
            'modelos' => RncModelo::options(),
        ]);
    }

    public function update(Request $request, Rnc $rnc): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);

        $antes = $rnc->only(['titulo', 'descricao', 'projeto_id', 'modelo', 'data_inspecao', 'responsavel_nome', 'responsavel_cargo', 'recomendacoes', 'resumo', 'conclusao']);

        $rnc->fill($this->validatedRnc($request));
        $rnc->updated_by = $request->user()->id;
        $rnc->save();

        Audit::record(
            'rnc.update',
            sprintf('RNC %s alterado', $rnc->code),
            $rnc,
            null,
            $antes,
            $rnc->only(array_keys($antes)),
            $request->user()
        );

        return redirect()
            ->route('rnc.show', $rnc)
            ->with('success', 'Cabeçalho atualizado. Se o relatório já foi publicado, clique em “Atualizar publicação” para reemitir a revisão com o conteúdo novo.');
    }

    /**
     * Exclusão definitiva do RNC (Admin/SuperAdmin). Vale para publicações
     * também: remove evidências (arquivos), PDFs das revisões e o link público
     * antes do delete — os itens e revisões caem em cascata.
     */
    public function destroy(Request $request, Rnc $rnc): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        abort_unless($request->user()?->canDelete(), 403);

        $code = $rnc->code;

        DB::transaction(function () use ($rnc) {
            foreach ($rnc->items as $item) {
                foreach ($item->evidences as $evidence) {
                    EvidenciaUploadService::deleteEvidence($evidence);
                }
            }

            foreach ($rnc->revisions as $revision) {
                Storage::disk('local')->delete(RncPdfRenderer::pathFor($rnc, $revision->revision));
            }

            $rnc->delete();
        });

        Audit::record(
            'rnc.destroy',
            sprintf('RNC %s excluído', $code),
            $rnc,
            null,
            [],
            [],
            $request->user()
        );

        return redirect()
            ->route('rnc.index')
            ->with('success', sprintf('RNC %s excluído.', $code));
    }

    // ------------------------------------------------------------------
    // Itens (não conformidades do relatório)
    // ------------------------------------------------------------------

    public function storeItem(Request $request, Rnc $rnc): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);

        $data = $this->validatedItem($request);

        $item = $rnc->items()->create($data + [
            'tenant_id' => $rnc->tenant_id,
            'numero' => $rnc->nextItemNumber(),
            'situacao_id' => $data['situacao_id'] ?? Situacao::default()?->id,
        ]);

        $item->normaItens()->sync($this->normaItemIds($request));

        $rnc->touch();
        $rnc->forceFill(['updated_by' => $request->user()->id])->save();

        Audit::record(
            'rnc.item.create',
            sprintf('NC %d adicionada ao %s', $item->numero, $rnc->code),
            $rnc,
            null,
            [],
            ['item' => $item->numero, 'titulo' => $item->titulo],
            $request->user()
        );

        return back()->with([
            'success' => sprintf('Não conformidade %d adicionada.', $item->numero),
            'new_item_id' => $item->id,
        ]);
    }

    public function updateItem(Request $request, Rnc $rnc, RncItem $item): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        $this->authorizeItemBelongsToRnc($rnc, $item);

        $data = $this->validatedItem($request);

        $antes = $item->only(['recomendacao', 'prazo_adequacao', 'data_adequacao', 'situacao_id']);
        $item->fill($data)->save();
        $item->normaItens()->sync($this->normaItemIds($request));

        Audit::record(
            'rnc.item.update',
            sprintf('NC %d do %s atualizada', $item->numero, $rnc->code),
            $rnc,
            null,
            $antes,
            $item->only(array_keys($antes)),
            $request->user()
        );

        return back()->with('success', sprintf('Não conformidade %d atualizada.', $item->numero));
    }

    public function destroyItem(Request $request, Rnc $rnc, RncItem $item): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        $this->authorizeItemBelongsToRnc($rnc, $item);

        DB::transaction(function () use ($item) {
            foreach ($item->evidences as $evidence) {
                EvidenciaUploadService::deleteEvidence($evidence);
            }

            $item->delete();
        });

        Audit::record(
            'rnc.item.destroy',
            sprintf('NC %d removida do RNC', $item->numero),
            $rnc,
            null,
            [],
            ['item' => $item->numero],
            $request->user()
        );

        return back()->with('success', sprintf('Não conformidade %d removida.', $item->numero));
    }

    // ------------------------------------------------------------------
    // Evidências do item
    // ------------------------------------------------------------------

    public function uploadItemEvidence(Request $request, Rnc $rnc, RncItem $item): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        $this->authorizeItemBelongsToRnc($rnc, $item);

        $data = $request->validate([
            'evidence' => ['required', 'file', 'max:20480'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $validade = EvidenciaUploadService::resolveValidade($request);

        EvidenciaUploadService::storeForRncItem(
            $request->file('evidence'),
            $item,
            $request->user(),
            $data['description'] ?? null,
            $validade
        );

        Audit::record(
            'rnc.item.evidencia.upload',
            sprintf('Evidência anexada na NC %d do %s', $item->numero, $rnc->code),
            $rnc,
            null,
            [],
            ['item' => $item->numero],
            $request->user()
        );

        return back()->with('success', 'Evidência anexada.')->with('open_item_id', $item->id);
    }

    public function destroyItemEvidence(Request $request, Rnc $rnc, RncItem $item, Evidence $evidence): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        $this->authorizeItemBelongsToRnc($rnc, $item);
        abort_unless($evidence->rnc_item_id === $item->id, 404);

        EvidenciaUploadService::deleteEvidence($evidence);

        Audit::record(
            'rnc.item.evidencia.destroy',
            sprintf('Evidência removida da NC %d do %s', $item->numero, $rnc->code),
            $rnc,
            null,
            [],
            ['item' => $item->numero],
            $request->user()
        );

        return back()->with('success', 'Evidência removida.')->with('open_item_id', $item->id);
    }

    // ------------------------------------------------------------------
    // Publicação: revisão, Markdown, PDF e link público
    // ------------------------------------------------------------------

    /**
     * Cria a PRÓXIMA revisão (`Rev:0002`, ...): congela a anterior, que segue
     * no histórico com o link dela valendo. É o "Publicar nova revisão".
     */
    public function publish(Request $request, Rnc $rnc): RedirectResponse
    {
        return $this->publicar($request, $rnc, novaRevisao: true);
    }

    /**
     * Reemite a revisão corrente (mesmo número, mesmo link público) — é o
     * "Atualizar publicação", usado no dia a dia após editar.
     */
    public function republish(Request $request, Rnc $rnc): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        abort_if($rnc->current_revision === 0, 422, 'Este RNC ainda não foi publicado. Use "Publicar".');

        return $this->publicar($request, $rnc, novaRevisao: false);
    }

    protected function publicar(Request $request, Rnc $rnc, bool $novaRevisao): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);

        $data = $request->validate([
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);

        abort_if($rnc->items()->doesntExist(), 422, 'Adicione ao menos uma não conformidade antes de publicar.');

        $anteriorLabel = $rnc->current_revision > 0 ? $rnc->makeRevisionLabel($rnc->current_revision) : null;

        $revision = $novaRevisao
            ? $this->publication->publish($rnc, $request->user(), $data['notas'] ?? null)
            : $this->publication->republish($rnc, $request->user(), $data['notas'] ?? null);

        if (! $novaRevisao) {
            $mensagem = sprintf(
                '%s atualizado: %s reemitida com o conteúdo atual. O link público continua o mesmo, renovado por %d dias.',
                $rnc->code,
                $revision->label,
                RncRevision::PUBLIC_LINK_DAYS
            );
        } elseif ($anteriorLabel) {
            $mensagem = sprintf(
                '%s publicado como %s (nova revisão). A %s continua no histórico com o próprio link; o novo vale %d dias.',
                $rnc->code,
                $revision->label,
                $anteriorLabel,
                RncRevision::PUBLIC_LINK_DAYS
            );
        } else {
            $mensagem = sprintf(
                '%s publicado como %s. Link público válido por %d dias.',
                $rnc->code,
                $revision->label,
                RncRevision::PUBLIC_LINK_DAYS
            );
        }

        return redirect()
            ->route('rnc.show', $rnc)
            ->with('success', $mensagem);
    }

    public function renewPublicLink(Request $request, Rnc $rnc, RncRevision $revision): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        $this->authorizeRevisionBelongsToRnc($rnc, $revision);

        $revision->renewPublicLink();

        Audit::record(
            'rnc.link.renew',
            sprintf('Link público do %s (%s) renovado', $rnc->code, $revision->label),
            $rnc,
            null,
            [],
            ['revision' => $revision->label],
            $request->user()
        );

        return back()->with('success', 'Link público renovado por mais '.RncRevision::PUBLIC_LINK_DAYS.' dias.');
    }

    public function revisionMarkdown(Rnc $rnc, RncRevision $revision): Response
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        $this->authorizeRevisionBelongsToRnc($rnc, $revision);

        return response($revision->markdown, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => sprintf(
                'attachment; filename="%s_%s.md"',
                $rnc->code,
                str_replace(':', '', $revision->label)
            ),
        ]);
    }

    public function revisionPdf(Request $request, Rnc $rnc, RncRevision $revision): StreamedResponse|RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        $this->authorizeRevisionBelongsToRnc($rnc, $revision);

        // Sem arquivo em disco (dompdf indisponível na Hostinger, p.ex.): cai no
        // HTML de impressão em vez de quebrar o download.
        if (! $revision->pdf_path || ! Storage::disk('local')->exists($revision->pdf_path)) {
            if ($this->pdf->available()) {
                $revision->forceFill([
                    'pdf_path' => $this->pdf->store($rnc, $revision->snapshot, $revision->label, $revision->revision),
                ])->save();
            } else {
                return redirect()->route('rnc.revision.print', [$rnc, $revision]);
            }
        }

        abort_unless($this->pdf->available(), 404);

        return Storage::disk('local')->download(
            $revision->pdf_path,
            RncPdfRenderer::filenameFor($rnc, $revision->revision),
            ['Content-Type' => 'application/pdf'],
        );
    }

    /**
     * Fallback sem dompdf: a mesma página em HTML com CSS de impressão, para o
     * usuário gerar o PDF pelo próprio navegador.
     */
    public function revisionPrint(Request $request, Rnc $rnc, RncRevision $revision): View
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        $this->authorizeRevisionBelongsToRnc($rnc, $revision);

        return view('rnc.print', [
            'rnc' => $rnc,
            'revision' => $revision,
            'snapshot' => $revision->snapshot,
        ]);
    }

    // ------------------------------------------------------------------
    // Envio por e-mail
    // ------------------------------------------------------------------

    public function send(Request $request, Rnc $rnc, RncRevision $revision): RedirectResponse
    {
        $this->authorizeWrite();
        $this->authorizeTenant($rnc);
        $this->authorizeRevisionBelongsToRnc($rnc, $revision);

        $destinatario = $rnc->tenant?->contact_email;

        if (! $destinatario) {
            return back()->with('error', 'O cliente não tem e-mail de contato cadastrado. Cadastre-o em Configurações do cliente.');
        }

        abort_if($revision->publicLinkExpired(), 422, 'O link público desta revisão expirou. Renove o link antes de enviar.');

        Mail::to($destinatario)->send(new RncMail($rnc, $revision));

        Audit::record(
            'rnc.send',
            sprintf('%s (%s) enviado para %s', $rnc->code, $revision->label, $destinatario),
            $rnc,
            null,
            [],
            ['destinatario' => $destinatario],
            $request->user()
        );

        return back()->with('success', sprintf('%s enviado para %s.', $revision->label, $destinatario));
    }

    // ------------------------------------------------------------------
    // Link público (sem autenticação)
    // ------------------------------------------------------------------

    public function publicShow(Request $request, string $token): View
    {
        $revision = RncRevision::query()
            ->where('public_token', $token)
            ->firstOrFail();

        abort_if($revision->publicLinkExpired(), 410, 'Este link público expirou. Peça um novo link ao responsável técnico.');

        $rnc = $revision->rnc()->firstOrFail();

        return view('rnc.public', [
            'rnc' => $rnc,
            'revision' => $revision,
            'snapshot' => $revision->snapshot,
        ]);
    }

    public function publicPdf(string $token): StreamedResponse
    {
        $revision = RncRevision::query()->where('public_token', $token)->firstOrFail();

        abort_if($revision->publicLinkExpired(), 410, 'Este link público expirou.');

        $rnc = $revision->rnc()->firstOrFail();

        if (! $revision->pdf_path || ! Storage::disk('local')->exists($revision->pdf_path)) {
            abort_unless($this->pdf->available(), 404);

            $revision->forceFill([
                'pdf_path' => $this->pdf->store($rnc, $revision->snapshot, $revision->label, $revision->revision),
            ])->save();
        }

        return Storage::disk('local')->download(
            $revision->pdf_path,
            RncPdfRenderer::filenameFor($rnc, $revision->revision),
            ['Content-Type' => 'application/pdf'],
        );
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    protected function validatedRnc(Request $request): array
    {
        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'projeto_id' => ['nullable', Rule::exists('projetos', 'id')],
            'modelo' => ['required', Rule::in(array_keys(RncModelo::options()))],
            'data_inspecao' => ['nullable', 'date'],
            'responsavel_nome' => ['nullable', 'string', 'max:255'],
            'responsavel_cargo' => ['nullable', 'string', 'max:120'],
            'recomendacoes' => ['nullable', 'string'],
            'resumo' => ['nullable', 'string'],
            'conclusao' => ['nullable', 'string'],
        ]);

        return array_map(fn ($value) => $value === '' ? null : $value, $data);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedItem(Request $request): array
    {
        $data = $request->validate([
            'titulo' => ['nullable', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'criticidade_id' => ['nullable', Rule::exists('criticidades', 'id')],
            'classificacao_risco_id' => ['nullable', Rule::exists('classificacoes_risco', 'id')],
            'situacao_id' => ['nullable', Rule::exists('situacoes', 'id')],
            'recomendacao' => ['nullable', 'string'],
            'prazo_adequacao' => ['nullable', 'date'],
            'data_adequacao' => ['nullable', 'date'],
            'norma_item_ids' => ['nullable', 'array'],
            'norma_item_ids.*' => ['integer', Rule::exists('norma_itens', 'id')],
        ]);

        unset($data['norma_item_ids']);

        return array_map(fn ($value) => $value === '' ? null : $value, $data);
    }

    /**
     * @return array<int, int>
     */
    protected function normaItemIds(Request $request): array
    {
        return collect($request->input('norma_item_ids', []))
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Projeto>
     */
    protected function projetos()
    {
        return Projeto::query()->orderBy('nome')->get();
    }

    /**
     * @return Collection<int, Criticidade>
     */
    protected function criticidades()
    {
        return Criticidade::query()->orderBy('ordem')->orderBy('nome')->get();
    }

    /**
     * @return Collection<int, ClassificacaoRisco>
     */
    protected function classificacoesRisco()
    {
        return ClassificacaoRisco::query()->orderBy('ordem')->orderBy('nome')->get();
    }

    /**
     * @return Collection<int, Situacao>
     */
    protected function situacoes()
    {
        return Situacao::query()->orderBy('ordem')->orderBy('nome')->get();
    }

    /**
     * O campo "Referências normativas" é exclusivo da NR-10: é a única norma
     * com itens (vêm da matriz do cronograma). As demais normas do catálogo
     * não entram aqui.
     *
     * @return Collection<int, NormaTecnica>
     */
    protected function normasComItens()
    {
        return NormaTecnica::query()
            ->where('codigo', 'NR-10')
            ->with(['itens' => fn ($q) => $q->orderBy('ordem')->orderBy('codigo')])
            ->get();
    }

    protected function authorizeWrite(): void
    {
        abort_unless(request()->user()?->canWrite(), 403);
    }

    protected function authorizeTenant(Rnc $rnc): void
    {
        abort_unless($rnc->tenant_id === TenantContext::id(), 404);
    }

    protected function authorizeItemBelongsToRnc(Rnc $rnc, RncItem $item): void
    {
        abort_unless($item->rnc_id === $rnc->id && $item->tenant_id === $rnc->tenant_id, 404);
    }

    protected function authorizeRevisionBelongsToRnc(Rnc $rnc, RncRevision $revision): void
    {
        abort_unless($revision->rnc_id === $rnc->id && $revision->tenant_id === $rnc->tenant_id, 404);
    }
}
