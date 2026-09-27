<?php

namespace App\Providers;

use App\Mail\PasswordResetMail;
use App\Models\Evidence;
use App\Models\Funcionario;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use App\Observers\AuditObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceRootUrl(config('app.url'));
        }

        ResetPassword::createUrlUsing(function (mixed $notifiable, string $token): string {
            return route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        ResetPassword::toMailUsing(function (mixed $notifiable, string $token): PasswordResetMail {
            $url = route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);

            return (new PasswordResetMail($notifiable->name, $url))
                ->to($notifiable->getEmailForPasswordReset());
        });

        $this->registerAuditObservers();
    }

    /**
     * Auditoria do sistema todo: criação, edição e exclusão dos registros
     * operacionais/administrativos são gravadas no histórico.
     */
    protected function registerAuditObservers(): void
    {
        $models = [
            TenantItem::class,
            Evidence::class,
            Funcionario::class,
            User::class,
            Tenant::class,
        ];

        foreach ($models as $model) {
            $model::observe(AuditObserver::class);
        }
    }
}
