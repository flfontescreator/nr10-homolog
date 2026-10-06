<?php

namespace App\Support\Rnc;

use App\Enums\RncModelo;
use App\Models\Rnc;
use Illuminate\Support\Carbon;

/**
 * Monta o Markdown do RNC a partir do snapshot congelado da revisão. O mesmo
 * Markdown alimenta a tela pública, o download `.md` e a conversão em PDF, de
 * modo que o que o cliente lê é sempre o mesmo conteúdo.
 */
class RncMarkdownBuilder
{
    /**
     * @param  array<string, mixed>  $snapshot  `Rnc::buildSnapshot()`
     */
    public function build(Rnc $rnc, array $snapshot): string
    {
        return ($snapshot['modelo'] ?? RncModelo::Tecnica->value) === RncModelo::Fotografica->value
            ? $this->fotografica($rnc, $snapshot)
            : $this->tecnica($rnc, $snapshot);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function tecnica(Rnc $rnc, array $snapshot): string
    {
        $lines = [];

        $lines[] = '# RELATÓRIO DE NÃO CONFORMIDADE';
        $lines[] = '';
        $lines[] = '## '.trim((string) ($snapshot['titulo'] ?? $rnc->titulo));
        $lines[] = '';
        $lines[] = sprintf('**%s** — %s', $snapshot['codigo'] ?? $rnc->code, $this->labelFor($rnc));
        $lines[] = '';

        $lines[] = $this->identificacao($snapshot);
        $lines[] = '';

        if (! empty($snapshot['descricao'])) {
            $lines[] = trim((string) $snapshot['descricao']);
            $lines[] = '';
        }

        $itens = $snapshot['itens'] ?? [];

        $lines[] = '## Não conformidades ('.count($itens).')';
        $lines[] = '';

        if ($itens === []) {
            $lines[] = '_Nenhuma não conformidade registrada._';
            $lines[] = '';
        }

        foreach ($itens as $item) {
            $lines[] = sprintf('### %s. %s', $item['numero'] ?? '-', trim((string) ($item['titulo'] ?? '')));
            $lines[] = '';
            $lines[] = $this->table(['Campo', 'Conteúdo'], $this->pairs([
                'Criticidade' => $item['criticidade'] ?? null,
                'Classificação de risco' => $item['classificacao_risco'] ?? null,
                'Situação' => $item['situacao'] ?? null,
                'Descrição' => $item['descricao'] ?? null,
                'Recomendação' => $item['recomendacao'] ?? null,
                'Prazo de adequação' => $this->date($item['prazo_adequacao'] ?? null),
                'Data de adequação' => $this->date($item['data_adequacao'] ?? null),
            ]));
            $lines[] = '';

            $referencias = collect($item['referencias'] ?? [])
                ->map(fn ($ref) => trim((string) ($ref['codigo'] ?? '')))
                ->filter()
                ->values()
                ->all();

            $lines[] = '**Referências normativas:** '.($referencias === [] ? '-' : implode(', ', $referencias));
            $lines[] = '';

            if (! empty($item['evidencias'])) {
                $lines[] = '**Evidências:**';

                foreach ($item['evidencias'] as $evidencia) {
                    $detalhe = trim(implode(' — ', array_filter([
                        $evidencia['nome'] ?? null,
                        $evidencia['descricao'] ?? null,
                        $this->validade($evidencia['validade'] ?? null),
                    ], fn ($valor) => $valor !== null && $valor !== '')));

                    $lines[] = '- '.$detalhe;
                }

                $lines[] = '';
            }
        }

        $lines[] = '## Recomendações de boas práticas da NR-10';
        $lines[] = '';
        $lines[] = $this->bloco($snapshot['recomendacoes'] ?? null);
        $lines[] = '';

        return $this->assinatura($lines, $rnc, $snapshot);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function fotografica(Rnc $rnc, array $snapshot): string
    {
        $lines = [];

        $lines[] = '# RELATÓRIO DE NÃO CONFORMIDADE';
        $lines[] = '';
        $lines[] = '**Descrição**';
        $lines[] = '';
        $lines[] = sprintf('**%s** — %s', $snapshot['codigo'] ?? $rnc->code, $this->labelFor($rnc));
        $lines[] = '';

        $lines[] = $this->identificacao($snapshot);
        $lines[] = '';

        $lines[] = '## Resumo';
        $lines[] = '';
        $lines[] = $this->bloco($snapshot['resumo'] ?? null);

        $itens = $snapshot['itens'] ?? [];

        $lines[] = '## Registro fotográfico';
        $lines[] = '';

        if ($itens === []) {
            $lines[] = '_Nenhum registro fotográfico._';
            $lines[] = '';
        }

        foreach ($itens as $item) {
            $lines[] = sprintf('### %s. %s', $item['numero'] ?? '-', trim((string) ($item['titulo'] ?? '')));
            $lines[] = '';

            if (! empty($item['descricao'])) {
                $lines[] = trim((string) $item['descricao']);
                $lines[] = '';
            }

            foreach ($item['evidencias'] ?? [] as $evidencia) {
                $lines[] = '- '.trim(implode(' — ', array_filter([
                    $evidencia['nome'] ?? null,
                    $evidencia['descricao'] ?? null,
                ], fn ($valor) => $valor !== null && $valor !== '')));
            }

            $lines[] = '';
        }

        $lines[] = '## Conclusão';
        $lines[] = '';
        $lines[] = $this->bloco($snapshot['conclusao'] ?? null);
        $lines[] = '';

        return $this->assinatura($lines, $rnc, $snapshot);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function identificacao(array $snapshot): string
    {
        return $this->table(
            ['Data de Inspeção', 'Projeto', 'Responsável', 'Número'],
            [[
                $this->date($snapshot['data_inspecao'] ?? null) ?? '—',
                (string) ($snapshot['projeto'] ?? '—'),
                (string) ($snapshot['responsavel_nome'] ?? '—'),
                (string) ($snapshot['codigo'] ?? '—'),
            ]]
        );
    }

    private function bloco(?string $markdown): string
    {
        $text = trim((string) $markdown);

        return $text === '' ? '_Sem conteúdo._' : $text;
    }

    /**
     * @param  array<int, string>  $lines
     * @param  array<string, mixed>  $snapshot
     */
    private function assinatura(array $lines, Rnc $rnc, array $snapshot): string
    {
        $nome = trim((string) ($snapshot['responsavel_nome'] ?? $rnc->responsavel_nome ?? ''));
        $cargo = trim((string) ($snapshot['responsavel_cargo'] ?? $rnc->responsavel_cargo ?? ''));

        $lines[] = '## Responsável pela emissão';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = '_____________________________________________';
        $lines[] = $cargo === '' ? $nome : ($nome === '' ? $cargo : $nome.' — '.$cargo);
        $lines[] = '```';
        $lines[] = '';

        return implode("\n", $lines)."\n";
    }

    private function labelFor(Rnc $rnc): string
    {
        return $rnc->current_revision > 0
            ? Rnc::makeRevisionLabel($rnc->current_revision)
            : 'Rascunho (não publicado)';
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<int, array{0: string, 1: string}>
     */
    private function pairs(array $values): array
    {
        $rows = [];

        foreach ($values as $key => $value) {
            $value = $value === null ? '' : trim((string) $value);

            if ($value === '') {
                continue;
            }

            $rows[] = [(string) $key, $value];
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string>>  $rows
     */
    private function table(array $headers, array $rows): string
    {
        if ($rows === []) {
            return '_Sem dados._';
        }

        $out = $this->row($headers);
        $out .= '|'.str_repeat(' --- |', count($headers));

        foreach ($rows as $row) {
            $out .= "\n".$this->row($row);
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function row(array $cells): string
    {
        return '|'.implode(
            ' | ',
            array_map(fn ($cell) => str_replace(['|', "\n"], ['\\|', '<br>'], (string) $cell), $cells)
        ).'|';
    }

    private function date(mixed $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $value;
        }
    }

    private function validade(mixed $value): ?string
    {
        $data = $this->date($value);

        return $data === null ? null : 'validade '.$data;
    }
}
