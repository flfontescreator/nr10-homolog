<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Source;
use App\Models\CatalogItem;
use App\Models\Evidence;
use App\Models\Funcionario;
use App\Models\FuncionarioItem;
use App\Models\FuncionarioSituacao;
use App\Models\NcDocument;
use App\Models\Situacao;
use App\Models\Tenant;
use App\Models\TenantItem;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FuncionarioModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_create_funcionario(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Viewer, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), ['nome' => 'Sem permissão'])
            ->assertForbidden();

        $this->assertDatabaseCount('funcionarios', 0);
    }

    public function test_matricula_unique_per_tenant(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $otherTenant = Tenant::create(['name' => 'Cliente B']);
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'A', 'matricula' => 'X1']);
        Funcionario::create(['tenant_id' => $otherTenant->id, 'nome' => 'B', 'matricula' => 'X1']);

        // Mesma matricula em outro tenant é aceita.
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), ['nome' => 'Duplicado mesmo tenant', 'matricula' => 'X1'])
            ->assertSessionHasErrors('matricula');
    }

    public function test_novo_funcionario_nao_cria_itens_automaticamente(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), ['nome' => 'João da Silva', 'matricula' => 'F100'])
            ->assertRedirect();

        $funcionario = Funcionario::where('tenant_id', $tenant->id)->firstOrFail();

        // Os itens são criados sob demanda, pelo usuário — nada é bootstrapeado.
        $this->assertSame(0, $funcionario->items()->count());
    }

    public function test_itens_sao_numerados_sequencialmente_por_funcionario(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $f1 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F1', 'matricula' => 'A1']);
        $f2 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F2', 'matricula' => 'A2']);

        foreach (['Atestado', 'Crachá', 'Curso'] as $titulo) {
            $this->actingAs($user)
                ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
                ->post(route('funcionarios.items.store', $f1), ['titulo' => $titulo])
                ->assertSessionHas('success');
        }

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.items.store', $f2), ['titulo' => 'Atestado'])
            ->assertSessionHas('success');

        // A numeração reinicia em cada funcionário e nunca é reusada.
        $this->assertSame([1, 2, 3], $f1->items()->pluck('numero')->all());
        $this->assertSame([1], $f2->items()->pluck('numero')->all());
    }

    public function test_item_recebe_situacao_padrao_nao_avaliado(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.items.store', $funcionario), ['titulo' => 'Atestado'])
            ->assertSessionHas('success');

        $item = $funcionario->items()->firstOrFail();

        $this->assertSame('Não avaliado', $item->situacao?->nome);
        $this->assertTrue(Situacao::query()->where('is_default', true)->exists());
    }

    public function test_itens_aceitam_evidencias_independentes(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $f1 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F1', 'matricula' => 'A1']);
        $f2 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F2', 'matricula' => 'A2']);

        $itemF1 = $this->makeItem($f1, 'Atestado F1');
        $itemF2 = $this->makeItem($f2, 'Atestado F2');

        Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.item.evidencia.upload', [$f1, $itemF1]), [
                'evidence' => UploadedFile::fake()->image('docs.png'),
                'description' => 'Atestado de F1',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, $itemF1->evidences()->count());
        $this->assertSame(0, $itemF2->evidences()->count());
    }

    public function test_evidencia_do_funcionario_exige_descricao(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);
        $item = $this->makeItem($funcionario, 'Atestado');

        Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.item.evidencia.upload', [$funcionario, $item]), [
                'evidence' => UploadedFile::fake()->image('sem-descricao.png'),
            ])
            ->assertSessionHasErrors('description');

        $this->assertSame(0, $item->evidences()->count());
    }

    public function test_validade_da_evidencia_segue_o_checkbox_se_aplica(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);
        $item = $this->makeItem($funcionario, 'Atestado');

        // O box de upload nasce com "Se aplica" desmarcado → sem data.
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('funcionarios.item.show', [$funcionario, $item]))
            ->assertOk()
            ->assertSee('Se aplica')
            ->assertSee('name="validade_aplica"', false)
            ->assertSee('name="validade"', false);

        // "Se aplica" marcado exige data.
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.item.evidencia.upload', [$funcionario, $item]), [
                'evidence' => UploadedFile::fake()->image('cracha.png'),
                'description' => 'Crachá',
                'validade_aplica' => '1',
            ])
            ->assertSessionHasErrors('evidence-validade');

        $this->assertSame(0, $item->evidences()->count());

        $validade = now()->addYear()->format('Y-m-d');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.item.evidencia.upload', [$funcionario, $item]), [
                'evidence' => UploadedFile::fake()->image('cracha.png'),
                'description' => 'Crachá',
                'validade_aplica' => '1',
                'validade' => $validade,
            ])
            ->assertSessionHas('success');

        $evidence = $item->evidences()->latest('id')->firstOrFail();
        $this->assertSame($validade, $evidence->validade->format('Y-m-d'));

        // Desmarcado, a data é ignorada e a evidência fica sem validade.
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.item.evidencia.upload', [$funcionario, $item]), [
                'evidence' => UploadedFile::fake()->image('atestado.png'),
                'description' => 'Atestado',
                'validade' => $validade,
            ])
            ->assertSessionHas('success');

        $this->assertNull($item->evidences()->latest('id')->firstOrFail()->validade);
    }

    public function test_item_guarda_prazos_e_comentario_sem_percentual(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);
        $item = $this->makeItem($funcionario, 'Atestado');
        $situacao = Situacao::query()->where('nome', 'Não conforme')->firstOrFail();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->put(route('funcionarios.item.update', [$funcionario, $item]), [
                'titulo' => 'Atestado',
                'descricao' => 'Documento exigido pela norma',
                'situacao_id' => $situacao->id,
                'prazo_adequacao' => '2026-12-31',
                'data_adequacao' => '2026-11-15',
                'data_verificacao' => '2026-10-20',
                'comentario' => 'Renovação em andamento',
            ])
            ->assertSessionHas('success');

        $item->refresh();
        $this->assertSame('Não conforme', $item->situacao->nome);
        $this->assertSame('2026-12-31', $item->prazo_adequacao->format('Y-m-d'));
        $this->assertSame('2026-11-15', $item->data_adequacao->format('Y-m-d'));
        $this->assertSame('2026-10-20', $item->data_verificacao->format('Y-m-d'));
        $this->assertSame('Renovação em andamento', $item->comentario);

        // A tela do item não expõe percentual.
        $html = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('funcionarios.item.show', [$funcionario, $item]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Percentual', $html);
    }

    public function test_viewer_nao_altera_item_nem_anexa_evidencia(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $viewer = User::factory()->create(['role' => Role::Viewer, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);
        $item = $this->makeItem($funcionario, 'Atestado');

        $this->actingAs($viewer)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->put(route('funcionarios.item.update', [$funcionario, $item]), ['titulo' => 'Outro'])
            ->assertForbidden();

        Storage::fake('local');

        $this->actingAs($viewer)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.item.evidencia.upload', [$funcionario, $item]), [
                'evidence' => UploadedFile::fake()->image('x.png'),
                'description' => 'X',
            ])
            ->assertForbidden();

        $this->assertSame('Atestado', $item->fresh()->titulo);
        $this->assertSame(0, $item->evidences()->count());
    }

    public function test_item_de_outro_funcionario_responde_404(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $f1 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F1', 'matricula' => 'A1']);
        $f2 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F2', 'matricula' => 'A2']);
        $item = $this->makeItem($f1, 'Atestado');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('funcionarios.item.show', [$f2, $item]))
            ->assertNotFound();
    }

    public function test_item_com_evidencia_pode_ser_excluido_e_evidencia_permanece(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);
        $item = $this->makeItem($funcionario, 'Atestado');

        Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.item.evidencia.upload', [$funcionario, $item]), [
                'evidence' => UploadedFile::fake()->image('docs.png'),
                'description' => 'Atestado',
            ])
            ->assertSessionHas('success');

        $evidence = Evidence::query()->firstOrFail();
        $path = $evidence->stored_path;

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('funcionarios.items.destroy', [$funcionario, $item]))
            ->assertSessionHas('success');

        // O item some, mas a evidência (linha + arquivo) permanece desvinculada.
        $this->assertNull($item->fresh());
        $this->assertNotNull($evidence->fresh());
        $this->assertNull($evidence->fresh()->funcionario_item_id);
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_item_vazio_pode_ser_excluido(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);
        $item = $this->makeItem($funcionario, 'Atestado');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('funcionarios.items.destroy', [$funcionario, $item]))
            ->assertSessionHas('success');

        $this->assertNull($item->fresh());
        $this->assertSame(0, $funcionario->items()->count());
    }

    public function test_gestor_exclui_funcionario_em_producao_e_evidencias_permanecem(): void
    {
        Storage::fake('local');

        $tenant = $this->makeTenantWithProntuario();
        $gestor = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);
        $item = $this->makeItem($funcionario, 'Atestado');

        $this->actingAs($gestor)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.item.evidencia.upload', [$funcionario, $item]), [
                'evidence' => UploadedFile::fake()->image('docs.png'),
                'description' => 'Atestado',
            ])
            ->assertSessionHas('success');

        $evidence = Evidence::query()->firstOrFail();
        $path = $evidence->stored_path;

        // A exclusão definitiva funciona igual em produção (não é mais restrita
        // a local/testing) e está liberada para o Gestor.
        $this->app['env'] = 'production';
        $this->withoutMiddleware(PreventRequestForgery::class);

        $this->actingAs($gestor)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('funcionarios.destroy', $funcionario))
            ->assertRedirect(route('funcionarios.index'));

        $this->assertNull($funcionario->fresh());
        $this->assertNull($item->fresh());

        // As evidências continuam na Gestão de Documentos (linha + arquivo).
        $this->assertNotNull($evidence->fresh());
        $this->assertNull($evidence->fresh()->funcionario_item_id);
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_prontuario_nao_exibe_itens_de_funcionario(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Joana', 'matricula' => 'J1']);
        $this->makeItem($funcionario, 'Atestado de Joana');

        $html = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('prontuario.index'))
            ->assertOk()
            ->getContent();

        // A documentação do funcionário vive no módulo próprio.
        $this->assertStringContainsString('Funcionários', $html);
        $this->assertStringNotContainsString('Atestado de Joana', $html);

        // Só o catálogo gera linhas de trabalho; o item do funcionário não.
        $this->assertSame(1, FuncionarioItem::query()->count());
        $this->assertSame(3, TenantItem::query()->count());
    }

    public function test_documento_seleciona_item_de_funcionario_e_mostra_badge(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Maria', 'matricula' => 'B1']);
        $item = $this->makeItem($funcionario, 'Atestado de Maria');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['funcionario_item_ids' => [$item->id]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();
        $entry = $document->items()->firstOrFail();

        // O vínculo aponta para o item do módulo de funcionário.
        $this->assertSame($item->id, $entry->funcionario_item_id);
        $this->assertNull($entry->tenant_item_id);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('nc-documents.show', $document))
            ->assertOk()
            ->assertSee('Func: Maria');
    }

    public function test_documento_inclui_itens_de_dois_funcionarios(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $f1 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Marta', 'matricula' => 'E1']);
        $f2 = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Carla', 'matricula' => 'E2']);

        $itemA = $this->makeItem($f1, 'Atestado');
        $itemB = $this->makeItem($f2, 'Atestado');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['funcionario_item_ids' => [$itemA->id, $itemB->id]])
            ->assertRedirect();

        $document = NcDocument::withoutGlobalScopes()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame(2, $document->items()->count());
        $this->assertSame(
            2,
            $document->items()->whereIn('funcionario_item_id', [$itemA->id, $itemB->id])->count()
        );
    }

    public function test_documento_recusa_item_de_funcionario_de_outro_tenant(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $otherTenant = Tenant::create(['name' => 'Outro']);
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $outroFuncionario = Funcionario::create(['tenant_id' => $otherTenant->id, 'nome' => 'Externo']);
        $itemExterno = FuncionarioItem::create([
            'tenant_id' => $otherTenant->id,
            'funcionario_id' => $outroFuncionario->id,
            'numero' => 1,
            'titulo' => 'Atestado externo',
        ]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['funcionario_item_ids' => [$itemExterno->id]])
            ->assertSessionHasErrors('funcionario_item_ids.0');
    }

    public function test_evidencia_do_funcionario_aparece_em_gestao_de_documentos_com_rastreabilidade(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'Rast', 'matricula' => 'T1']);
        $item = $this->makeItem($funcionario, 'Atestado');

        Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.item.evidencia.upload', [$funcionario, $item]), [
                'evidence' => UploadedFile::fake()->image('docs.png'),
                'description' => 'Atestado',
            ])
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('nc-documents.store'), ['funcionario_item_ids' => [$item->id]])
            ->assertRedirect();

        $html = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->getContent();

        // A tela exibe o nome GERADO pelo sistema, não o enviado pelo usuário.
        $this->assertMatchesRegularExpression('/img_fun_\d{8}_\d{9}\.png/', $html);
        $this->assertStringNotContainsString('docs.png', $html);
        $this->assertStringContainsString('Rast', $html);
        $this->assertStringContainsString('Funcionários', $html);
        $this->assertStringContainsString('RNC-00001', $html);
    }

    public function test_funcionario_cpf_is_stored_without_mask_and_displayed_masked(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), [
                'nome' => 'Com Cpf',
                'cpf' => '529.982.247-25',
            ])
            ->assertRedirect();

        $funcionario = Funcionario::where('tenant_id', $tenant->id)->firstOrFail();

        // Gravado só com dígitos; a máscara é só de exibição.
        $this->assertSame('52998224725', $funcionario->cpf);

        $html = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('funcionarios.show', $funcionario))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('529.982.247-25', $html);
    }

    public function test_funcionario_cpf_rejects_invalid_check_digits(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), [
                'nome' => 'Cpf Inválido',
                'cpf' => '111.111.111-11',
            ])
            ->assertSessionHasErrors('cpf');

        $this->assertSame(0, Funcionario::where('tenant_id', $tenant->id)->count());
    }

    public function test_funcionario_cpf_is_unique_per_tenant(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $otherTenant = Tenant::create(['name' => 'Cliente Cpf']);
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'A', 'cpf' => '52998224725']);
        Funcionario::create(['tenant_id' => $otherTenant->id, 'nome' => 'B', 'cpf' => '52998224725']);

        // Mesmo CPF em outro tenant é aceito; duplicado no mesmo tenant, não.
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), ['nome' => 'Duplicado', 'cpf' => '529.982.247-25'])
            ->assertSessionHasErrors('cpf');
    }

    public function test_funcionario_can_be_created_without_cpf_and_cpf_can_be_cleared(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Sem Cpf',
            'cpf' => '52998224725',
        ]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->put(route('funcionarios.update', $funcionario), ['nome' => 'Sem Cpf', 'cpf' => ''])
            ->assertRedirect();

        $this->assertNull($funcionario->fresh()->cpf);
    }

    public function test_funcionario_guarda_admissao_e_situacao_default_ativo(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), [
                'nome' => 'Com Admissão',
                'data_admissao' => '2024-03-15',
            ])
            ->assertRedirect();

        $funcionario = Funcionario::where('tenant_id', $tenant->id)->firstOrFail();
        $ativo = FuncionarioSituacao::query()->where('slug', FuncionarioSituacao::ATIVO)->firstOrFail();

        $this->assertSame('2024-03-15', $funcionario->data_admissao->format('Y-m-d'));
        $this->assertSame($ativo->id, $funcionario->situacao_id);
    }

    public function test_vinculo_inativo_gravado_no_cadastro_e_exibido_em_dd_mm_aaaa(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $inativo = FuncionarioSituacao::query()->where('slug', FuncionarioSituacao::INATIVO)->firstOrFail();

        // A Situação não é escolhida na tela, mas o valor cadastral continua
        // sendo exibido.
        $funcionario = Funcionario::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Vínculo Inativo',
            'data_admissao' => '2020-01-02',
            'situacao_id' => $inativo->id,
        ]);

        $this->assertSame($inativo->id, $funcionario->fresh()->situacao_id);

        $html = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('funcionarios.show', $funcionario))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('02/01/2020', $html);
        $this->assertMatchesRegularExpression('/badge badge-neutral"[^>]*>\s*Inativo/u', $html);
    }

    public function test_funcionario_sem_admissao_e_sem_situacao_exibe_travessao(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Sem Dados Cadastrais',
        ]);

        $this->assertNull($funcionario->data_admissao);
        $this->assertNull($funcionario->situacao_id);

        $html = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('funcionarios.show', $funcionario))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/Data de admissão:<\/strong>\s*-\s*<\/span>/u', $html);
        $this->assertMatchesRegularExpression('/Situação:<\/strong>\s*-\s*<\/span>/u', $html);
    }

    public function test_tela_de_criacao_esconde_situacao_e_store_grava_ativo_por_padrao(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $ativo = FuncionarioSituacao::query()->where('slug', FuncionarioSituacao::ATIVO)->firstOrFail();

        $html = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('funcionarios.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('name="situacao_id"', $html);

        // Mesmo enviando um valor inválido, o cadastro ignora e grava Ativo.
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.store'), [
                'nome' => 'Novo Funcionário',
                'situacao_id' => 99999,
            ])
            ->assertRedirect();

        $funcionario = Funcionario::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertSame($ativo->id, $funcionario->situacao_id);
    }

    public function test_documentos_grid_shows_description_and_validity_with_correct_badges(): void
    {
        Storage::fake('local');

        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $item = TenantItem::query()->where('tenant_id', $tenant->id)->firstOrFail();

        // Evidência com validade próxima (banner vermelho) e descrição preenchida.
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $item), [
                'evidence' => UploadedFile::fake()->image('atestado.png'),
                'description' => 'Atestado de Aptidão Física',
                'validade_aplica' => '1',
                'validade' => now()->addDays(10)->format('Y-m-d'),
            ])
            ->assertSessionHas('success');

        // Evidência com validade distante e sem descrição.
        $item2 = TenantItem::query()->where('tenant_id', $tenant->id)->skip(1)->firstOrFail();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $item2), [
                'evidence' => UploadedFile::fake()->image('cracha.png'),
                'validade_aplica' => '1',
                'validade' => now()->addDays(180)->format('Y-m-d'),
            ])
            ->assertSessionHas('success');

        $html = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->getContent();

        // Cabeçalho final: sem "Enviado por", "Tamanho" nem "Ação".
        $this->assertStringContainsString('>Documento<', $html);
        $this->assertStringContainsString('>Enviado<', $html);
        $this->assertStringContainsString('>Situação<', $html);
        $this->assertStringContainsString('>Validade<', $html);
        $this->assertStringNotContainsString('Enviado por', $html);
        $this->assertStringNotContainsString('>Tamanho<', $html);
        $this->assertStringNotContainsString('>Ação<', $html);

        // Descrição do arquivo aparece na coluna Arquivo.
        $this->assertStringContainsString('Atestado de Aptidão Física', $html);

        // Validade <= 30 dias em vermelho; > 30 dias no azul alternativo.
        $this->assertMatchesRegularExpression(
            '/badge-red[^"]*">\s*'.preg_quote(now()->addDays(10)->format('d/m/Y'), '/').'/',
            preg_replace('/\s+/', ' ', $html)
        );
        $this->assertMatchesRegularExpression(
            '/badge-blue-alt[^"]*">\s*'.preg_quote(now()->addDays(180)->format('d/m/Y'), '/').'/',
            preg_replace('/\s+/', ' ', $html)
        );
    }

    public function test_documentos_grid_exposes_provisional_delete_for_admin(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);

        $item = TenantItem::query()->where('tenant_id', $tenant->id)->firstOrFail();

        Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $item), [
                'evidence' => UploadedFile::fake()->image('laudo.png'),
            ])
            ->assertSessionHas('success');

        $evidence = Evidence::query()->firstOrFail();

        $html = $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->getContent();

        // Exclusão liberada para Admin (e demais perfis de escrita), no ar.
        $this->assertStringContainsString(route('documentos.destroy', $evidence), $html);
        $this->assertStringContainsString('_method', $html);
        $this->assertStringContainsString('Excluir', $html);
        // O download continua disponível pelo link do nome gerado.
        $this->assertMatchesRegularExpression('/img_prt_\d{8}_\d{9}\.png/', $html);
        $this->assertStringNotContainsString('laudo.png', $html);
    }

    public function test_documentos_grid_hides_delete_for_viewer(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $viewer = User::factory()->create(['role' => Role::Viewer, 'tenant_id' => $tenant->id]);

        $item = TenantItem::query()->where('tenant_id', $tenant->id)->firstOrFail();

        Storage::fake('local');

        $this->actingAs(User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]))
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $item), [
                'evidence' => UploadedFile::fake()->image('laudo.png'),
            ])
            ->assertSessionHas('success');

        $evidence = Evidence::query()->firstOrFail();

        // Viewer não pode excluir: nem o botão aparece. (Compara os controles,
        // não a URL — `documentos/1` é prefixo de `documentos/1/download`.)
        $html = $this->actingAs($viewer)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('documentos.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('_method', $html);
        $this->assertStringNotContainsString('Excluir', $html);

        // E o endpoint responde 403, mesmo com a rota direta.
        $this->actingAs($viewer)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('documentos.destroy', $evidence))
            ->assertForbidden();

        $this->assertDatabaseHas('evidences', ['id' => $evidence->id]);
    }

    public function test_provisional_delete_removes_the_evidence_and_its_file(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $item = TenantItem::query()->where('tenant_id', $tenant->id)->firstOrFail();

        Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $item), [
                'evidence' => UploadedFile::fake()->image('laudo.png'),
            ])
            ->assertSessionHas('success');

        $evidence = Evidence::query()->firstOrFail();
        $path = $evidence->stored_path;

        $this->assertTrue(Storage::disk('local')->exists($path));

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->from(route('documentos.index'))
            ->delete(route('documentos.destroy', $evidence))
            ->assertRedirect(route('documentos.index'))
            ->assertSessionHas('success');

        // Registro e arquivo físico somem juntos — não deixa lixo no storage.
        $this->assertDatabaseMissing('evidences', ['id' => $evidence->id]);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    public function test_provisional_delete_funciona_em_producao_para_gestor(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $item = TenantItem::query()->where('tenant_id', $tenant->id)->firstOrFail();

        Storage::fake('local');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $item), [
                'evidence' => UploadedFile::fake()->image('laudo.png'),
            ])
            ->assertSessionHas('success');

        $evidence = Evidence::query()->firstOrFail();

        // Simula produção: a exclusão continua permitida (não há mais gate de
        // ambiente). O CSRF é desligado porque `runningUnitTests()` deixa de
        // valer assim que o env muda.
        $this->app->detectEnvironment(fn () => 'production');

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('documentos.destroy', $evidence))
            ->assertRedirect();

        $this->assertDatabaseMissing('evidences', ['id' => $evidence->id]);
    }

    public function test_index_mostra_todos_e_badge_de_situacao(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $admin = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);
        $ativo = FuncionarioSituacao::query()->where('slug', FuncionarioSituacao::ATIVO)->firstOrFail();
        $inativo = FuncionarioSituacao::query()->where('slug', FuncionarioSituacao::INATIVO)->firstOrFail();

        $ativoFuncionario = Funcionario::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Ana Ativa',
            'matricula' => 'A1',
            'situacao_id' => $ativo->id,
        ]);
        $inativoFuncionario = Funcionario::create([
            'tenant_id' => $tenant->id,
            'nome' => 'Bruno Inativo',
            'matricula' => 'I1',
            'situacao_id' => $inativo->id,
        ]);

        // Sem filtro: a listagem mostra todos e o badge vem do cadastro.
        $html = $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('funcionarios.index'))
            ->assertOk()
            ->assertSee($ativoFuncionario->nome)
            ->assertSee($inativoFuncionario->nome)
            ->getContent();

        $this->assertMatchesRegularExpression('/badge badge-blue"[^>]*>\s*Ativo/u', $html);
        $this->assertMatchesRegularExpression('/badge badge-neutral"[^>]*>\s*Inativo/u', $html);
    }

    public function test_nomenclatura_do_arquivo_anexado_segue_modulo_tipo_e_data(): void
    {
        Storage::fake('local');

        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $item = TenantItem::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $hoje = now()->format('dmY');

        // Prontuário: imagem → img_prt_ddmmaaaa_000000001
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $item), [
                'evidence' => UploadedFile::fake()->image('cracha.png'),
            ])
            ->assertSessionHas('success');

        $this->assertSame(
            "evidences/tenant-{$tenant->id}/img_prt_{$hoje}_000000001.png",
            Evidence::query()->latest('id')->firstOrFail()->stored_path
        );

        // PDF → doc_prt_ddmmaaaa_000000001: sequência independente da de imagens.
        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $item), [
                'evidence' => UploadedFile::fake()->create('atestado.pdf', 20, 'application/pdf'),
            ])
            ->assertSessionHas('success');

        $this->assertSame(
            "evidences/tenant-{$tenant->id}/doc_prt_{$hoje}_000000001.pdf",
            Evidence::query()->latest('id')->firstOrFail()->stored_path
        );

        // Funcionário: a sequência de imagens é GLOBAL, então segue a do
        // Prontuário (000000002) em vez de recomeçar em 000000001.
        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);
        $funcionarioItem = $this->makeItem($funcionario, 'Atestado');

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('funcionarios.item.evidencia.upload', [$funcionario, $funcionarioItem]), [
                'evidence' => UploadedFile::fake()->image('cracha.png'),
                'description' => 'Crachá',
            ])
            ->assertSessionHas('success');

        $this->assertSame(
            "evidences/tenant-{$tenant->id}/img_fun_{$hoje}_000000002.png",
            Evidence::query()->latest('id')->firstOrFail()->stored_path
        );
    }

    public function test_nomenclatura_descarta_o_nome_enviado_pelo_usuario(): void
    {
        Storage::fake('local');

        $tenant = $this->makeTenantWithProntuario();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $item = TenantItem::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->actingAs($user)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->post(route('prontuario.evidencia.upload', $item), [
                'evidence' => UploadedFile::fake()->image('minha foto de jackson violation.jpg'),
            ])
            ->assertSessionHas('success');

        $evidence = Evidence::query()->latest('id')->firstOrFail();

        // O nome gerado é o que fica salvo e o que a tela exibe.
        $this->assertSame(basename($evidence->stored_path), $evidence->original_name);
        $this->assertStringNotContainsString('jackson', $evidence->original_name);
    }

    public function test_exclusao_definitiva_funciona_em_producao_e_nc_document_segue_restrito(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $admin = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);

        $document = NcDocument::create([
            'tenant_id' => $tenant->id,
            'number' => NcDocument::nextNumber($tenant->id),
            'code' => NcDocument::makeCode(NcDocument::nextNumber($tenant->id)),
            'title' => 'Documento temporário',
        ]);

        $this->app['env'] = 'production';
        $this->withoutMiddleware(PreventRequestForgery::class);

        // A exclusão de funcionário continua disponível em produção...
        $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('funcionarios.destroy', $funcionario))
            ->assertRedirect(route('funcionarios.index'));

        // ...mas o hard delete de documento NC continua restrito a local/testing.
        $this->actingAs($admin)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->delete(route('nc-documents.destroy', $document))
            ->assertNotFound();

        $this->assertNull($funcionario->fresh());
        $this->assertNotNull($document->fresh());
    }

    public function test_index_mostra_excluir_para_escrita_e_esconde_do_viewer(): void
    {
        $tenant = $this->makeTenantWithProntuario();
        $manager = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $viewer = User::factory()->create(['role' => Role::Viewer, 'tenant_id' => $tenant->id]);

        $funcionario = Funcionario::create(['tenant_id' => $tenant->id, 'nome' => 'F', 'matricula' => 'A1']);

        $managerHtml = $this->actingAs($manager)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('funcionarios.index'))
            ->assertOk()
            ->getContent();

        // A URL do show e a do destroy coincidem; o marcador confiável do botão
        // é o title do submit.
        $this->assertStringContainsString('title="Exclusão definitiva"', $managerHtml);
        $this->assertStringContainsString(route('funcionarios.destroy', $funcionario), $managerHtml);

        $viewerHtml = $this->actingAs($viewer)
            ->withSession(['tenant_id' => $tenant->id, 'two_step_verified' => true])
            ->get(route('funcionarios.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('title="Exclusão definitiva"', $viewerHtml);
    }

    protected function makeItem(Funcionario $funcionario, string $titulo): FuncionarioItem
    {
        return FuncionarioItem::create([
            'tenant_id' => $funcionario->tenant_id,
            'funcionario_id' => $funcionario->id,
            'numero' => $funcionario->nextItemNumber(),
            'titulo' => $titulo,
            'situacao_id' => Situacao::default()?->id,
        ]);
    }

    protected function makeTenantWithProntuario(): Tenant
    {
        $tenant = Tenant::create(['name' => 'Cliente Funcionarios']);

        foreach (['1', '2', '3'] as $section) {
            CatalogItem::create([
                'source' => Source::Prontuario->value,
                'code' => $section,
                'n1' => (int) $section,
                'n2' => 0,
                'n3' => 0,
                'n4' => 0,
                'is_section' => true,
                'title' => 'Item '.$section,
                'description' => 'Item '.$section,
            ]);
        }

        foreach (['1.1', '2.1', '3.1'] as $code) {
            $parts = explode('.', $code);
            CatalogItem::create([
                'source' => Source::Prontuario->value,
                'code' => $code,
                'n1' => (int) $parts[0],
                'n2' => (int) $parts[1],
                'n3' => 0,
                'n4' => 0,
                'is_section' => false,
                'title' => 'Item '.$code,
                'description' => 'Item '.$code,
            ]);
        }

        $tenant->bootstrapItems();

        return $tenant;
    }
}
