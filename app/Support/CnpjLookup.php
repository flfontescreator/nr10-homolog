<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Consulta pública e gratuita de CNPJ via BrasilAPI (Receita Federal).
 * Não requer chave nem cadastro — apenas o CNPJ. Os dados retornados são
 * normalizados para Title Case (somente as iniciais em maiúsculas).
 */
class CnpjLookup
{
    private const API = 'https://brasilapi.com.br/api/cnpj/v1/';

    /**
     * Remove máscara e valida os 14 dígitos do CNPJ.
     */
    public static function normalize(string $cnpj): ?string
    {
        $digits = preg_replace('/\D/', '', $cnpj);

        if (! $digits || strlen($digits) !== 14) {
            return null;
        }

        return $digits;
    }

    /**
     * Consulta os dados do CNPJ e devolve razão social e endereço no formato
     * do cadastro (Title Case). Retorna null quando o CNPJ é inválido ou a
     * Receita Federal não possui o registro. Lança exceção em falha de rede.
     */
    public static function lookup(string $cnpj): ?array
    {
        $digits = self::normalize($cnpj);

        if (! $digits) {
            return null;
        }

        $response = Http::timeout(8)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::API.$digits);

        if ($response->status() === 404 || $response->status() === 400) {
            return null;
        }

        $response->throw();

        $data = $response->json();

        if (! is_array($data) || empty($data['razao_social'])) {
            return null;
        }

        return [
            'name' => self::titleCase((string) $data['razao_social']),
            'address' => self::mountAddress($data),
        ];
    }

    private static function mountAddress(array $data): string
    {
        $street = implode(', ', array_filter([
            self::titleCase((string) ($data['logradouro'] ?? '')),
            trim((string) ($data['numero'] ?? '')),
        ], fn ($v) => $v !== ''));

        $neighborhood = self::titleCase((string) ($data['bairro'] ?? ''));
        $city = self::titleCase((string) ($data['municipio'] ?? ''));
        $uf = strtoupper(trim((string) ($data['uf'] ?? '')));
        $cep = trim((string) ($data['cep'] ?? ''));

        $location = implode(' — ', array_filter([$city, $uf], fn ($v) => $v !== ''));

        return implode(', ', array_filter([
            $street,
            in_array($neighborhood, ['', $city], true) ? '' : $neighborhood,
            $location,
            $cep !== '' ? 'CEP '.$cep : '',
        ], fn ($v) => $v !== ''));
    }

    /**
     * Converte um texto em Title Case respeitando as contrações comuns do
     * português e mantendo siglas empresariais tradicionais (LTDA, EIRELI,
     * etc.) em maiúsculas.
     */
    public static function titleCase(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        $lower = [
            'a', 'ao', 'aos', 'as', 'com', 'da', 'das', 'de', 'do', 'dos',
            'e', 'em', 'na', 'nas', 'no', 'nos', 'o', 'os', 'para', 'por',
            '&',
        ];

        $words = preg_split('/\s+/', mb_strtolower($value, 'UTF-8')) ?: [];

        $result = [];

        foreach ($words as $index => $word) {
            if (in_array($word, $lower, true) && $index !== 0) {
                $result[] = $word;
            } else {
                $result[] = mb_convert_case($word, MB_CASE_TITLE, 'UTF-8');
            }
        }

        // Siglas empresariais tradicionais permanecem em maiúsculas.
        return preg_replace_callback(
            '/\b(ltda|eireli|epp|me|s\/a)\b/i',
            fn ($m) => mb_strtoupper($m[1], 'UTF-8'),
            implode(' ', $result)
        );
    }
}
