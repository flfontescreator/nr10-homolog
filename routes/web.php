<?php

use App\Http\Controllers\AjudaController;
use App\Http\Controllers\AuditController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ChecklistController;
use App\Http\Controllers\CronogramaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentoController;
use App\Http\Controllers\FuncionarioController;
use App\Http\Controllers\NcDocumentController;
use App\Http\Controllers\ProntuarioCatalogController;
use App\Http\Controllers\ProntuarioController;
use App\Http\Controllers\RncCatalogoController;
use App\Http\Controllers\RncController;
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

// ---- Link público do RNC (sem autenticação: token + validade de 7 dias) ----
Route::get('rnc/public/{token}', [RncController::class, 'publicShow'])->name('rnc.public.show');
Route::get('rnc/public/{token}/pdf', [RncController::class, 'publicPdf'])->name('rnc.public.pdf');

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
    Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');
    Route::get('ajuda', [AjudaController::class, 'index'])->name('ajuda');

    Route::get('auditoria', [AuditController::class, 'index'])->name('auditoria.index');

    Route::get('/', function () {
        return redirect()->route('dashboard');
    });

    Route::middleware('tenant')->group(function () {
        Route::get('cronograma', [CronogramaController::class, 'index'])->name('cronograma.index');
        Route::get('cronograma/{item}', [CronogramaController::class, 'show'])->name('cronograma.show');
        Route::put('cronograma/{item}', [CronogramaController::class, 'update'])->name('cronograma.update');
        Route::post('cronograma/{item}/evidencias', [CronogramaController::class, 'uploadEvidence'])->name('cronograma.evidencia.upload');
        Route::post('cronograma/{item}/biblioteca', [CronogramaController::class, 'attachLibraryEvidence'])->name('cronograma.biblioteca.attach');
        Route::delete('cronograma/{item}/evidencias/{evidence}', [CronogramaController::class, 'destroyEvidenceLink'])->name('cronograma.evidencia.destroy');

        Route::get('prontuario/catalogo', [ProntuarioCatalogController::class, 'index'])->name('prontuario.catalogo.index');
        Route::post('prontuario/catalogo/secoes', [ProntuarioCatalogController::class, 'storeSection'])->name('prontuario.catalogo.section.store');
        Route::post('prontuario/catalogo/itens', [ProntuarioCatalogController::class, 'storeItem'])->name('prontuario.catalogo.item.store');
        Route::put('prontuario/catalogo/{catalogItem}', [ProntuarioCatalogController::class, 'update'])->name('prontuario.catalogo.update');
        Route::delete('prontuario/catalogo/{catalogItem}', [ProntuarioCatalogController::class, 'destroy'])->name('prontuario.catalogo.destroy');

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
        Route::post('funcionarios/{funcionario}/itens', [FuncionarioController::class, 'storeItem'])->name('funcionarios.items.store');
        Route::get('funcionarios/{funcionario}/itens/{item}', [FuncionarioController::class, 'showItem'])->name('funcionarios.item.show');
        Route::put('funcionarios/{funcionario}/itens/{item}', [FuncionarioController::class, 'updateItem'])->name('funcionarios.item.update');
        Route::delete('funcionarios/{funcionario}/itens/{item}', [FuncionarioController::class, 'destroyItem'])->name('funcionarios.items.destroy');
        Route::post('funcionarios/{funcionario}/itens/{item}/evidencias', [FuncionarioController::class, 'uploadItemEvidence'])->name('funcionarios.item.evidencia.upload');
        Route::delete('funcionarios/{funcionario}/itens/{item}/evidencias/{evidence}', [FuncionarioController::class, 'destroyItemEvidence'])->name('funcionarios.item.evidencia.destroy');

        Route::get('checklist', [ChecklistController::class, 'index'])->name('checklist.index');
        Route::get('checklist/{item}', [ChecklistController::class, 'show'])->name('checklist.show');
        Route::put('checklist/{item}', [ChecklistController::class, 'update'])->name('checklist.update');
        Route::post('checklist/{item}/evidencias', [ChecklistController::class, 'uploadEvidence'])->name('checklist.evidencia.upload');

        Route::get('checklist/documentos/novo', [NcDocumentController::class, 'create'])->name('nc-documents.create');
        Route::post('checklist/documentos', [NcDocumentController::class, 'store'])->name('nc-documents.store');
        Route::get('checklist/documentos/{document}', [NcDocumentController::class, 'show'])->name('nc-documents.show');
        Route::get('checklist/documentos/{document}/editar', [NcDocumentController::class, 'edit'])->name('nc-documents.edit');
        Route::get('checklist/documentos/{document}/itens', [NcDocumentController::class, 'itemsIndex'])->name('nc-documents.items.index');
        Route::post('checklist/documentos/{document}/itens', [NcDocumentController::class, 'itemsAttach'])->name('nc-documents.items.attach');
        Route::put('checklist/documentos/{document}/itens/{item}', [NcDocumentController::class, 'itemsUpdate'])->name('nc-documents.items.update');
        Route::delete('checklist/documentos/{document}/itens/{item}', [NcDocumentController::class, 'itemsDestroy'])->name('nc-documents.items.destroy');
        Route::put('checklist/documentos/{document}', [NcDocumentController::class, 'update'])->name('nc-documents.update');
        Route::post('checklist/documentos/{document}/finalizar', [NcDocumentController::class, 'finalize'])->name('nc-documents.finalize');
        Route::post('checklist/documentos/{document}/reabrir', [NcDocumentController::class, 'reopen'])->name('nc-documents.reopen');
        Route::delete('checklist/documentos/{document}', [NcDocumentController::class, 'destroy'])->name('nc-documents.destroy');
        Route::delete('checklist/documentos/{document}/biblioteca/{evidence}', [NcDocumentController::class, 'detachLibraryEvidence'])->name('nc-documents.evidence.detach');

        Route::get('documentos', [DocumentoController::class, 'index'])->name('documentos.index');
        Route::get('documentos/{evidence}/download', [DocumentoController::class, 'download'])->name('documentos.download');
        Route::get('documentos/{evidence}/preview', [DocumentoController::class, 'preview'])->name('documentos.preview');
        Route::delete('documentos/{evidence}', [DocumentoController::class, 'destroy'])->name('documentos.destroy');

        // ---- RNC (Relatório Formal de Não Conformidade) — módulo NOVO, independente de Não Conformidades
        Route::get('rnc', [RncController::class, 'index'])->name('rnc.index');
        Route::get('rnc/novo', [RncController::class, 'create'])->name('rnc.create');
        Route::post('rnc', [RncController::class, 'store'])->name('rnc.store');
        Route::get('rnc/{rnc}', [RncController::class, 'show'])->whereNumber('rnc')->name('rnc.show');
        Route::get('rnc/{rnc}/editar', [RncController::class, 'edit'])->whereNumber('rnc')->name('rnc.edit');
        Route::put('rnc/{rnc}', [RncController::class, 'update'])->whereNumber('rnc')->name('rnc.update');
        Route::delete('rnc/{rnc}', [RncController::class, 'destroy'])->whereNumber('rnc')->name('rnc.destroy');

        Route::post('rnc/{rnc}/itens', [RncController::class, 'storeItem'])->whereNumber('rnc')->name('rnc.item.store');
        Route::put('rnc/{rnc}/itens/{item}', [RncController::class, 'updateItem'])->whereNumber(['rnc', 'item'])->name('rnc.item.update');
        Route::delete('rnc/{rnc}/itens/{item}', [RncController::class, 'destroyItem'])->whereNumber(['rnc', 'item'])->name('rnc.item.destroy');

        Route::post('rnc/{rnc}/itens/{item}/evidencias', [RncController::class, 'uploadItemEvidence'])->whereNumber(['rnc', 'item'])->name('rnc.item.evidencia.upload');
        Route::delete('rnc/{rnc}/itens/{item}/evidencias/{evidence}', [RncController::class, 'destroyItemEvidence'])->whereNumber(['rnc', 'item', 'evidence'])->name('rnc.item.evidencia.destroy');

        Route::post('rnc/{rnc}/publicar', [RncController::class, 'publish'])->whereNumber('rnc')->name('rnc.publish');
        Route::post('rnc/{rnc}/republicar', [RncController::class, 'republish'])->whereNumber('rnc')->name('rnc.republish');
        Route::post('rnc/{rnc}/revisoes/{revision}/renovar-link', [RncController::class, 'renewPublicLink'])->whereNumber(['rnc', 'revision'])->name('rnc.revision.link.renew');
        Route::get('rnc/{rnc}/revisoes/{revision}/markdown', [RncController::class, 'revisionMarkdown'])->whereNumber(['rnc', 'revision'])->name('rnc.revision.markdown');
        Route::get('rnc/{rnc}/revisoes/{revision}/pdf', [RncController::class, 'revisionPdf'])->whereNumber(['rnc', 'revision'])->name('rnc.revision.pdf');
        Route::get('rnc/{rnc}/revisoes/{revision}/imprimir', [RncController::class, 'revisionPrint'])->whereNumber(['rnc', 'revision'])->name('rnc.revision.print');
        Route::post('rnc/{rnc}/revisoes/{revision}/enviar', [RncController::class, 'send'])->whereNumber(['rnc', 'revision'])->name('rnc.revision.send');

        // Catálogos globais do RNC (Admin/SuperAdmin)
        Route::get('rnc/catalogos', [RncCatalogoController::class, 'index'])->name('rnc.catalogo.index');
        Route::post('rnc/catalogos/projetos', [RncCatalogoController::class, 'storeProjeto'])->name('rnc.catalogo.projeto.store');
        Route::put('rnc/catalogos/projetos/{projeto}', [RncCatalogoController::class, 'updateProjeto'])->name('rnc.catalogo.projeto.update');
        Route::delete('rnc/catalogos/projetos/{projeto}', [RncCatalogoController::class, 'destroyProjeto'])->name('rnc.catalogo.projeto.destroy');
        Route::post('rnc/catalogos/criticidades', [RncCatalogoController::class, 'storeCriticidade'])->name('rnc.catalogo.criticidade.store');
        Route::put('rnc/catalogos/criticidades/{criticidade}', [RncCatalogoController::class, 'updateCriticidade'])->name('rnc.catalogo.criticidade.update');
        Route::delete('rnc/catalogos/criticidades/{criticidade}', [RncCatalogoController::class, 'destroyCriticidade'])->name('rnc.catalogo.criticidade.destroy');
        Route::post('rnc/catalogos/classificacoes', [RncCatalogoController::class, 'storeClassificacao'])->name('rnc.catalogo.classificacao.store');
        Route::put('rnc/catalogos/classificacoes/{classificacao}', [RncCatalogoController::class, 'updateClassificacao'])->name('rnc.catalogo.classificacao.update');
        Route::delete('rnc/catalogos/classificacoes/{classificacao}', [RncCatalogoController::class, 'destroyClassificacao'])->name('rnc.catalogo.classificacao.destroy');
    });

    Route::delete('evidencias-prontuario/{evidence}', [ProntuarioController::class, 'destroyEvidence'])
        ->name('evidencia.destroy-prontuario');
    Route::delete('evidencias-checklist/{evidence}', [ChecklistController::class, 'destroyEvidence'])
        ->name('evidencia.destroy-checklist');

    // ---- Clientes (super admin) ----
    Route::get('tenants/cnpj/{cnpj}', [TenantController::class, 'lookupCnpj'])->name('tenants.cnpj-lookup');
    Route::get('tenants/cep/{cep}', [TenantController::class, 'lookupCep'])->name('tenants.cep-lookup');
    Route::get('tenants/cidades', [TenantController::class, 'cidades'])->name('tenants.cidades');
    Route::get('tenants/bairros', [TenantController::class, 'bairros'])->name('tenants.bairros');
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

use Illuminate\Support\Facades\Artisan;

// ROTA TEMPORÁRIA: Executar migrations e limpar cache
Route::get('/run-artisan-migrate-homolog', function () {
    try {
        // Executa as migrations no banco com --force
        Artisan::call('migrate', ['--force' => true]);
        $outputMigrate = Artisan::output();

        // Opcional: limpa caches do Laravel para garantir que reconheça as novidades
        Artisan::call('optimize:clear');
        $outputOptimize = Artisan::output();

        return '<h1>✅ Migrations executadas com sucesso!</h1><pre>' 
            . $outputMigrate . "\n" . $outputOptimize 
            . '</pre>';
    } catch (\Exception $e) {
        return '<h1>❌ Erro ao executar migrations:</h1><pre>' . $e->getMessage() . '</pre>';
    }
});
