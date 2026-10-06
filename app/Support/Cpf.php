<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Normalização, máscara e validação de CPF.
 *
 * O CPF é gravado SEM máscara (11 dígitos) — a máscara é apenas de exibição.
 * A validação é pelos dois dígitos verificadores, que reprova as sequências
 * repetidas (000.000.000-00, 111.111.111-11, ...).
 */
class Cpf
{
    /**
     * Remove tudo que não for dígito e devolve null quando não há 11 dígitos
     * válidos (ou quando o campo veio vazio).
     */
    public static function normalize(?string $cpf): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $cpf);

        if ($digits === '' || $digits === null) {
            return null;
        }

        return strlen($digits) === 11 ? $digits : null;
    }

    /**
     * Formata os 11 dígitos como 000.000.000-00.
     */
    public static function mask(?string $cpf): ?string
    {
        $digits = self::normalize($cpf);

        if (! $digits) {
            return null;
        }

        return substr($digits, 0, 3).'.'
            .substr($digits, 3, 3).'.'
            .substr($digits, 6, 3).'-'
            .substr($digits, 9, 2);
    }

    /**
     * Confere os dígitos verificadores do CPF.
     */
    public static function isValid(?string $cpf): bool
    {
        $digits = self::normalize($cpf);

        if (! $digits || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        foreach ([9, 10] as $length) {
            $sum = 0;

            for ($i = 0; $i < $length; $i++) {
                $sum += (int) $digits[$i] * ($length + 1 - $i);
            }

            $remainder = $sum % 11;
            $check = $remainder < 2 ? 0 : 11 - $remainder;

            if ((int) $digits[$length] !== $check) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normaliza e valida o CPF, devolvendo os dígitos (ou null quando vazio).
     * Lança erro de validação apontando o campo quando os dígitos não fecham.
     */
    public static function validateOrFail(?string $cpf, string $field = 'cpf'): ?string
    {
        $digits = self::normalize($cpf);

        if ($digits === null) {
            if (preg_replace('/\D/', '', (string) $cpf) !== '') {
                throw ValidationException::withMessages([
                    $field => 'O CPF deve ter 11 dígitos.',
                ]);
            }

            return null;
        }

        if (! self::isValid($digits)) {
            throw ValidationException::withMessages([
                $field => 'CPF inválido.',
            ]);
        }

        return $digits;
    }
}
