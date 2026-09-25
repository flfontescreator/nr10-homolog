<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'password', 'role', 'tenant_id', 'last_login_at', 'two_step_enabled'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Novo usuário nasce com verificação em 2 etapas ativada por padrão
     * (o default também existe na coluna; aqui é espelhado para que a
     * instância o observe antes da persistência).
     */
    protected $attributes = [
        'two_step_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'last_login_at' => 'datetime',
            'two_step_code_expires_at' => 'datetime',
            'two_step_enabled' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(Evidence::class, 'uploaded_by');
    }

    public function trustedDevices(): HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->role === Role::Admin;
    }

    public function canDelete(): bool
    {
        return $this->isSuperAdmin() || $this->role === Role::Admin;
    }

    /**
     * Exclusão de evidências: SuperAdmin, Admin e Manager.
     * Decisão do produto: managers também podem remover anexos.
     */
    public function canDeleteEvidence(): bool
    {
        return $this->isSuperAdmin() || $this->role === Role::Admin || $this->role === Role::Manager;
    }

    public function canWrite(): bool
    {
        return $this->isAdmin() || $this->role === Role::Manager;
    }

    /**
     * Indica se o usuário usa verificação em 2 etapas no login.
     */
    public function twoStepEnabled(): bool
    {
        return (bool) $this->two_step_enabled;
    }

    /**
     * Gera e devolve o código de verificação em 2 etapas (6 dígitos, expira em 10 min).
     */
    public function issueTwoStepCode(): string
    {
        $code = (string) random_int(100000, 999999);

        $this->forceFill([
            'two_step_code_hash' => Hash::make($code),
            'two_step_code_expires_at' => now()->addMinutes(10),
        ])->save();

        return $code;
    }

    /**
     * Valida o código de verificação informado (até 3 tentativas).
     */
    public function twoStepCodeMatches(string $code): bool
    {
        if ($this->two_step_code_hash === null) {
            return false;
        }

        return Hash::check($code, $this->two_step_code_hash);
    }

    public function twoStepCodeExpired(): bool
    {
        return $this->two_step_code_expires_at === null || $this->two_step_code_expires_at->isPast();
    }

    /**
     * Marca o dispositivo atual como confiável por 7 dias.
     */
    public function trustCurrentDevice(?string $deviceToken): string
    {
        $token = $deviceToken ?: Str::random(64);

        TrustedDevice::updateOrCreate(
            ['user_id' => $this->id, 'device_token_hash' => $token],
            ['expires_at' => now()->addDays(7)]
        );

        return $token;
    }

    public function hasTrustedDevice(?string $deviceToken): bool
    {
        if (! $deviceToken) {
            return false;
        }

        return TrustedDevice::query()
            ->where('user_id', $this->id)
            ->where('device_token_hash', $deviceToken)
            ->where('expires_at', '>', now())
            ->exists();
    }

    public function revokeTrustedDevice(?string $deviceToken): void
    {
        if (! $deviceToken) {
            return;
        }

        TrustedDevice::query()
            ->where('user_id', $this->id)
            ->where('device_token_hash', $deviceToken)
            ->delete();
    }

    /**
     * Clientes (tenants) que o usuário pode acessar.
     */
    public function accessibleTenants(): Collection
    {
        if ($this->isSuperAdmin()) {
            return Tenant::query()->orderBy('name')->get();
        }

        if ($this->tenant) {
            return collect([$this->tenant]);
        }

        return collect();
    }
}
