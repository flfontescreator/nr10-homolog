<?php

namespace Tests\Feature;

use App\Enums\RncModelo;
use App\Enums\RncStatus;
use App\Enums\Role;
use App\Mail\RncMail;
use App\Models\ClassificacaoRisco;
use App\Models\Evidence;
use App\Models\NormaTecnica;
use App\Models\Rnc;
use App\Models\RncItem;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Rnc\RncPdfRenderer;
use App\Support\Rnc\RncPublicationService;
use Database\Seeders\RncCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Módulo RNC �?" relatório formal de não conformidade, NOVO e independente de
 * "Não Conformidades" (`NcDocument`). Nada aqui toca `nc_documents`.
 */
class RncModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_nao_acessa_o_modulo(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Viewer, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.index'))
            ->assertForbidden();
    }

    public function test_formulario_de_criacao_mostra_o_proximo_codigo(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.create'))
            ->assertOk()
            ->assertSee('RNC_0001');
    }

    public function test_cria_rascunho_com_numeracao_sequencial_por_cliente(): void
    {
        $tenant = $this->makeTenant();
        $outro = Tenant::create(['name' => 'Cliente B']);
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.store'), ['titulo' => 'Inspeção mensal', 'modelo' => RncModelo::Tecnica->value])
            ->assertRedirect();

        // A numeração reinicia em 1 para cada cliente.
        $this->actingAs($user)->withSession($this->sessionData($outro))
            ->post(route('rnc.store'), ['titulo' => 'Outro cliente', 'modelo' => RncModelo::Tecnica->value])
            ->assertRedirect();

        $this->assertSame('RNC_0001', Rnc::withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('code'));
        $this->assertSame('RNC_0001', Rnc::withoutGlobalScopes()->where('tenant_id', $outro->id)->value('code'));
        $this->assertSame(RncStatus::Rascunho, Rnc::withoutGlobalScopes()->where('tenant_id', $tenant->id)->value('status'));
    }

    public function test_rnc_de_outro_cliente_responde_404(): void
    {
        $tenant = $this->makeTenant();
        $outro = Tenant::create(['name' => 'Cliente B']);
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $rnc = $this->makeRnc($outro);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.show', $rnc))
            ->assertNotFound();
    }

    public function test_publicar_exige_uma_nao_conformidade(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->from(route('rnc.show', $rnc))
            ->post(route('rnc.publish', $rnc))
            ->assertStatus(422);

        $this->assertSame(0, $rnc->revisions()->count());
    }

    public function test_publicar_cria_revisao_com_markdown_pdf_e_link_publico(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'Ausência de proteção termométrica');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.publish', $rnc))
            ->assertRedirect();

        $revision = $rnc->revisions()->firstOrFail();

        $this->assertSame(1, $revision->revision);
        $this->assertSame('Rev:0001', $revision->label);
        $this->assertStringContainsString('# '.$rnc->titulo, $revision->markdown);
        $this->assertStringContainsString('Ausência de proteção termométrica', $revision->markdown);
        $this->assertNotNull($revision->pdf_path);
        Storage::disk('local')->assertExists($revision->pdf_path);
        $this->assertNotNull($revision->public_token);
        $this->assertTrue($revision->public_expires_at->isFuture());

        $rnc->refresh();
        $this->assertSame(RncStatus::Publicado, $rnc->status);
        $this->assertSame(1, $rnc->current_revision);
    }

    public function test_publicacao_sobe_sem_dompdf_e_preserva_pdf_na_reemissao(): void
    {
        $this->app->instance(RncPdfRenderer::class, new class extends RncPdfRenderer
        {
            public function available(): bool
            {
                return false;
            }
        });

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'Ausência de proteção termométrica');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.publish', $rnc))
            ->assertRedirect();

        $revision = $rnc->revisions()->firstOrFail();

        $this->assertSame(1, $revision->revision);
        $this->assertNull($revision->pdf_path, 'Sem dompdf a revisão nasce com markdown + link, mas sem PDF em disco.');
        $this->assertStringContainsString('# '.$rnc->titulo, $revision->markdown);
        $this->assertNotNull($revision->public_token);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.revision.pdf', [$rnc, $revision]))
            ->assertRedirect(route('rnc.revision.print', [$rnc, $revision]));

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.republish', $rnc))
            ->assertRedirect();

        $this->assertSame(1, $rnc->revisions()->count());
        $this->assertNull($rnc->revisions()->firstOrFail()->pdf_path);
    }

    public function test_campo_inspecao_tecnica_saiu_do_formulario_e_do_relatorio(): void
    {
        Storage::fake('local');

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');
        $rnc->forceFill(['introducao' => 'Texto antigo guardado no banco'])->save();

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.edit', $rnc))
            ->assertOk()
            ->assertDontSee('name="introducao"');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->put(route('rnc.update', $rnc), [
                'titulo' => 'Quadro QGBT sem aterramento',
                'modelo' => RncModelo::Tecnica->value,
                'introducao' => 'Texto enviado e ignorado',
            ])
            ->assertRedirect(route('rnc.show', $rnc));

        $rnc->refresh();
        $this->assertSame('Texto antigo guardado no banco', $rnc->introducao, 'O banco preserva o valor; o campo só saiu do uso.');
        $this->assertArrayNotHasKey('introducao', $rnc->buildSnapshot());

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));

        $revisao = $rnc->revisions()->firstOrFail();
        $this->assertStringNotContainsString('## Inspeção Técnica', $revisao->markdown);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.revision.print', [$rnc, $revisao]))
            ->assertOk()
            ->assertDontSee('Inspeção Técnica');
    }

    public function test_atualizar_publicacao_reemite_a_mesma_revisao_e_preserva_o_link(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));

        $revisao = $rnc->revisions()->firstOrFail();
        $token = $revisao->public_token;

        // Editar depois da publicação é livre — não trava nada.
        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->put(route('rnc.update', $rnc), [
                'titulo' => 'Título alterado após publicar',
                'modelo' => RncModelo::Tecnica->value,
            ])
            ->assertRedirect();

        $this->assertTrue($this->publicationPendente($rnc));

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.republish', $rnc));

        // Nunca nasce Rev:0002: é a mesma revisão, com o link intacto.
        $this->assertSame(1, $rnc->revisions()->count());
        $this->assertSame(['Rev:0001'], $rnc->revisions()->pluck('label')->all());
        $this->assertSame(1, $rnc->fresh()->current_revision);

        $revisao->refresh();
        $this->assertSame($token, $revisao->public_token, 'O link público continua o mesmo.');
        $this->assertTrue($revisao->public_expires_at->isFuture());
        $this->assertStringContainsString('Título alterado após publicar', $revisao->markdown);
        $this->assertStringContainsString('Rev:0001', $revisao->markdown, 'A assinatura leva o rótulo da revisão.');
        $this->assertFalse($this->publicationPendente($rnc), 'Depois de atualizar não há pendência.');
    }

    public function test_publicar_nova_revisao_congela_a_anterior_com_link_proprio(): void
    {
        Storage::fake('local');
        Mail::fake();

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));
        $rev1 = $rnc->revisions()->firstOrFail();

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->put(route('rnc.update', $rnc), [
                'titulo' => 'Título da segunda fase',
                'modelo' => RncModelo::Tecnica->value,
            ]);

        // "Publicar nova revisão" é uma ação separada de "Atualizar publicação".
        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->from(route('rnc.show', $rnc))
            ->post(route('rnc.publish', $rnc))
            ->assertRedirect(route('rnc.show', $rnc));

        $this->assertSame(['Rev:0002', 'Rev:0001'], $rnc->revisions()->pluck('label')->all());
        $this->assertSame(2, $rnc->fresh()->current_revision);

        $rev1->refresh();
        $this->assertStringContainsString('Relatório de inspeção', $rev1->markdown, 'A anterior continua congelada.');
        $this->assertStringNotContainsString('Título da segunda fase', $rev1->markdown);
        $this->assertStringContainsString('Título da segunda fase', $rnc->revisions()->orderByDesc('revision')->first()->markdown);

        // Os dois links continuam valendo, cada um com o seu conteúdo.
        $this->get(route('rnc.public.show', $rev1->public_token))->assertOk();
        $this->get(route('rnc.public.show', $rnc->revisions()->orderByDesc('revision')->first()->public_token))
            ->assertOk();

        // A tela oferece as duas ações.
        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.show', $rnc))
            ->assertOk()
            ->assertSee(route('rnc.republish', $rnc), false)
            ->assertSee('Publicar nova revisão', false);
    }

    public function test_republish_sem_publicacao_responde_422(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.republish', $rnc))
            ->assertStatus(422);

        $this->assertSame(0, $rnc->revisions()->count());
    }

    public function test_link_publico_abre_sem_autenticacao(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));
        $revision = $rnc->revisions()->firstOrFail();

        // Sem login: o link público precisa responder.
        $this->get(route('rnc.public.show', $revision->public_token))
            ->assertOk()
            ->assertSee($rnc->code);
    }

    public function test_link_publico_expirado_responde_410(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));
        $revision = $rnc->revisions()->firstOrFail();

        $revision->forceFill(['public_expires_at' => now()->subDay()])->save();

        $this->get(route('rnc.public.show', $revision->public_token))->assertStatus(410);
    }

    public function test_renovar_link_prolonga_a_validade(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));
        $revision = $rnc->revisions()->firstOrFail();
        $tokenAnterior = $revision->public_token;

        $revision->forceFill(['public_expires_at' => now()->subDay()])->save();

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.revision.link.renew', [$rnc, $revision]))
            ->assertRedirect();

        $revision->refresh();
        $this->assertNotSame($tokenAnterior, $revision->public_token);
        $this->assertTrue($revision->public_expires_at->isFuture());
    }

    public function test_edicao_de_item_continua_livre_depois_da_publicacao(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $item = $this->makeItem($rnc, 'NC 1');
        $this->makeItem($rnc, 'NC 2');

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));

        $revisao = $rnc->revisions()->firstOrFail();
        $this->assertStringContainsString('NC 1', $revisao->markdown);

        // Remover NC publicada é liberado: o relatório só muda ao atualizar.
        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->delete(route('rnc.item.destroy', [$rnc, $item]))
            ->assertRedirect();

        $this->assertDatabaseMissing('rnc_items', ['id' => $item->id]);
        $this->assertStringContainsString('NC 1', $revisao->fresh()->markdown);

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.republish', $rnc));

        $this->assertSame(1, $rnc->revisions()->count());
        $this->assertStringNotContainsString('NC 1', $revisao->fresh()->markdown);
        $this->assertStringContainsString('NC 2', $revisao->fresh()->markdown);
    }

    public function test_item_pode_ser_removido_enquanto_e_rascunho(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $item = $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->delete(route('rnc.item.destroy', [$rnc, $item]))
            ->assertRedirect();

        $this->assertDatabaseMissing('rnc_items', ['id' => $item->id]);
    }

    public function test_item_de_outro_rnc_responde_404(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rncA = $this->makeRnc($tenant);
        $rncB = $this->makeRnc($tenant);
        $item = $this->makeItem($rncB, 'NC do B');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->delete(route('rnc.item.destroy', [$rncA, $item]))
            ->assertNotFound();
    }

    public function test_evidencia_ancora_no_item_do_rnc_e_usa_prefixo_rnc(): void
    {
        Storage::fake('local');

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $item = $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.item.evidencia.upload', [$rnc, $item]), [
                'evidence' => UploadedFile::fake()->image('foto.png'),
                'description' => 'Foto do painel',
            ])
            ->assertRedirect();

        $evidence = Evidence::withoutGlobalScopes()->where('rnc_item_id', $item->id)->firstOrFail();

        $this->assertNull($evidence->tenant_item_id);
        $this->assertNull($evidence->funcionario_item_id);
        $this->assertNull($evidence->validade, 'Sem marcar "Se aplica", a validade é nula.');
        $this->assertMatchesRegularExpression(
            '/^img_rnc_\d{8}_\d{9}\.png$/',
            basename($evidence->stored_path)
        );
    }

    public function test_envia_revisao_para_o_contato_do_cliente(): void
    {
        Mail::fake();

        $tenant = Tenant::create(['name' => 'Cliente RNC', 'contact_email' => 'cliente@exemplo.com']);
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));
        $revision = $rnc->revisions()->firstOrFail();

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.revision.send', [$rnc, $revision]))
            ->assertRedirect();

        Mail::assertSent(RncMail::class, fn (RncMail $mail) => $mail->hasTo('cliente@exemplo.com'));
    }

    public function test_envio_avisa_quando_o_cliente_nao_tem_email(): void
    {
        Mail::fake();

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));
        $revision = $rnc->revisions()->firstOrFail();

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->from(route('rnc.show', $rnc))
            ->post(route('rnc.revision.send', [$rnc, $revision]))
            ->assertRedirect()
            ->assertSessionHas('error');

        Mail::assertNothingSent();
    }

    public function test_markdown_e_pdf_ficam_disponiveis_para_revisao_do_cliente(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));
        $revision = $rnc->revisions()->firstOrFail();

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.revision.markdown', [$rnc, $revision]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.revision.pdf', [$rnc, $revision]))
            ->assertOk();
    }

    public function test_rnc_nao_toca_o_modulo_de_nao_conformidades(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))->post(route('rnc.publish', $rnc));

        $this->assertDatabaseCount('rncs', 1);
        $this->assertDatabaseCount('nc_documents', 0);
        $this->assertDatabaseCount('nc_document_items', 0);
    }

    public function test_item_aceita_classificacao_de_risco_e_adequacao(): void
    {
        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $classificacao = ClassificacaoRisco::create(['nome' => 'Alto', 'ordem' => 1]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.item.store', $rnc), [
                'titulo' => 'Sem EPI',
                'descricao' => 'Trabalhador sem capacete.',
                'classificacao_risco_id' => $classificacao->id,
                'recomendacao' => 'Fornecer e treinar.',
                'prazo_adequacao' => now()->addWeek()->toDateString(),
            ])
            ->assertRedirect();

        $item = RncItem::withoutGlobalScopes()->where('rnc_id', $rnc->id)->firstOrFail();

        $this->assertSame(1, $item->numero);
        $this->assertSame($classificacao->id, $item->classificacao_risco_id);
        $this->assertSame('Fornecer e treinar.', $item->recomendacao);
        $this->assertNotNull($item->prazo_adequacao);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->put(route('rnc.item.update', [$rnc, $item]), [
                'titulo' => 'Sem EPI',
                'data_adequacao' => now()->toDateString(),
            ])
            ->assertRedirect();

        $item->refresh();
        $this->assertNotNull($item->data_adequacao);
        $this->assertSame($classificacao->id, $item->classificacao_risco_id);
    }

    public function test_publicacao_fotografica_gera_pdf_com_registro_fotografico(): void
    {
        Storage::fake('local');

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);

        $number = Rnc::nextNumber($tenant->id);
        $rnc = Rnc::create([
            'tenant_id' => $tenant->id,
            'number' => $number,
            'code' => Rnc::makeCode($number),
            'titulo' => 'Relatório fotográfico',
            'modelo' => RncModelo::Fotografica,
            'resumo' => 'Resumo geral.',
            'conclusao' => 'Conclusão geral.',
            'status' => RncStatus::Rascunho,
            'current_revision' => 0,
        ]);

        $item = $this->makeItem($rnc, 'Quadro sem sinalização');
        $rnc->items()->create([
            'tenant_id' => $rnc->tenant_id,
            'numero' => $rnc->nextItemNumber(),
            'titulo' => 'Fiação exposta',
            'descricao' => 'Condutores sem isolação.',
        ]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.item.evidencia.upload', [$rnc, $item]), [
                'evidence' => UploadedFile::fake()->image('foto.png'),
                'description' => 'Foto do quadro',
            ])
            ->assertRedirect();

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.publish', $rnc))
            ->assertRedirect();

        $revision = $rnc->revisions()->firstOrFail();

        $this->assertSame('fotografica', $revision->snapshot['modelo']);
        $this->assertStringContainsString('Registro fotográfico', $revision->markdown);
        $this->assertNotNull($revision->pdf_path);
        Storage::disk('local')->assertExists($revision->pdf_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($revision->pdf_path));
    }

    // ------------------------------------------------------------------
    // Exclusão de evidência (botão PROVISÓRIO da Gestão de Documentos)
    // ------------------------------------------------------------------
    public function test_evidencia_de_rnc_rascunho_pode_ser_excluida(): void
    {
        Storage::fake('local');

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $item = $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.item.evidencia.upload', [$rnc, $item]), [
                'evidence' => UploadedFile::fake()->image('foto.png'),
            ])
            ->assertRedirect();

        $evidence = Evidence::withoutGlobalScopes()->where('rnc_item_id', $item->id)->firstOrFail();

        $this->assertFalse($evidence->linkedToPublishedRnc());

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->from(route('documentos.index'))
            ->delete(route('documentos.destroy', $evidence))
            ->assertRedirect(route('documentos.index'));

        $this->assertDatabaseMissing('evidences', ['id' => $evidence->id]);
    }

    public function test_botao_excluir_evidencia_da_nc_monta_a_url_correta_e_reabre_o_painel(): void
    {
        Storage::fake('local');

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $item = $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.item.evidencia.upload', [$rnc, $item]), [
                'evidence' => UploadedFile::fake()->image('foto.png'),
            ])
            ->assertRedirect()
            ->assertSessionHas('open_item_id', $item->id);

        $evidence = Evidence::withoutGlobalScopes()->where('rnc_item_id', $item->id)->firstOrFail();

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.show', $rnc))
            ->assertOk()
            ->assertSee(route('rnc.item.evidencia.destroy', [$rnc, $item, $evidence]), false);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->from(route('rnc.show', $rnc))
            ->delete(route('rnc.item.evidencia.destroy', [$rnc, $item, $evidence]))
            ->assertRedirect(route('rnc.show', $rnc))
            ->assertSessionHas('open_item_id', $item->id);

        $this->assertDatabaseMissing('evidences', ['id' => $evidence->id]);
    }

    public function test_evidencia_de_rnc_publicado_pode_ser_excluida(): void
    {
        Storage::fake('local');
        Mail::fake();

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $item = $this->makeItem($rnc, 'NC 1');

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.item.evidencia.upload', [$rnc, $item]), [
                'evidence' => UploadedFile::fake()->image('foto.png'),
            ])
            ->assertRedirect();

        // Publicar cria a Rev:0001, mas remover a evidência continua permitido.
        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.publish', $rnc));

        $evidence = Evidence::withoutGlobalScopes()->where('rnc_item_id', $item->id)->firstOrFail();
        $path = $evidence->stored_path;

        $this->assertTrue($evidence->linkedToPublishedRnc());

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->delete(route('documentos.destroy', $evidence))
            ->assertRedirect();

        $this->assertDatabaseMissing('evidences', ['id' => $evidence->id]);
        $this->assertFalse(Storage::disk('local')->exists($path));
    }

    // ------------------------------------------------------------------
    // Fuso horário (regra: tudo em America/Sao_Paulo, -03:00)
    // ------------------------------------------------------------------

    public function test_nome_do_arquivo_usa_a_data_de_sao_paulo_e_nao_a_de_utc(): void
    {
        // Guarda contra voltar a gravar a data em UTC: entre 21:00 e 23:59 de
        // Brasília o dia UTC já é o dia seguinte, e o nome saía com +1 dia.
        $this->assertSame('America/Sao_Paulo', config('app.timezone'));
        $this->assertSame('-03:00', now()->format('P'));

        Storage::fake('local');

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $item = $this->makeItem($rnc, 'NC 1');

        // 22:30 em São Paulo = 01:30 do dia SEGUINTE em UTC.
        $this->travelTo(now()->setTimeFromTimeString('22:30:00'));

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.item.evidencia.upload', [$rnc, $item]), [
                'evidence' => UploadedFile::fake()->image('foto.png'),
            ])
            ->assertRedirect();

        $evidence = Evidence::withoutGlobalScopes()->where('rnc_item_id', $item->id)->firstOrFail();
        $nome = basename($evidence->stored_path);

        // A data embutida no nome é a de São Paulo...
        $this->assertStringContainsString(now()->format('dmY'), $nome);

        // ...e `created_at` concorda com ela, sem diferença de dia.
        $this->assertSame(
            now()->format('dmY'),
            $evidence->created_at->format('dmY'),
            'A data do arquivo e a do created_at precisam ser o mesmo dia em Brasília.'
        );
    }

    // ------------------------------------------------------------------
    // Exclusão do RNC (listagem)
    // ------------------------------------------------------------------

    public function test_apenas_admin_e_super_admin_excluem_o_rnc(): void
    {
        Storage::fake('local');
        Mail::fake();

        $tenant = $this->makeTenant();
        $manager = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $admin = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $item = $this->makeItem($rnc, 'NC 1');

        // Escreve, mas não exclui: botão fora da tela e rota respondendo 403.
        $this->actingAs($manager)->withSession($this->sessionData($tenant))
            ->get(route('rnc.index'))
            ->assertOk()
            ->assertDontSee('data-confirm="Excluir o RNC', false);

        $this->actingAs($manager)->withSession($this->sessionData($tenant))
            ->delete(route('rnc.destroy', $rnc))
            ->assertForbidden();

        $this->assertDatabaseHas('rncs', ['id' => $rnc->id]);

        $this->actingAs($admin)->withSession($this->sessionData($tenant))
            ->get(route('rnc.index'))
            ->assertOk()
            ->assertSee('data-confirm="Excluir o RNC', false);
    }

    public function test_excluir_rnc_publicado_apaga_evidencias_pdf_e_link(): void
    {
        Storage::fake('local');
        Mail::fake();

        $tenant = $this->makeTenant();
        $admin = User::factory()->create(['role' => Role::Admin, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $item = $this->makeItem($rnc, 'NC 1');

        $this->actingAs($admin)->withSession($this->sessionData($tenant))
            ->post(route('rnc.item.evidencia.upload', [$rnc, $item]), [
                'evidence' => UploadedFile::fake()->image('foto.png'),
            ])
            ->assertRedirect();

        $evidence = Evidence::withoutGlobalScopes()->where('rnc_item_id', $item->id)->firstOrFail();
        $arquivo = $evidence->stored_path;

        $this->actingAs($admin)->withSession($this->sessionData($tenant))
            ->post(route('rnc.publish', $rnc))
            ->assertRedirect();

        $revisao = $rnc->revisions()->firstOrFail();
        Storage::disk('local')->assertExists($revisao->pdf_path);

        $token = $revisao->public_token;

        $this->actingAs($admin)->withSession($this->sessionData($tenant))
            ->from(route('rnc.index'))
            ->delete(route('rnc.destroy', $rnc))
            ->assertRedirect(route('rnc.index'));

        $this->assertDatabaseMissing('rncs', ['id' => $rnc->id]);
        $this->assertDatabaseMissing('rnc_revisions', ['public_token' => $token]);
        $this->assertDatabaseMissing('rnc_items', ['id' => $item->id]);
        Storage::disk('local')->assertMissing($arquivo);
        Storage::disk('local')->assertMissing($revisao->pdf_path);
    }

    // ------------------------------------------------------------------

    /**
     * Espelha `RncPublicationService::hasPendingChanges()` — se o relatório
     * publicado está atrás do conteúdo atual.
     */
    private function publicationPendente(Rnc $rnc): bool
    {
        return app(RncPublicationService::class)->hasPendingChanges($rnc->fresh());
    }

    public function test_seeder_popula_os_itens_da_nbr_5410(): void
    {
        $seeder = new RncCatalogSeeder;

        $seeder->run();
        $seeder->run();

        $nbr = NormaTecnica::where('codigo', 'NBR 5410')->firstOrFail();

        $this->assertSame(163, $nbr->itens()->count(), 'O CSV de seed traz 163 itens e a reexecução é idempotente.');
        $this->assertSame('Proteção contra choques elétricos', $nbr->itens()->where('codigo', '5.1')->value('descricao'));
        $this->assertTrue($nbr->itens()->where('codigo', '6.4.1')->exists());
    }

    public function test_formulario_lista_somente_as_normas_com_itens(): void
    {
        (new RncCatalogSeeder)->run();

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.show', $rnc))
            ->assertOk()
            ->assertSee('NBR 5410')
            ->assertSee('"codigo":"6.4.1"', false)
            ->assertDontSee('NBR 5419', false, 'Norma sem itens catalogados não entra no picker.');
    }

    public function test_relatorio_agrupa_as_referencias_por_norma(): void
    {
        Storage::fake('local');

        (new RncCatalogSeeder)->run();

        $tenant = $this->makeTenant();
        $user = User::factory()->create(['role' => Role::Manager, 'tenant_id' => $tenant->id]);
        $rnc = $this->makeRnc($tenant);
        $item = $this->makeItem($rnc, 'NC 1');

        $nr10 = NormaTecnica::where('codigo', 'NR-10')->firstOrFail();
        $anterior = $nr10->itens()->create(['codigo' => '10.3.1', 'descricao' => 'Serviços com risco elétrico', 'ordem' => 2]);
        $posterior = $nr10->itens()->create(['codigo' => '10.1.2', 'descricao' => 'Disposições gerais', 'ordem' => 1]);

        $nbr5410 = NormaTecnica::where('codigo', 'NBR 5410')->firstOrFail();
        $itemNbr = $nbr5410->itens()->where('codigo', '5.1')->firstOrFail();

        // Anexados fora de ordem: o relatório ordena os códigos por valor natural.
        $item->normaItens()->attach([$anterior->id, $itemNbr->id, $posterior->id]);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->post(route('rnc.publish', $rnc))
            ->assertRedirect();

        $revisao = $rnc->revisions()->firstOrFail();

        $this->assertStringContainsString('NR-10 Item(s): 10.1.2 10.3.1', $revisao->markdown);
        $this->assertStringContainsString('NBR 5410 Item(s): 5.1', $revisao->markdown);

        $this->actingAs($user)->withSession($this->sessionData($tenant))
            ->get(route('rnc.revision.print', [$rnc, $revisao]))
            ->assertOk()
            ->assertSee('NR-10 Item(s): 10.1.2 10.3.1')
            ->assertSee('NBR 5410 Item(s): 5.1');
    }

    protected function makeTenant(): Tenant
    {
        return Tenant::create(['name' => 'Cliente RNC '.Str::random(5)]);
    }

    protected function makeRnc(Tenant $tenant): Rnc
    {
        $number = Rnc::nextNumber($tenant->id);

        return Rnc::create([
            'tenant_id' => $tenant->id,
            'number' => $number,
            'code' => Rnc::makeCode($number),
            'titulo' => 'Relatório de inspeção',
            'descricao' => 'Inspeção mensal das instalações.',
            'modelo' => RncModelo::Tecnica,
            'responsavel_nome' => 'João da Silva',
            'responsavel_cargo' => 'Engenheiro Eletricista',
            'status' => RncStatus::Rascunho,
            'current_revision' => 0,
        ]);
    }

    protected function makeItem(Rnc $rnc, string $titulo): RncItem
    {
        return $rnc->items()->create([
            'tenant_id' => $rnc->tenant_id,
            'numero' => $rnc->nextItemNumber(),
            'titulo' => $titulo,
            'descricao' => 'Condição encontrada em campo.',
            'recomendacao' => 'Corrigir e revalidar.',
            'prazo_adequacao' => now()->addWeek(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionData(Tenant $tenant): array
    {
        return ['tenant_id' => $tenant->id, 'two_step_verified' => true];
    }
}
