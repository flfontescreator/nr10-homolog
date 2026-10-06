<?php

namespace App\Support\Rnc;

/**
 * Formata as referências normativas de uma NC em texto corrido, agrupadas por
 * norma (ex.: "NR-10 Item(s): 10.1.2 10.3.1"). Snapshots antigos, gravados
 * antes da ABNT NBR 5410, não têm o código da norma e caem no fallback NR-10.
 */
class ReferenciasNormativas
{
    /**
     * @param  array<int, array<string, mixed>>  $referencias
     */
    public static function texto(array $referencias): ?string
    {
        $grupos = [];

        foreach ($referencias as $referencia) {
            $codigo = trim((string) ($referencia['codigo'] ?? ''));

            if ($codigo === '') {
                continue;
            }

            $norma = trim((string) ($referencia['norma'] ?? ''));
            $grupos[$norma !== '' ? $norma : 'NR-10'][] = $codigo;
        }

        if ($grupos === []) {
            return null;
        }

        $partes = [];

        foreach ($grupos as $norma => $codigos) {
            usort($codigos, fn (string $a, string $b) => strnatcasecmp($a, $b));
            $partes[] = $norma.' Item(s): '.implode(' ', $codigos);
        }

        return implode("\n", $partes);
    }
}
