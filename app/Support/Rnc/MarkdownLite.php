<?php

namespace App\Support\Rnc;

/**
 * Conversor mínimo de Markdown para HTML usado nos blocos livres do relatório
 * (introdução, recomendações, resumo, conclusão). Cobre o que o editor de texto
 * simples do RNC produz — títulos, negrito, itálico, listas e parágrafos — sem
 * adicionar dependência externa ao projeto.
 */
class MarkdownLite
{
    public static function toHtml(?string $markdown): string
    {
        $text = trim((string) $markdown);

        if ($text === '') {
            return '';
        }

        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $html = [];
        $paragrafo = [];
        $lista = null;

        $fechaParagrafo = function () use (&$paragrafo, &$html): void {
            if ($paragrafo !== []) {
                $html[] = '<p>'.implode('<br>', array_map([self::class, 'inline'], $paragrafo)).'</p>';
                $paragrafo = [];
            }
        };

        $fechaLista = function () use (&$lista, &$html): void {
            if ($lista !== null) {
                $html[] = '</'.$lista.'>';
                $lista = null;
            }
        };

        foreach ($lines as $raw) {
            $linha = trim($raw);

            if ($linha === '') {
                $fechaParagrafo();
                $fechaLista();

                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.*)$/u', $linha, $m)) {
                $fechaParagrafo();
                $fechaLista();
                $nivel = strlen($m[1]);
                $html[] = "<h{$nivel}>".self::inline($m[2])."</h{$nivel}>";

                continue;
            }

            if (preg_match('/^[-*]\s+(.*)$/u', $linha, $m)) {
                $fechaParagrafo();

                if ($lista !== 'ul') {
                    $fechaLista();
                    $html[] = '<ul>';
                    $lista = 'ul';
                }

                $html[] = '<li>'.self::inline($m[1]).'</li>';

                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.*)$/u', $linha, $m)) {
                $fechaParagrafo();

                if ($lista !== 'ol') {
                    $fechaLista();
                    $html[] = '<ol>';
                    $lista = 'ol';
                }

                $html[] = '<li>'.self::inline($m[1]).'</li>';

                continue;
            }

            $paragrafo[] = $linha;
        }

        $fechaParagrafo();
        $fechaLista();

        return implode("\n", $html);
    }

    public static function inline(string $text): string
    {
        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        $escaped = preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/su', '<em>$1</em>', $escaped) ?? $escaped;

        return $escaped;
    }
}
