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
- **Não Conformidades / Documentos** (`nc-documents.*` + `checklist.index`):
  documento DN-XX; seleção = seções + subitens DO CRONOGRAMA; versões/snapshots;
  finalizar/reabrir. Estado de trabalho por documento em `nc_document_items`
  (cópia própria); plano/cronograma nunca trava;
  **biblioteca de documentos**: arquivos reutilizáveis via vínculos N:N —
  `evidence_document` (documento, badges "Documento de referência" em
  `documentos.index`, picker no create/edit), `evidence_document_item`
  (evidência ↔ sub-item de DN, contexto de anexo) e `evidence_tenant_item`
  (evidência ↔ sub-item do plano); `tenant_item_id` na evidência é âncora/origem.
  Regras completas em `decisions.md` (Fases 1, 5, 6 e 7).
- **Checklist legado** (`checklist.*`, source=checklist): registros históricos;
  `checklist.index` hoje lista os documentos, não os itens antigos.
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
- Produção: só com autorização explícita; backups em `storage/app/backups/*.sql`;
  tag `pre-nc-rework` = ponto de rollback.