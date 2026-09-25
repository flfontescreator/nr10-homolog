<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Porta de entrada do "cliente ativo" (tenant) da sessão.
 * Antes de qualquer consulta de dados de cliente, o middleware SetTenantContext
 * define aqui qual cliente está sendo operado.
 */
class TenantContext
{
    protected static ?Tenant $current = null;

    public static function set(?Tenant $tenant): void
    {
        static::$current = $tenant;
    }

    public static function current(): ?Tenant
    {
        return static::$current;
    }

    public static function id(): ?int
    {
        return static::$current?->id;
    }

    public static function name(): ?string
    {
        return static::$current?->name;
    }
}