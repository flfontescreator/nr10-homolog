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

## Fase 10 — Matriz NR-10 2026 completa o catálogo (de-para)
Área: `Database\Seeders\CatalogSeeder` (`importMatrizCronograma`/`upsertMatrizItem`),
migration `2026_09_29_194520`, `storage/app/imports/matriz_nr10_2026.csv`,
`CronogramaOptions`, `tests/Feature/CatalogMatrizSyncTest`.

- **Fonte**: `matriz_nr10_2026.csv` (133 itens × 12 colunas; Portaria MTE nº 737/2026,
  vigência 01/06/2027). CSV não versionado no git (mesma regra dos demais imports).
- **De-para por (source, code)**: cria o que falta (8 itens + seções 10.1/10.2 → 149 linhas
  no cronograma); IDs preservados via `updateOrCreate`; reseed idempotente.
  **Armadilha**: códigos que existem só na matriz precisam ser registrados em
  `sourceCodes` mesmo quando a linha já existe — senão `removeStaleCodes` apaga no reseed.
- **Regras de conteúdo**: norma técnica = planilha prevalece sempre (`norma_tecnica`
  literal com o código; `description` converge só quando o texto normalizado difere —
  corrigiu 7 itens, ex.: 10.7.4.1 estava contaminado com o texto do 10.7.4.2);
  interpretação/sugestão/status preenchem apenas o vazio (status inicial "Não iniciado",
  seções ficam sem); criticidade e setor = planilha 2026 prevalece (decisão do usuário),
  com mapeamento Crítica→`Crítica / Grave e Iminente Risco (GIR)`, Alta→`ALTA`,
  Média→`MÉDIA`, Baixa→`BAIXA`.
- **`sort` não é tocado**: o cronograma inteiro vive com `sort=0` e os testes ordenam
  por `sort` — alterar o contador do seeder mudaria a ordem das listagens de teste.
- **`CronogramaOptions`**: `criticidades()` ganhou `BAIXA`; `setores()` faz a união de
  cronograma.csv (col. Setor) com os setores da matriz (col. 4, divididos por `/`).
- **Tenants existentes**: `Tenant::bootstrapItems()` precisa ser reexecutado por tenant
  para vincular os itens novos (local já executado nos tenants 1 e 5; produção pendente
  de autorização, junto com migrate + re-seed + scp do CSV).
- Cobertura: `CatalogMatrizSyncTest` (7 casos: criação de itens/seções, preenchimento,
  convergência de textos, reclassificação, preservação de edições, idempotência).

## Fase 11 — Funcionário standalone + exclusão definitiva

### Funcionário standalone (definitivo)
- `funcionarios` não tem mais vínculo com catálogo/4.x: os itens são
  `funcionario_items` numeração PRÓPRIA em sequência (1, 2, 3…), criados pelo usuário.
  Legado 4.x removido por `2026_10_01_070400` (que também remove `catalog_items.n1=4`).
- Migration `2026_10_01_080000_add_deactivation_fields_to_funcionarios_table` criou
  `funcionarios.{ativo, desativado_*, reativado_*, reativacao_expira_em}` e DROPA
  `funcionario_items.{validade_aplica, data_validade}` (validade migrou para a
  evidência). As colunas `ativo`/`desativado_*`/`reativado_*` ficaram **sem uso** —
  desativar/reativar foi removido por completo (rotas, `authorizeActive`/`authorizeManage`,
  comando `funcionarios:expira-reativacoes` e o agendamento em `routes/console.php`).
  Manter a migration: as colunas continuam no banco.

### Situação do funcionário (cadastro — só exibição)
- `funcionario_situacoes` (`2026_10_02_142759` + seed e
  `2026_10_02_142810_add_admissao_e_situacao`) guarda o Ativo/Inativo cadastral.
- A Situação **não é escolhida na tela** (create/edit escondem o campo): `store` grava
  sempre `FuncionarioSituacao::default()` (Ativo); `update` **não sobrescreve**. Ajuste
  pontual só via banco.
- Badge de Situação vem de `$funcionario->situacao` (`isInativo()` → `.badge-neutral`;
  senão `.badge-blue`; `null` → `-`). Não reintroduzir o filtro `?situacao`: a listagem
  mostra todos.

### Validade da evidência é universal (checkbox "Se aplica")
- A validade pertence ao **arquivo anexado**, não ao item: `partials/evidences`
  tem o checkbox **Se aplica** (desmarcado por padrão) que habilita/desabilita o
  campo data; marcado exige data, desmarcado grava `null`.
- Regra única em `EvidenciaUploadService::resolveValidade($request)` — TODOS os
  módulos de upload usam (Prontuário, Cronograma, Checklist/RNC, Funcionário).
  Não reintroduzir `$request->input('validade')` direto nos controllers.
- Trap de teste: sem `validade_aplica => 1` a validade é SEMPRE `null`, mesmo que
  a data seja enviada.

### Nomenclatura do arquivo anexado
- `EvidenciaUploadService::storedFilename()` gera
  `{img|doc}_{módulo}_{ddmmaaaa}_{sequencial de 9 dígitos}.{ext}`:
  `img_fun_01102026_000000001.png`, `doc_prt_01102026_000000001.pdf`.
- O `sequencial` é GLOBAL por categoria (uma para imagens, outra para
  documentos/PDF), em `evidence_sequences` via `EvidenceSequence::proximo()`
  (transação + `lockForUpdate`) — não é diário nem por módulo/cliente.
- Prefixos: `rnc` (RNC e documentos de NC), `fun` (Funcionários),
  `crn` (Cronograma), `prt` (Prontuário) — constantes `MODULO_*`. O prefixo é o
  da TELA do upload: no `CronogramaController` em contexto de documento
  (`?from=document`) é `rnc`, no plano é `crn`.
- `storeForTenantItem()`/`storeForFuncionarioItem()` usam `storeAs` (não `store`);
  não voltar a chamar `UploadedFile::store()` direto nos controllers.
- `original_name` guarda o nome GERADO; o nome enviado pelo usuário é
  descartado. Anexos já gravados mantêm o nome antigo (sem migração).

> A regra completa e vigente está em `architecture.md` → **Anexos (evidências)**.

### Exclusão definitiva de funcionário e item (produção, sem rótulo "teste")
- **Funcionário**: `DELETE funcionarios/{funcionario}` (`FuncionarioController::destroy`),
  liberado para Gestor/Admin/SuperAdmin (`canHardDelete()` = `User::canWrite()`); o
  Visualizador não vê o botão. Apaga o funcionário e seus itens, mas **preserva as
  evidências**: faz `evidences()->update(['funcionario_item_id' => null])` antes de
  deletar (a FK é `cascadeOnDelete`, então NÃO confiar só no banco).
- **Item do funcionário**: `DELETE .../itens/{item}` idem — desvincula e preserva.
- Sem gate de ambiente: funciona igual em produção. Não existe mais
  `abort_unless(app()->environment('local', 'testing'), 404)` nesses endpoints, e os
  botões não têm mais o rótulo "(teste)".

### Exclusão de evidência é sempre permitida
- `DocumentoController::destroy` NÃO bloqueia mais evidência presa a DN finalizado
  (`linkedToFinalizedDocument()`) nem a RNC publicado (`linkedToPublishedRnc()` /
  `RncItem::lockedByPublication()`). `RncController::destroyItemEvidence` perdeu o
  `abort_if(..., 409)`. A autorização segue: `canDeleteEvidence()`
  (SuperAdmin/Admin/Manager) + tenant.
- `CronogramaController::destroyEvidenceLink()` segue com `linkedToFinalizedDocument()`
  — não foi destravado.

### ⚠️ EXCEÇÃO TEMPORÁRIA remanescente — HARD DELETE DE DOCUMENTO RNC (REVERTER)
Só o **documento de NC** continua restrito a `local`/`testing`:
- Rota `DELETE nc-documents/{document}` (`NcDocumentController::destroy`) + botões
  "Excluir (teste)" em `nc-documents/show` e `checklist/index`.
- Guarda `abort_unless(app()->environment('local', 'testing'), 404)` — o gate é do
  **endpoint**, não só da view: esconder o botão não fecha a rota.

**CHECKLIST DE REMOÇÃO (obrigatório antes de qualquer deploy):**
- [ ] Remover `NcDocumentController::destroy()` + rota `nc-documents.destroy`.
- [ ] Remover os botões "Excluir (teste)" de `nc-documents/show` e `checklist/index`.
- [ ] Remover/inverter os testes de hard delete de documento NC.
- [ ] Confirmar `php artisan test --compact` verde + Pint limpo.
