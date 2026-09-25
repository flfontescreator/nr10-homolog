<?php

use App\Http\Controllers\AjudaController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CronogramaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\ProntuarioController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TwoStepController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// ---- Autenticação ----
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1')->name('login.store');

    Route::get('forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'store'])->name('password.store');
});

Route::post('logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// ---- Verificação em 2 etapas (usuário autenticado, antes do resto da área) ----
Route::middleware('auth')->group(function () {
    Route::get('verificar', [TwoStepController::class, 'create'])->name('verificar');
    Route::post('verificar', [TwoStepController::class, 'store'])->middleware('throttle:5,1')->name('verificar.store');
    Route::post('verificar/reenviar', [TwoStepController::class, 'resend'])->middleware('throttle:3,1')->name('verificar.resend');
});

// ---- Área autenticada ----
Route::middleware(['auth', '2fa'])->group(function () {
    Route::post('tenant/switch', [TenantController::class, 'switch'])->name('tenant.switch');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('ajuda', [AjudaController::class, 'index'])->name('ajuda');

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::middleware('tenant')->group(function () {
        Route::get('cronograma', [CronogramaController::class, 'index'])->name('cronograma.index');
        Route::get('cronograma/{item}', [CronogramaController::class, 'show'])->name('cronograma.show');
        Route::put('cronograma/{item}', [CronogramaController::class, 'update'])->name('cronograma.update');
        Route::post('cronograma/{item}/evidencias', [CronogramaController::class, 'uploadEvidence'])->name('cronograma.evidencia.upload');

        Route::get('prontuario', [ProntuarioController::class, 'index'])->name('prontuario.index');
        Route::get('prontuario/{item}', [ProntuarioController::class, 'show'])->name('prontuario.show');
        Route::put('prontuario/{item}', [ProntuarioController::class, 'update'])->name('prontuario.update');
        Route::post('prontuario/{item}/evidencias', [ProntuarioController::class, 'uploadEvidence'])->name('prontuario.evidencia.upload');

        Route::get('funcionarios', [FuncionarioController::class, 'index'])->name('funcionarios.index');
        Route::get('funcionarios/novo', [FuncionarioController::class, 'create'])->name('funcionarios.create');
        Route::post('funcionarios', [FuncionarioController::class, 'store'])->name('funcionarios.store');
        Route::get('funcionarios/{funcionario}', [FuncionarioController::class, 'show'])->name('funcionarios.show');
        Route::get('funcionarios/{funcionario}/editar', [FuncionarioController::class, 'edit'])->name('funcionarios.edit');
        Route::put('funcionarios/{funcionario}', [FuncionarioController::class, 'update'])->name('funcionarios.update');
        Route::delete('funcionarios/{funcionario}', [FuncionarioController::class, 'destroy'])->name('funcionarios.destroy');

        Route::get('checklist', [ChecklistController::class, 'index'])->name('checklist.index');
        Route::get('checklist/{item}', [ChecklistController::class, 'show'])->name('checklist.show');
        Route::put('checklist/{item}', [ChecklistController::class, 'update'])->name('checklist.update');
        Route::post('checklist/{item}/evidencias', [ChecklistController::class, 'uploadEvidence'])->name('checklist.evidencia.upload');

        Route::get('documentos', [DocumentoController::class, 'index'])->name('documentos.index');
        Route::get('documentos/{evidence}/download', [DocumentoController::class, 'download'])->name('documentos.download');
        Route::get('documentos/{evidence}/preview', [DocumentoController::class, 'preview'])->name('documentos.preview');
        Route::delete('documentos/{evidence}', [DocumentoController::class, 'destroy'])->name('documentos.destroy');
    });

    Route::delete('evidencias/{evidence}', [CronogramaController::class, 'destroyEvidence'])
        ->name('evidencia.destroy-cronograma');
    Route::delete('evidencias-prontuario/{evidence}', [ProntuarioController::class, 'destroyEvidence'])
        ->name('evidencia.destroy-prontuario');
    Route::delete('evidencias-checklist/{evidence}', [ChecklistController::class, 'destroyEvidence'])
        ->name('evidencia.destroy-checklist');

    // ---- Clientes (super admin) ----
    Route::get('tenants/cnpj/{cnpj}', [TenantController::class, 'lookupCnpj'])->name('tenants.cnpj-lookup');
    Route::resource('tenants', TenantController::class);

    // ---- Usuários ----
    Route::get('usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
    Route::get('usuarios/novo', [UsuarioController::class, 'create'])->name('usuarios.create');
    Route::post('usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
    Route::get('usuarios/{user}', [UsuarioController::class, 'show'])->name('usuarios.show');
    Route::get('usuarios/{user}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
    Route::put('usuarios/{user}', [UsuarioController::class, 'update'])->name('usuarios.update');
    Route::put('usuarios/{user}/reset-link', [UsuarioController::class, 'sendResetLink'])->name('usuarios.reset-link');
    Route::delete('usuarios/{user}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');
});
