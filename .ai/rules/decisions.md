# Log de decisões por fase

Regras consolidadas por fase (o QUÊ + POR QUÊ + ONDE). Ao concluir uma fase de
implementação, adicionar a seção desta fase como regra para as próximas.

## Fase 1 — Não Conformidades: documentos (DN-XX)
Área: `NcDocument*`, rotas `nc-documents.*`, `checklist.index`,
`resources/views/nc-documents/**`, `CatalogItem::tree()`.

- **Fonte da seleção = Cronograma de Adequação** (`Source::Cronograma`), NUNCA
  `Source::Checklist` (requisito do cliente). `catalog_item_ids` aceita SEÇÕES e SUBITENS.
- **Seção (título)** entra no documento SEM `tenant_item` — será a "capa" do futuro PDF
  (subitens vão para outra página). **Subitem** entra com o `tenant_item` como ÂNCORA
  (identidade de vínculo/URL); o estado de trabalho do documento é uma CÓPIA própria em
  `nc_document_items` (Fase 5) — status/datas/evidências do documento NÃO refletem no
  cronograma e vice-versa.
- **Picker em árvore** (`_selection.blade.php`): sincronização bidirecional —
  marcar seção marca os sub-itens; desmarcar seção desmarca os sub-itens;
  **marcar um sub-item marca a seção**; desmarcar sub-item individual MANTÉM os
  demais; **desmarcar o último sub-item desmarca a seção** (comportamento JS, sem bundle).
- Contagem de "pendentes" (show + `checklist.index`) usa o status POR DOCUMENTO do
  `nc_document_item` (ignora seções: `tenant_item_id` nulo).
- `buildSnapshot()` grava itens + estado do documento (campos do `nc_document_item`);
  `recordVersion()` roda em create/update/finalize/reopen. Finalizado bloqueia edição do
  próprio documento; "Reabrir" volta a rascunho.
- Auditoria dos documentos é MANUAL no `NcDocumentController` (`nc_document.*`):
  NÃO registrar `NcDocument` no `AuditObserver` (evita duplicação).
- "Trabalhar" de um subitem no documento → `cronograma.show` com
  `?from=document&document_id=`; o "Voltar" respeita a origem (fallback `cronograma.index`).

## Fase 2 — Auditoria global
- Regra de tenant: parâmetro > `tenant_id` do modelo (null respeitado) > `TenantContext`.
- `AuditLog` usa `BelongsToTenant` de propósito: super admin vê tudo; admin só o próprio.
- Observers registrados: TenantItem, Evidence, Funcionario, User, Tenant (nunca Nc*).
- Hooks: `auth.login`, `auth.login_failed`, `auth.logout`.

## Fase 3 — Correções de estabilidade (lições/traps)
- `TenantContext` é estático: resetar em `tests/TestCase.php` (setUp/tearDown).
- `bootstrap/app.php`: `SetTenantContext` ANTES de `SubstituteBindings`.
- `Rule::exists` com `false` quebra → usar `0`.
- `$request->validate()` omite campos nullable ausentes → `$data['x'] ?? null`.
- `AuditObserver::updated`: `mapWithKeys(fn ($value, $key) => ...)`; valores crus via `getAttributes()`.
- SQLite grava dates como `Y-m-d H:i:s`.

## Fase 4 — Estado/ambiente
- Suíte completa verde (`php artisan test --compact`) + Pint limpo ao fim de cada fase.
- Produção só com autorização explícita; dump em `storage/app/backups` antes de mudar.
- CSVs não versionados; re-seed + scp para sincronizar produção.

## Fase 5 — Estado de trabalho por documento (opção 2)
- **Modelo**: `nc_document_items` ganhou cópia dos campos de controle (data_inspecao,
  condicao_inicial, setor/setores, criticidade, descricao_nc, id_relatorio,
  prazo_adequacao, acao, acao_realizada, data_realizacao, responsavel, status, updated_by).
  Cada item do documento tem o SEU estado; o `tenant_item` do cronograma fica só para o
  plano. Dois documentos podem trabalhar o mesmo subitem em ciclos independentes.
- **Evidência por escopo**: `evidences.nc_document_item_id` (nullable, índice,
  cascade no item do documento). `tenant_item_id` SEMPRE preenchido (âncora);
  evidência do documento tem `nc_document_item_id` setado; do plano, nulo.
  Listagem do plano filtra `whereNull('nc_document_item_id')`.
- **Bloqueio por documento** (substitui `isLockedByFinalizedDocument`): finalizar congela
  o CONTEÚDO DO DOCUMENTO (status/evidências próprios), não o subitem do cronograma.
  `CronogramaController::isLocked($document, $working)` = só quando o documento do
  CONTEXTO (`?from=document&document_id=`) está finalizado; plano/sem contexto nunca trava;
  remoção de evidência bloqueia quando o documento dela está finalizado
  (`CronogramaController::isLockedByEvidence` e `DocumentoController::destroy`).
- **`syncItems` virou UPSERT** (ids estáveis): itens que permanecem na seleção preservam
  o estado de trabalho; novos copiam o estado atual do `tenant_item` (`importTenantState`);
  removidos apagam as evidências do documento (arquivos + linhas via cascade) — o
  trabalho de tenant NUNCA é apagado.
- **Auditoria**: update de item no documento gera `nc_document_item.updated` MANUAL
  (Nc* não entra no observer). Evidência e TenantItem continuam no observer automático.
- `cronograma.show/update/upload` resolvem a unidade de trabalho (`NcDocumentItem`
  quando há contexto de documento; `TenantItem` no plano) e a view usa `$working` +
  `$evidences` explícitos (`partials/evidences` aceita coleção). Migrations:
  `2026_09_27_000001` (estado em nc_document_items) e `2026_09_27_000002`
  (`evidences.nc_document_item_id`).

## Fase 6 — Biblioteca de documentos (reuso de arquivos entre DNs)
- **Vínculo N:N** via pivô `evidence_document` (tenant_id, evidence_id, document_id;
  unique par). O modelo `EvidenceDocument` **estende `Pivot`** (não `Model` — o
  `belongsToMany` exige `fromRawAttributes()` no pivô custom).
- O arquivo é **ÚNICO** (uma linha `evidences`, um arquivo físico); "reuso" = só um
  vínculo novo no pivô. Badges na coluna **Documento de referência** de `documentos.index`
  (via `Evidence::documents()`, badge clicável → `nc-documents.show`); acumula
  DN-01, DN-02, ... para o mesmo arquivo.
- Upload em contexto de documento (`?from=document&document_id=`) cria evidência +
  vínculo do pivô com auditoria manual `nc_document.evidence_linked`. Upload no plano
  (sem contexto) não vincula. **Trap**: o ACTION do formulário de anexo precisa levar
  o contexto — `CronogramaController::show()` monta `$uploadRoute` com
  `from=document&document_id=` quando há documento (igual ao `$backUrl`); sem isso o
  arquivo cai no plano (sem badge e sem aparecer na listagem do item).
- **Picker de biblioteca** no create/edit (`_selection.blade.php`, seed `evidence_ids[]`);
  `NcDocumentController::syncLibrary()` sincroniza os vínculos (attach/detach + auditoria).
  Validação: `Rule::exists('evidences','id')->where('tenant_id', TenantContext::id())`.
- **Preservação de arquivo compartilhado**: ao remover item ou excluir documento
  (`syncItems`/`destroy`), vínculo em OUTRO documento preserva arquivo+linha
  (`nc_document_item_id` vira null e o pivô deste doc é removido); sem outro vínculo,
  apaga arquivo + linha. `nc_document_item_id` passa a ser apenas o contexto de anexo.
- `Evidence::linkedToFinalizedDocument()` centraliza a trava: item do documento finalizado
  OU qualquer vínculo do pivô para documento finalizado — usada por
  `CronogramaController::destroyEvidence` e `DocumentoController::destroy`.
- `nc-documents.show`: card "Arquivos vinculados (biblioteca)" (vínculos com
  `nc_document_item_id` null, via `NcDocument::libraryFiles()`) + rota
  `nc-documents.evidence.detach` (desvincular ≠ apagar). Migration:
  `2026_09_27_000003_create_evidence_document_table`.

> **SUPERSEDED pela Fase 7:** a coluna `evidences.nc_document_item_id` (contexto de
> anexo único, Fases 5–6) foi REMOVIDA e virou o pivô N:N `evidence_document_item`.
> Onde lei "`nc_document_item_id` nulo/setado", leia "pivô ausente/presente".

## Fase 7 — Biblioteca POR SUB-ITEM (reuso N:N evidência ↔ sub-item)
- **Modelagem**: dois pivôs N:N (ambos com `tenant_id` + unique par):
  `evidence_document_item` (evidence ↔ `nc_document_item`) e
  `evidence_tenant_item` (evidence ↔ `tenant_item` do plano). `tenant_item_id` em
  `evidences` continua preenchido sempre, mas como **ÂNCORA/origem** — NÃO é vínculo
  de card. Migration `2026_09_27_000004` MIGRA dados e DROPA `nc_document_item_id`.
- **Backfill**: linha com `nc_document_item_id` → pivô doc-item; linha sem →
  pivô plan (a âncora vira vínculo de card). Arquivos doc-work antigos NÃO ganham
  pivô de plano. Ordem MySQL: dropar FK ANTES do índice (`dropIndex` em coluna com
  FK ativa falha com "Cannot drop index ... needed in a foreign key constraint").
- **Card de plano** (cronograma): arquivo aparece se âncora no item SEM pivô doc-item
  OU se há pivô `evidence_tenant_item` no item. **Card de sub-item de documento**:
  só via pivô `evidence_document_item`. Contagem no `index` exclui a âncora quando há
  pivô (senão duplica: âncora + pivô do mesmo item contariam 2x).
- **Upload**: contexto doc → evidência + pivô doc-item + badge DN + auditoria
  `nc_document.evidence_linked`; plano → só pivô plan.
- **Reuso**: rota `cronograma.biblioteca.attach` (pickers com checkboxes
  `evidence_ids[]` na partial de evidências, exibido sempre que há `libraryAvailable`);
  doc context → pivô doc-item + badge + auditoria `nc_document.evidence_item_linked`;
  plano → pivô plan. Validação cross-tenant:
  `Rule::exists('evidences','id')->where('tenant_id', ...)`.
- **Destruição = remoção do VÍNCULO** (`cronograma.evidencia.destroy`, substitui
  `evidencia.destroy-cronograma`; forma com `$item` e `$evidence` + valor/params em
  `destroyRouteModel`/`destroyRouteParams`): apaga só o pivô do contexto; arquivo é
  apagado SOMENTE quando `Evidence::hasAnyLink()` falso (badge de DN, pivô de outro
  sub-item/doc ou do plano preservam o arquivo). Badges de DN não são tocados por aqui.
- **`removeItemLinks()`** (`NcDocumentController::syncItems`/`destroy`): ao remover o
  último item de DN que aponta o arquivo, remover também o badge DAQUELE DN; arquivo
  apagado só quando `hasAnyLink()` falso (badge de outro DN ou pivô de outro doc/plano
  conservam). Em `syncItems` a remoção do item do DN usa `evidence_document_item`.
- Documento OPTIONAL finalizado continua travando (via `linkedToFinalizedDocument`,
  que agora cobre badge OU sub-item de DN finalizado).
## Fase 8 — Dashboard com telemetria (60s)
Área: `DashboardController`, `App\Support\DashboardStats`, `resources/views/dashboard/**`,
`public/js/{dashboard.js,vendor/chart.umd.min.js}`, rota `dashboard.stats`, `DashboardStatsTest`.

- **NC = `nc_document_items` com `tenant_item_id` preenchido** dos documentos do tenant
  (seções/capas ficam de fora; mesma regra do "pendentes" da Fase 1). Filtro por tenant via
  `whereHas('document', tenant_id)` — `NcDocumentItem` NÃO tem `BelongsToTenant`.
- **Buckets**: status = Concluído > Atrasada (prazo vencido) > status próprio (nulo = Pendente);
  criticidade alta = `ALTA` + `GIR` (depois `MÉDIA`; resto = Baixa). KPI "criticidade alta" e
  gráficos de criticidade/prazos consideram só ABERTAS; alertas = abertas com `prazo_adequacao`
  (ordenados asc, máx. 8).
- **Deltas vs mês anterior** por `created_at` e conclusão (`data_realizacao ?? updated_at`);
  base 0 no mês anterior → null ("sem base anterior"). Evolução de 6 meses e previsões são
  HEURÍSTICAS (menor quadrados + velocidade dos últimos 90d), não ML — nunca apresentar como
  previsão oficial.
- **Fonte única**: `DashboardStats::for($tenant)` alimenta o render (`index`) E o JSON
  (`dashboard.stats`, que devolve também fragmentos HTML das listagens). Front
  (`public/js/dashboard.js`) faz polling de 60s, pausa com a aba oculta, sinaliza falha
  mantendo o último estado válido.
- **Frontend**: Chart.js vendored em `public/js/vendor/chart.umd.min.js` (estático, NÃO
  npm/build — regra de frontend); layout com classes existentes + `style=` inline; os cards
  originais ficaram na seção "Indicadores do plano e da biblioteca" (secundários também
  atualizam no polling).
