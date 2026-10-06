<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

/**
 * Consulta pública e gratuita de CEP via ViaCEP. Não requer chave nem
 * cadastro — apenas o CEP. Os dados voltam normalizados para Title Case,
 * no mesmo padrão do cadastro de clientes.
 *
 * A busca de CEP é o único momento em que o bairro do endereço da empresa
 * fica conhecido (a Receita Federal não publica bairro), por isso ela também
 * alimenta a base `bairros` — ver TenantController::lookupCep().
 */
class CepLookup
{
    private const API = 'https://viacep.com.br/ws/';

    /**
     * Remove a máscara e valida os 8 dígitos do CEP.
     */
    public static function normalize(string $cep): ?string
    {
        $digits = preg_replace('/\D/', '', $cep);

        if (! $digits || strlen($digits) !== 8) {
            return null;
        }

        return $digits;
    }

    /**
     * Consulta os dados do CEP e devolve logradouro, bairro, cidade e UF
     * no formato do cadastro (Title Case). Retorna null quando o CEP é
     * inválido ou não existe. Lança exceção em falha de rede.
     */
    public static function lookup(string $cep): ?array
    {
        $digits = self::normalize($cep);

        if (! $digits) {
            return null;
        }

        $response = Http::timeout(8)
            ->withHeaders(['Accept' => 'application/json'])
            ->get(self::API.$digits.'/json');

        if ($response->status() === 404 || $response->status() === 400) {
            return null;
        }

        $response->throw();

        $data = $response->json();

        if (! is_array($data) || ! empty($data['erro'])) {
            return null;
        }

        $cidade = trim((string) ($data['localidade'] ?? ''));
        $bairro = trim((string) ($data['bairro'] ?? ''));

        if ($cidade === '' && $bairro === '') {
            return null;
        }

        return [
            'cep' => self::mask($digits),
            'address' => CnpjLookup::titleCase(trim((string) ($data['logradouro'] ?? ''))),
            'bairro' => CnpjLookup::titleCase($bairro),
            'cidade' => CnpjLookup::titleCase($cidade),
            'uf' => strtoupper(trim((string) ($data['uf'] ?? ''))),
        ];
    }

    /**
     * Formata os 8 dígitos como 00000-000.
     */
    public static function mask(string $digits): string
    {
        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }
}
