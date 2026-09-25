<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Mail\TwoStepCodeMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoStepVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_when_accessing_verification(): void
    {
        $this->get(route('verificar'))->assertRedirect(route('login'));
    }

    public function test_dashboard_redirects_unverified_user_to_verification(): void
    {
        $user = User::factory()->create(['role' => Role::Manager]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verificar'));
    }

    public function test_dashboard_is_accessible_without_verification_when_user_disabled_2fa(): void
    {
        $user = User::factory()->withoutTwoStep()->create(['role' => Role::Manager]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_login_redirects_to_verification_when_2fa_enabled(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'role' => Role::Manager,
            'password' => 'Password@123',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Password@123',
        ])->assertRedirect(route('verificar'));
    }

    public function test_login_redirects_to_dashboard_when_2fa_disabled(): void
    {
        Mail::fake();

        $user = User::factory()->withoutTwoStep()->create([
            'role' => Role::Manager,
            'password' => 'Password@123',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'Password@123',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_code_is_sent_via_email_on_first_verification_view(): void
    {
        Mail::fake();

        $user = User::factory()->create(['role' => Role::Manager]);

        $this->actingAs($user)
            ->get(route('verificar'))
            ->assertOk()
            ->assertSee('Verificação em 2 etapas');

        Mail::assertSent(TwoStepCodeMail::class, fn (TwoStepCodeMail $mail) => $mail->hasTo($user->email));
    }

    public function test_valid_code_marks_session_as_verified(): void
    {
        Mail::fake();

        $user = User::factory()->create(['role' => Role::Manager]);
        $code = $user->issueTwoStepCode();

        $this->actingAs($user)
            ->post(route('verificar.store'), ['code' => $code])
            ->assertRedirect(route('dashboard'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_invalid_code_is_rejected(): void
    {
        Mail::fake();

        $user = User::factory()->create(['role' => Role::Manager]);
        $user->issueTwoStepCode();

        $this->actingAs($user)
            ->post(route('verificar.store'), ['code' => '000000'])
            ->assertSessionHasErrors('code')
            ->assertSessionMissing('two_step_verified');
    }

    public function test_expired_code_is_rejected(): void
    {
        Mail::fake();

        $user = User::factory()->create(['role' => Role::Manager]);
        $user->forceFill([
            'two_step_code_hash' => bcrypt('123456'),
            'two_step_code_expires_at' => now()->subMinute(),
        ])->save();

        $this->actingAs($user)
            ->post(route('verificar.store'), ['code' => '123456'])
            ->assertSessionHasErrors('code');
    }

    public function test_trusted_device_skips_verification_on_next_login(): void
    {
        Mail::fake();

        $user = User::factory()->create(['role' => Role::Manager]);
        $code = $user->issueTwoStepCode();

        $this->actingAs($user)
            ->post(route('verificar.store'), ['code' => $code, 'trust_device' => true])
            ->assertCookie('nr10_trusted_device');

        $token = $user->trustedDevices()->first()->device_token_hash;

        $this->assertNotNull($token);
        $this->assertTrue($user->hasTrustedDevice($token));
    }

    public function test_logout_revokes_trusted_device(): void
    {
        $user = User::factory()->create(['role' => Role::Manager]);
        $token = $user->trustCurrentDevice(null);

        $this->actingAs($user)
            ->withCookie('nr10_trusted_device', $token)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertFalse($user->fresh()->hasTrustedDevice($token));
    }

    public function test_new_user_created_with_2fa_enabled_by_default(): void
    {
        $admin = User::factory()->create(['role' => Role::SuperAdmin]);
        $tenant = Tenant::create(['name' => 'Cliente Teste']);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->post(route('usuarios.store'), [
                'name' => 'Novo Usuário',
                'email' => 'novo@example.com',
                'role' => Role::Manager->value,
                'tenant_id' => $tenant->id,
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
            ])
            ->assertRedirect(route('usuarios.index'));

        $this->assertTrue(User::where('email', 'novo@example.com')->first()->twoStepEnabled());
    }

    public function test_admin_can_disable_2fa_and_revokes_trusted_devices(): void
    {
        $admin = User::factory()->create(['role' => Role::SuperAdmin]);
        $user = User::factory()->create(['role' => Role::Manager]);
        $user->trustCurrentDevice(null);

        $this->assertSame(1, $user->trustedDevices()->count());

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->put(route('usuarios.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'two_step_enabled' => '0',
            ])
            ->assertRedirect();

        $this->assertFalse($user->fresh()->twoStepEnabled());
        $this->assertSame(0, $user->fresh()->trustedDevices()->count());
    }

    public function test_admin_can_enable_2fa(): void
    {
        $admin = User::factory()->create(['role' => Role::SuperAdmin]);
        $user = User::factory()->withoutTwoStep()->create(['role' => Role::Manager]);

        $this->actingAs($admin)
            ->withSession(['two_step_verified' => true])
            ->put(route('usuarios.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'two_step_enabled' => '1',
            ])
            ->assertRedirect();

        $this->assertTrue($user->fresh()->twoStepEnabled());
    }
}
