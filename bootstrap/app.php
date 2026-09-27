<?php

use App\Http\Middleware\EnsureTwoStepVerified;
use App\Http\Middleware\RequireTenant;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // O contexto do cliente precisa estar ativo ANTES do binding de rota,
        // senão o escopo global de tenant não filtra os modelos e registros de
        // outro cliente aparecem (o acesso cross-tenant vira 403 no controller
        // em vez de 404 no binding). Por isso SubstitBindings é reposicionado
        // para depois de SetTenantContext.
        $middleware->web(
            remove: [SubstituteBindings::class, SetTenantContext::class],
            append: [SetTenantContext::class, SubstituteBindings::class],
        );

        $middleware->alias([
            'tenant' => RequireTenant::class,
            '2fa' => EnsureTwoStepVerified::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Erros de integridade referencial (FK): nunca tela em branco;
        // mostra o caminho de resolução para o usuário.
        $exceptions->render(function (QueryException $e, Request $request) {
            $code = $e->errorInfo[1] ?? null;

            if ($code !== null && in_array((int) $code, [1451, 1452], true) && ! $request->expectsJson()) {
                return response()->view('errors.dependents', ['exception' => $e], 422);
            }

            return null;
        });
    })->create();
