<?php

namespace App\Observers;

use App\Support\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * Observer genérico: registra created/updated/deleted de um modelo.
 * Campos sensíveis (senha, tokens) jamais entram no histórico; no update
 * gravam-se apenas os atributos que realmente mudaram.
 */
class AuditObserver
{
    protected array $sensitive = [
        'password',
        'two_step_code_hash',
        'two_step_code_expires_at',
        'remember_token',
    ];

    /**
     * Campos que mudam com frequência sem representar ação relevante
     * (ex.: data de último login registrada automaticamente).
     */
    protected array $ignorable = [
        'last_login_at',
    ];

    public function created(Model $model): void
    {
        Audit::record(
            \sprintf('%s.%s', $this->slug($model), 'created'),
            $this->summary($model, 'criado'),
            $model,
            $this->tenantId($model),
            [],
            $this->filter($model->getAttributes()),
        );
    }

    public function updated(Model $model): void
    {
        $changed = collect($model->getChanges())
            ->except($this->ignorable)
            ->except('updated_at')
            ->all();

        if (empty($changed)) {
            return;
        }

        $old = collect($changed)
            ->mapWithKeys(fn ($value, $key) => [$key => $model->getOriginal($key)])
            ->all();

        $new = collect($changed)
            ->mapWithKeys(fn ($value, $key) => [$key => $model->getAttributes()[$key] ?? null])
            ->all();

        Audit::record(
            \sprintf('%s.%s', $this->slug($model), 'updated'),
            $this->summary($model, 'atualizado'),
            $model,
            $this->tenantId($model),
            $this->filter($old),
            $this->filter($new),
        );
    }

    public function deleted(Model $model): void
    {
        Audit::record(
            \sprintf('%s.%s', $this->slug($model), 'deleted'),
            $this->summary($model, 'excluído'),
            $model,
            $this->tenantId($model),
            $this->filter($model->getAttributes()),
            [],
        );
    }

    protected function slug(Model $model): string
    {
        return \strtolower(class_basename($model));
    }

    protected function summary(Model $model, string $verb): string
    {
        $label = method_exists($model, 'auditLabel') ? $model->auditLabel() : null;
        $subject = $label ?: (class_basename($model).' #'.$model->getKey());

        return \sprintf('%s %s', $subject, $verb);
    }

    protected function tenantId(Model $model): ?int
    {
        $value = $model->getAttribute('tenant_id');

        return $value === null ? null : (int) $value;
    }

    protected function filter(array $data): array
    {
        return collect($data)
            ->except($this->sensitive)
            ->map(fn ($value) => is_bool($value) ? ($value ? 1 : 0) : $value)
            ->filter(fn ($value) => ! is_null($value))
            ->all();
    }
}
