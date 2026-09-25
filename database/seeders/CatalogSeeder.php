<?php

namespace Database\Seeders;

use App\Enums\Source;
use App\Models\CatalogItem;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    protected array $codes = [];

    /**
     * Códigos importados por fonte (para limpeza de linhas obsoletas sem
     * invalidar os IDs — os tenant_items dependem dos IDs do catálogo).
     */
    protected array $sourceCodes = [];

    /**
     * Importa o conteúdo FIXO das planilhas (itens/subitens + termos técnicos + criticidade).
     * As colunas de controle (datas, status, percentuais, evidências...) não entram no catálogo.
     *
     * NÃO apaga a tabela antes de importar: os registros são atualizados pela chave
     * (source, code), preservando os IDs. Assim, os tenant_items que apontam para o
     * catálogo permanecem íntegros após reimportações. Apenas códigos que deixaram de
     * existir na planilha são removidos ao final.
     */
    public function run(): void
    {
        $this->importProntuario();
        $this->importCronograma();
        $this->importChecklist();

        $this->removeStaleCodes();

        $this->markSections();
    }

    protected function importProntuario(): void
    {
        $rows = $this->readCsv(storage_path('app/imports/prontuario.csv'));

        foreach ($rows as $row) {
            // Pula título/preamble (linhas sem código no topo).
            $this->normalizeRow($row);
            $code = trim((string) $row[0]);

            if (! $this->validCode($code)) {
                continue;
            }

            $this->upsertItem(Source::Prontuario, $code, [
                'title' => $this->clean((string) ($row[1] ?? '')),
            ]);
        }
    }

    protected function importCronograma(): void
    {
        $rows = $this->readCsv(storage_path('app/imports/cronograma.csv'));

        // A planilha agora tem UM setor por linha; subitens com vários setores
        // aparecem em linhas repetidas. Agrupamos por código antes de gravar.
        $groups = [];

        // Linha 0: título; linha 1: cabeçalho; dados a partir da linha 2.
        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue;
            }

            $this->normalizeRow($row);
            $cell = $this->clean((string) ($row[1] ?? ''));

            // O código vem junto com a descrição na planilha: "10.3.1 No processo de ..."
            if (! preg_match('/^([\d]+(?:\.[\d]+)*)\s*(.*)$/s', $cell, $m)) {
                continue;
            }

            $code = $m[1];
            $setor = $this->clean((string) ($row[3] ?? ''));

            $groups[$code]['title'] ??= $this->clean($m[2]);
            $groups[$code]['criticidade'] ??= $this->clean((string) ($row[2] ?? ''));
            $groups[$code]['detalhamento'] ??= $this->clean((string) ($row[4] ?? ''));
            $groups[$code]['setores'][] = $setor;
        }

        foreach ($groups as $code => $group) {
            $group['setores'] = array_values(array_filter(array_unique(array_map(
                fn ($setor) => $this->clean((string) $setor),
                $group['setores'],
            ))));

            $this->upsertItem(Source::Cronograma, $code, $group);
        }
    }

    protected function importChecklist(): void
    {
        $rows = $this->readCsv(storage_path('app/imports/checklist.csv'));

        // A planilha agora tem UM setor por linha; itens com vários setores
        // aparecem em linhas repetidas. Agrupamos por código antes de gravar.
        $groups = [];

        // Linha 0: título; linha 1: cabeçalho; dados a partir da linha 2.
        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue;
            }

            $this->normalizeRow($row);
            $code = trim((string) ($row[0] ?? ''));

            if (! $this->validCode($code)) {
                continue;
            }

            $setor = $this->clean((string) ($row[3] ?? ''));

            $groups[$code]['title'] ??= $this->clean((string) ($row[1] ?? ''));
            $groups[$code]['criticidade'] ??= $this->clean((string) ($row[2] ?? ''));
            $groups[$code]['detalhamento'] ??= $this->clean((string) ($row[4] ?? ''));
            $groups[$code]['setores'][] = $setor;
        }

        foreach ($groups as $code => $group) {
            $group['setores'] = array_values(array_filter(array_unique(array_map(
                fn ($setor) => $this->clean((string) $setor),
                $group['setores'],
            ))));

            $this->upsertItem(Source::Checklist, $code, $group);
        }
    }

    protected function upsertItem(Source $source, string $code, array $extra): void
    {
        $parts = array_slice(array_merge(explode('.', $code), [0, 0, 0, 0]), 0, 4);

        $description = (string) ($extra['title'] ?? '');
        $setores = array_values(array_filter((array) ($extra['setores'] ?? [])));
        $setor = (string) ($extra['setor'] ?? '');

        if ($setor === '' && $setores !== []) {
            $setor = (string) ($setores[0] ?? '');
        }

        CatalogItem::updateOrCreate(
            ['source' => $source->value, 'code' => $code],
            [
                'n1' => (int) $parts[0],
                'n2' => (int) $parts[1],
                'n3' => (int) $parts[2],
                'n4' => (int) $parts[3],
                'title' => mb_substr($description, 0, 250),
                'description' => $description,
                'criticidade' => $extra['criticidade'] ?? null,
                'setor' => $setor ?: null,
                'setores' => $setores ?: null,
                'detalhamento' => $extra['detalhamento'] ?? null,
                'sort' => $this->codes[$source->value.'.'.$code] ?? 0,
            ]
        );

        $this->codes[$source->value.'.'.$code] = ($this->codes[$source->value.'.'.$code] ?? 0) + 1;
        $this->sourceCodes[$source->value][] = $code;
    }

    /**
     * Remove do catálogo apenas códigos que deixaram de existir nas planilhas.
     * Mantém íntegros os IDs das linhas que continuam na fonte (requisito para
     * não quebrar a ligação com os tenant_items).
     */
    protected function removeStaleCodes(): void
    {
        foreach (array_keys($this->sourceCodes) as $source) {
            $currentCodes = array_values(array_unique($this->sourceCodes[$source]));

            CatalogItem::query()
                ->where('source', $source)
                ->whereNotIn('code', $currentCodes)
                ->delete();
        }
    }

    /**
     * Seção = nó que contém filhos. Ex.: "10.3" (cronograma) e "1..7" (prontuário)
     * agrupam subitens; um nó intermediário como "10.4.3" também agrupa "10.4.3.1".
     */
    protected function markSections(): void
    {
        $items = CatalogItem::all()->groupBy('source');

        foreach ($items as $source => $group) {
            $codes = $group->pluck('code', 'id');

            $sections = $codes->filter(function (string $code) use ($codes) {
                return $codes->contains(
                    fn (string $other) => $other !== $code && str_starts_with($other, $code.'.')
                );
            });

            $sections = $sections->mapWithKeys(fn ($c) => [$c => true]);

            foreach ($group as $item) {
                $segments = substr_count($item->code, '.') + 1;
                $isSection = ($sections[$item->code] ?? false) && in_array($segments, [1, 2], true);

                if ($item->is_section !== $isSection) {
                    $item->is_section = $isSection;
                    $item->save();
                }
            }
        }
    }

    protected function readCsv(string $path): array
    {
        return self::csvRows($path);
    }

    public static function csvRows(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];

        while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    protected function normalizeRow(array &$row): void
    {
        // Remove BOM (assinatura UTF-8) do primeiro campo.
        $row[0] = str_replace("\xEF\xBB\xBF", '', (string) $row[0]);
    }

    protected function validCode(string $code): bool
    {
        return (bool) preg_match('/^[\d]+(?:\.[\d]+)*$/', $code);
    }

    protected function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }
}
