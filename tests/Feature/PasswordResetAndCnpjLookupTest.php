<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Support\CnpjLookup;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetAndCnpjLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_sends_reset_password_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'name' => 'Fabio Souza',
            'role' => Role::Manager,
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_reset_notification_renders_greenjob_mailable_with_reset_url(): void
    {
        Notification::fake();

        $user = User::factory()->create(['role' => Role::Manager]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            $this->assertInstanceOf(PasswordResetMail::class, $mail);
            $this->assertSame($user->name, $mail->name);
            $this->assertTrue($mail->hasTo($user->email), 'O e-mail de reset deve ter o destinatário preenchido.');
            $this->assertSame(
                route('password.reset', ['token' => $notification->token, 'email' => $user->email]),
                $mail->url,
            );

            // Verifica a identidade visual e a tradução no corpo renderizado.
            $body = $mail->render();

            return str_contains($body, 'Redefinição de senha')
                && str_contains($body, 'GreenJob')
                && str_contains($body, 'Gestão de Conformidades')
                && str_contains($body, 'minutos')
                && str_contains($body, $mail->url);
        });
    }

    public function test_reset_url_matches_the_reset_route(): void
    {
        $user = User::factory()->create(['role' => Role::Manager]);
        $token = Password::broker()->createToken($user);
        Notification::fake();

        $user->sendPasswordResetNotification($token);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, $token) {
            $mail = $notification->toMail($user);

            return $notification->token === $token
                && $mail instanceof PasswordResetMail
                && $mail->url === route('password.reset', ['token' => $token, 'email' => $user->email]);
        });
    }

    public function test_super_admin_can_lookup_cnpj(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response([
                'razao_social' => 'EMPRESA EXEMPLO DE ENERGIA LTDA',
                'logradouro' => 'RUA DAS FLORES',
                'numero' => '123',
                'complemento' => 'SALA 5',
                'bairro' => 'CENTRO',
                'municipio' => 'SAO PAULO',
                'uf' => 'SP',
                'cep' => '01000-000',
            ]),
        ]);

        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cnpj-lookup', '11222333000181'))
            ->assertOk()
            ->assertJson([
                'name' => 'Empresa Exemplo de Energia LTDA',
                'address' => 'Rua das Flores, 123, Centro, Sao Paulo — SP, CEP 01000-000',
            ]);
    }

    public function test_non_super_admin_cannot_lookup_cnpj(): void
    {
        $manager = User::factory()->create(['role' => Role::Manager]);

        $this->actingAs($manager)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cnpj-lookup', '11222333000181'))
            ->assertForbidden();
    }

    public function test_lookup_returns_404_for_invalid_cnpj_digits(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response([
                'message' => 'CNPJ não encontrado',
                'type' => 'not_found',
            ], 404),
        ]);

        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cnpj-lookup', '00112233000100'))
            ->assertNotFound();
    }

    public function test_lookup_returns_503_when_api_is_unavailable(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response('', 500),
        ]);

        $admin = User::factory()->create(['role' => Role::SuperAdmin]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->getJson(route('tenants.cnpj-lookup', '11222333000181'))
            ->assertStatus(503);
    }

    public function test_title_case_keeps_business_suffix_uppercase(): void
    {
        $this->assertSame(
            'Acme Serviços Elétricos LTDA',
            CnpjLookup::titleCase('ACME SERVIÇOS ELÉTRICOS LTDA'),
        );

        $this->assertSame(
            'João da Silva e Santos Jr',
            CnpjLookup::titleCase('JOÃO DA SILVA E SANTOS JR'),
        );
    }

    public function test_normalize_accepts_masked_or_raw_cnpj(): void
    {
        $this->assertSame('11222333000181', CnpjLookup::normalize('11.222.333/0001-81'));
        $this->assertSame('11222333000181', CnpjLookup::normalize('11222333000181'));
        $this->assertNull(CnpjLookup::normalize('123'));
        $this->assertNull(CnpjLookup::normalize(''));
    }
}
