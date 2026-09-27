# Índice de regras do projeto

Antes de criar ou editar QUALQUER arquivo:

1. Leia este `index.md`.
2. Leia todo arquivo de regra cujo glob cubra o caminho do arquivo em questão.
3. Rode `grep -rin <palavra-chave> .ai/rules` para pegar regras não capturadas por glob.

| Glob | Arquivo | Conteúdo |
|------|---------|----------|
| `**` | `architecture.md` | Contexto enxuto de arquitetura (módulos, multi-tenant, auditoria, papéis, traps). |
| `**` | `decisions.md` | Log de decisões por fase (regras consolidadas + armadilhas + onde). |

`architecture.md` e `decisions.md` são contexto do sistema: consultam-se em qualquer
implementação que altere comportamento, além dos arquivos de regra da área.