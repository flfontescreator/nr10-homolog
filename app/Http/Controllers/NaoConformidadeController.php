<?php

namespace App\Http\Controllers;

use App\Models\RncItem;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Não Conformidades — datagrid somente leitura que agrega as NCs de outros
 * módulos. Fase 1: única fonte são as não conformidades dos RNC
 * (`rnc_items`); as demais fontes (Prontuário, Cronograma, Gestão de
 * Documentos) entram depois.
 *
 * A classificação de cada linha (Não conformidade / Em conformidade) e as
 * tags vêm de UMA expressão SQL compartilhada entre SELECT, WHERE e ORDER BY
 * (`classificacaoSql()`), para filtro, ordenação e exibição nunca divergirem.
 */
class NaoConformidadeController extends Controller
{
    /** Rótulos das tags exibidas no grid. */
    public const TAG_SEM_PRAZO = 'Sem prazo de adequação';

    public const TAG_NAO_PREENCHIDA = 'Não conformidade não preenchida adequitamente';

    public const TAG_ARQUIVADA = 'Arquivada';

    public function index(Request $request): View
    {
        // `ConvertEmptyStringsToNull` transforma `?classificacao=` em null e
        // `$request->query()` devolve o default nesse caso — a chave ausente e a
        // chave vazia (opção "Todos") são diferentes, então lê o bag cru.
        $parametros = $request->query->all();
        $bruto = array_key_exists('classificacao', $parametros)
            ? (string) $parametros['classificacao']
            : 'nao_conformidade';

        $classificacao = in_array($bruto, ['', 'nao_conformidade', 'em_conformidade'], true)
            ? $bruto
            : 'nao_conformidade';

        $ordem = (string) $request->query('ordem', 'desc');
        $ordem = in_array($ordem, ['desc', 'asc'], true) ? $ordem : 'desc';

        $de = $this->dataValida($request->query('de'));
        $ate = $this->dataValida($request->query('ate'));

        $hoje = now()->toDateString();
        [$classificacaoSql, $classificacaoBindings] = $this->classificacaoSql();

        $query = RncItem::query()
            ->with(['rnc', 'situacao'])
            ->join('rncs', 'rncs.id', '=', 'rnc_items.rnc_id')
            ->leftJoin('situacoes', 'situacoes.id', '=', 'rnc_items.situacao_id')
            ->select('rnc_items.*')
            ->selectRaw($classificacaoSql.' as classificacao', $classificacaoBindings);

        if ($classificacao !== '') {
            $query->whereRaw($classificacaoSql.' = ?', [...$classificacaoBindings, $classificacao]);
        }

        if ($de !== null) {
            $query->whereDate('rncs.data_inspecao', '>=', $de);
        }

        if ($ate !== null) {
            $query->whereDate('rncs.data_inspecao', '<=', $ate);
        }

        $direcao = $ordem === 'asc' ? 'ASC' : 'DESC';

        $query
            ->orderByRaw('CASE WHEN '.$classificacaoSql." = 'em_conformidade' THEN 1 ELSE 0 END ASC", $classificacaoBindings)
            ->orderByRaw('CASE WHEN rnc_items.prazo_adequacao IS NOT NULL AND rnc_items.prazo_adequacao < ? AND rnc_items.data_adequacao IS NULL THEN 0 ELSE 1 END ASC', [$hoje])
            ->orderByRaw('CASE WHEN rncs.data_inspecao IS NULL THEN 1 ELSE 0 END ASC')
            ->orderByRaw('rncs.data_inspecao '.$direcao)
            ->orderByRaw('rncs.code ASC')
            ->orderByRaw('rnc_items.numero ASC');

        return view('nao-conformidades.index', [
            'itens' => $query->paginate(25)->withQueryString(),
            'classificacao' => $classificacao,
            'ordem' => $ordem,
            'de' => $de,
            'ate' => $ate,
            'podeAbrir' => (bool) $request->user()?->canWrite(),
        ]);
    }

    /**
     * A expressão única de classificação. O `?` final recebe a data de hoje;
     * os três primeiros recebem as situações que são NC por si só.
     *
     * @return array{0: string, 1: array<int, string>} [expressão SQL, bindings]
     */
    private function classificacaoSql(): array
    {
        $sql = <<<'SQL'
            CASE
                WHEN situacoes.nome IS NULL THEN 'nao_conformidade'
                WHEN situacoes.nome IN (?, ?, ?) THEN 'nao_conformidade'
                WHEN situacoes.nome = 'Conforme' AND rnc_items.prazo_adequacao IS NULL THEN 'nao_conformidade'
                WHEN situacoes.nome = 'Conforme' AND rnc_items.prazo_adequacao < ?
                    AND rnc_items.data_adequacao IS NULL THEN 'nao_conformidade'
                ELSE 'em_conformidade'
            END
            SQL;

        return [$sql, ['Pendente', 'Não adequado', 'Não conforme', now()->toDateString()]];
    }

    private function dataValida(mixed $valor): ?string
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) === 1
            ? $valor
            : null;
    }
}
