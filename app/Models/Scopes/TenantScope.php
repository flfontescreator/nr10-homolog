<?php

namespace App\Models\Scopes;

use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Escopo global: filtra automaticamente todos os registros pelo cliente ativo.
 * Quando não há cliente selecionado (super admin na plataforma), não filtra.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::id()) {
            $builder->where($model->getTable().'.tenant_id', TenantContext::id());
        }
    }
}