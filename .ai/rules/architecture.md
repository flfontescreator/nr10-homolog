# Arquitetura (contexto enxuto)

## Visão geral
Laravel (PHP 8.3), gestão de segurança/adequação NR-10, multi-cliente (tenant).
Catálogo FIXO vem de planilhas CSV (`storage/app/imports`) → `catalog_items`
(importado por `CatalogSeeder`). O trabalho de cada cliente fica em `tenant_items`
(uma linha por tenant + subitem do catálogo).

## Multi-tenant (base de tudo)
- Sessão define o "cliente ativo" → `TenantContext` (`app/Support/TenantContext.php`).
- `BelongsToTenant` + `TenantScope` (`app/Models/Scopes/TenantScope.php`): filtra por
  `tenant_id` só quando o contexto está ativo; super admin (sem tenant) não filtra.
- `SetTenantContext` roda ANTES de `SubstituteBindings` (`bootstrap/app.php`).
  **NUNCA inverter**: binding sem contexto resolve registros cross-tenant
  (vira 403 no controller em vez de 404). Falha = bug real de aplicação.
- `RequireTenant` (alias `tenant`) bloqueia área sem cliente; `2fa` exige verificação.
- Cross-tenant deve dar **404** (binding). Controles manuais usam `abort(403)`
  quando o modelo chega por outro caminho.
- Em testes, `TenantContext` é **estático e vaza** entre testes;
  `tests/TestCase.php` faz `TenantContext::set(null)` no setUp/tearDown.

## Módulos (rotas em `routes/web.php`)
- **Cronograma de Adequação** (`cronograma.*`, grupo `tenant`): árvore seções +
  subitens (source=cronograma) com campos de controle por cliente em `tenant_items`.
- **Não Conformidades / Documentos** (`nc-documents.*`):
  documento DN-XX; seleção = seções + subitens DO CRONOGRAMA; versões/snapshots;
  finalizar/reabrir. Estado de trabalho por documento em `nc_document_items`
  (cópia própria); plano/cronograma nunca trava;
  **biblioteca de documentos**: arquivos reutilizáveis via vínculos N:N —
  `evidence_document` (documento, badges "Documento de referência" em
  `documentos.index`, picker no create/edit), `evidence_document_item`
  (evidência ↔ sub-item de DN, contexto de anexo) e `evidence_tenant_item`
  (evidência ↔ sub-item do plano); `tenant_item_id` na evidência é âncora/origem.
  Regras completas em `decisions.md` (Fases 1, 5, 6 e 7).
- **Não Conformidades — grid** (`checklist.index` → `NaoConformidadeController`):
  datagrid somente leitura, uma linha por NC, fonte atual = RNC (matriz de
  classificação e filtros em `decisions.md` Fase 12). As rotas/views de edição
  do checklist antigo (`checklist.show/update/evidencia.*`) foram removidas;
  `source=checklist` segue no catálogo só como histórico.
- **Prontuário** (`prontuario.*`): subitens do item 4 por funcionário
  (`funcionario_id`), criados por `Funcionario::bootstrapProntuarioItems()`.
- **Auditoria** (`auditoria.index`): log global (veja § Auditoria).
- **Documentos/evidências** (`documentos.*`): download/preview/exclusão de arquivos;
  é a tela "Gestão de Documentos" — lista a biblioteca (todas as evidências do tenant)
  com a coluna "Documento de referência" (badges dos DNs via `evidence_document`).
- **Clientes/Usuários** (`tenants.*`, `usuarios.*`): CRUD administrativo.

## Papéis e permissões (`app/Models/User.php`)
- `super_admin` (tenant_id null): tudo. `admin`: gerencia/exclui.
  `manager`: escreve, não exclui registros. `viewer`: só leitura.
- Helpers: `isAdmin()`, `isSuperAdmin()`, `canWrite()`, `canDelete()`,
  `canDeleteEvidence()` (SuperAdmin/Admin/Manager).

## Auditoria (`app/Support/Audit.php`, `app/Observers/AuditObserver.php`)
- Eventos "ricos" (documentos NC, login/logout) gravados MANUALMENTE via
  `Audit::record(...)`, com diffs detalhados.
- CRUD genérico via observer (AppServiceProvider): TenantItem, Evidence,
  Funcionario, User, Tenant.
- Tenant do registro: parâmetro explícito > `tenant_id` do modelo (null respeitado
  = evento de plataforma) > `TenantContext` da sessão.
- `AuditLog` usa `BelongsToTenant` de propósito: super admin vê tudo,
  admin só o próprio tenant (`AuditController` aborta 403 se !isAdmin).
- Observers nunca logam sensitivos (password, tokens) nem `last_login_at`.

## Dados fixos (catálogo)
- `CatalogSeeder` importa prontuario.csv, cronograma.csv, checklist.csv
  (upsert por source+code preserva IDs; remove só códigos obsoletos).
  `matriz_nr10_2026.csv` completa o cronograma (norma/interpretação/sugestão/status +
  reclassifica criticidade/setor pela NR-10 2026 — Fase 10 em `decisions.md`).
- Seção = nó com filhos nos níveis 1–2 (`markSections`); seções NÃO geram
  `tenant_items` no bootstrap. No CSV atual do cronograma as seções são todas nível 2.
- CSVs não são versionados no git.

## Testes / ambiente
- PHPUnit 12.5; suíte final obrigatória a cada fase: `php artisan test --compact`.
- `vendor/bin/pint --dirty --format agent` antes de concluir (Pint limpo).
- SQLite em testes: colunas date gravam `Y-m-d H:i:s`.
- Traps do Laravel: `$request->validate()` omite chaves de campos nullable ausentes →
  `$data['campo'] ?? null`; `Rule::exists()->where('bool', false)` quebra (vira `''`) →
  usar `0`; `mapWithKeys` recebe `(value, key)`.
- Frontend: Blade + CSS já compilado (`public/css/app.css`); não rodar `npm run build` —
  reutilizar classes existentes, estilo pontual via `style=` inline.
- **Encoding (obrigatório em TODO ajuste/correção):** antes de concluir qualquer
  edição em view/Blade/PHP, varrer o arquivo procurando mojibake
  (`Ã©`, `Ã§`, `â‚¬`, `â€œ`, `ï¿½`, `Ã£o`, `Â` solto) e conferir os codepoints
  (`<U+XXXX>`) das strings acentuadas/títulos. Corrompido → reparar por BYTE:
  round-trip Latin-1/28591 + tabela de substituição (maiores primeiro), nunca
  "reescrito a olho" nem `Set-Content` (PowerShell regrava em UTF-16/ANSI).
  Backup dos originais antes de mexer. No shell do Windows não há `rg`/`grep`:
  usar `Select-String`, byte array (`[byte[]](0xC3,0xA9)`) ou dump de codepoints —
  NUNCA embutir UTF-8 literal em comando PowerShell (console CP850 deturpa o
  padrão e o teste passa a não achar nada). Conferência final:
  `php artisan view:clear && php artisan view:cache` + testes da área.
- Produção: só com autorização explícita; backups em `storage/app/backups/*.sql`;
  tag `pre-nc-rework` = ponto de rollback.

## Fuso horário (regra transversal — vale para TODAS as seções)
- O sistema inteiro é de uso exclusivo no Brasil. **Toda** implementação tem que
  refletir o fuso **-03:00 `America/Sao_Paulo`** (Brasília/São Paulo): código,
  telas, nomes de arquivo, PDF, e-mail, job, seed e teste.
- A fonte única é `config/app.php` → `'timezone' => 'America/Sao_Paulo'`
  (fixo no config, **sem `env()`**). O Brasil não tem horário de verão desde
  2019, então esse valor é constante — não parametrizar.
- Consequência prática: `now()`, `today()`, `Carbon::now()`, casts de data e
  `date_default_timezone_get()` já saem em -03:00. **Nunca** escrever
  `->setTimezone('America/Sao_Paulo')` no código: é redundante e mascara erro.
  (Havia 11 conversões manuais espalhadas por 8 views + `DashboardStats`;
  foram removidas em favor desta regra.)
- Se um dia aparecer `+00:00`/`Z`/`utc()` em tela, nome de arquivo ou relatório,
  é bug — e não é para "consertar" reconvertindo na view. A causa é
  `app.timezone`/ambiente, não o ponto de exibição.
- **Armadilha já ocorrida:** o nome do arquivo anexado gravava a data em UTC
  (`now()->format('dmY')`). Entre 21:00 e 23:59 de Brasília o dia UTC já é o dia
  seguinte, então a Gestão de Documentos exibia um arquivo com **+1 dia**
  (`img_rnc_02102026_...` numa noite de 01/10). Qualquer data vinda de
  `now()` para dentro de um nome, texto ou documento tem que sair em -03:00.
- Dados gravados antes desta regra foram escritos com o app em UTC e passam a
  ser lidos como -03:00 (ficam 3 h adiantados). São de base de teste; não há
  backfill — zerar a base é mais barato que converter.

## Anexos (evidências)
- Todo upload passa por `EvidenciaUploadService::storedFilename()`. Nenhum
  controller chama `store()`/`storeAs()` direto — se surgir um novo módulo de
  anexos, ele usa o serviço.
- Nome: `{img|doc}_{módulo}_{ddmmaaaa}_{sequencial de 9 dígitos}`, ex.
  `img_rnc_01102026_000000001.png`. `img` = MIME `image/*`; tudo o mais é `doc`.
- A data `ddmmaaaa` vem de `now()` e por isso já está em -03:00 (ver "Fuso
  horário" acima). Não reintroduzir `setTimezone()` aqui.
- Módulos: `rnc` (RNC novo e documentos de NC, ver `decisions.md`), `fun`,
  `prt`, `crn`. O prefixo é o da TELA do upload: cronograma em contexto de
  documento (`?from=document`) usa `rnc`, no plano usa `crn`.
- O `sequencial` é GLOBAL por categoria (uma para imagens, outra para
  documentos/PDF), persistido em `evidence_sequences` e incrementado com
  `EvidenceSequence::proximo()` — transação + `lockForUpdate()`. NÃO é diário
  nem por módulo/cliente: é o que garante que a ordem alfabética do nome
  equivale à ordem de upload do sistema inteiro e que duas telas nunca gerem o
  mesmo nome.
- A sequência NÃO tem teto: ao ultrapassar 999.999.999 continua em
  1000000001… e o `sprintf('%09d')` passa a emitir 10 dígitos. Aviso de "restam
  1000" é regra de negócio separada, não limite técnico.
- O nome enviado pelo usuário é DESCARTADO. `original_name` guarda o nome
  GERADO — é ele que a Gestão de Documentos exibe e que o download entrega.
  Consequência: os anexos existentes ficam com o nome antigo (não há migração
  retroativa) e os testes nunca devem afirmar o nome que o usuário digitou.