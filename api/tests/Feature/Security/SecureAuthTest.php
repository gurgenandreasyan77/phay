<?php

namespace Tests\Feature\Security;

use App\Mail\InvitationMail;
use App\Mail\PasswordResetMail;
use App\Mail\VerificationCodeMail;
use App\Models\SecurityLogEntry;
use App\Models\User;
use App\Security\Sms\SmsSender;
use App\Security\Totp;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\FakeSmsSender;
use Tests\Feature\Tenancy\TenancyTestHelpers;
use Tests\TestCase;

class SecureAuthTest extends TestCase
{
    use RefreshDatabase, TenancyTestHelpers;

    private const PASSWORD = 'correct-horse-42';

    private FakeSmsSender $sms;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->sms = new FakeSmsSender;
        $this->app->instance(SmsSender::class, $this->sms);
    }

    /** Signs in for real (no Sanctum::actingAs), as the admin panel does. */
    private function login(string $email, string $password = self::PASSWORD): TestResponse
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');

        return $this->postJson('/api/v1/admin/login', ['email' => $email, 'password' => $password]);
    }

    private function asBearer(string $token, string $method, string $uri, array $data = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->json($method, $uri, $data, ['Authorization' => "Bearer {$token}"]);
    }

    private function code(?int $offset = 0): string
    {
        return Totp::code(UserFactory::TWO_FACTOR_SECRET, time() + $offset);
    }

    /** The token after # in a link. */
    private function tokenOf(string $url): string
    {
        return substr($url, strpos($url, '#') + 1);
    }

    public function test_with_2fa_the_password_alone_gives_a_challenge_not_a_session(): void
    {
        User::factory()->withTwoFactor()->create(['email' => 'ani@shop.am', 'password' => Hash::make(self::PASSWORD)]);

        $challenge = $this->login('ani@shop.am')
            ->assertOk()
            ->assertJsonPath('data.twoFactor', true)
            ->assertJsonMissingPath('data.token')
            ->json('data.challenge');

        $this->postJson('/api/v1/admin/login/two-factor', ['challenge' => $challenge, 'code' => '000000'])
            ->assertUnprocessable()->assertJsonValidationErrors('code');

        $token = $this->postJson('/api/v1/admin/login/two-factor', ['challenge' => $challenge, 'code' => $this->code()])
            ->assertOk()
            ->json('data.token');

        $this->asBearer($token, 'GET', '/api/v1/admin/me')->assertOk()->assertJsonPath('data.security.twoFactorEnabled', true);

        // The challenge is spent, and the same code doesn't work twice.
        $this->postJson('/api/v1/admin/login/two-factor', ['challenge' => $challenge, 'code' => $this->code()])
            ->assertUnprocessable()->assertJsonValidationErrors('challenge');
        $again = $this->login('ani@shop.am')->json('data.challenge');
        $this->postJson('/api/v1/admin/login/two-factor', ['challenge' => $again, 'code' => $this->code()])
            ->assertUnprocessable()->assertJsonValidationErrors('code');
    }

    public function test_a_recovery_code_works_once_and_wrong_codes_burn_the_challenge(): void
    {
        $user = User::factory()->withTwoFactor()->create(['email' => 'ani@shop.am', 'password' => Hash::make(self::PASSWORD)]);

        $challenge = $this->login('ani@shop.am')->json('data.challenge');
        $this->postJson('/api/v1/admin/login/two-factor', ['challenge' => $challenge, 'recovery_code' => 'ABCD-1234'])->assertOk();
        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => 'recovery_code_used']);

        $challenge = $this->login('ani@shop.am')->json('data.challenge');
        $this->postJson('/api/v1/admin/login/two-factor', ['challenge' => $challenge, 'recovery_code' => 'abcd-1234'])->assertUnprocessable();

        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/admin/login/two-factor', ['challenge' => $challenge, 'code' => '111111']);
        }
        $this->postJson('/api/v1/admin/login/two-factor', ['challenge' => $challenge, 'code' => $this->code()])
            ->assertUnprocessable()->assertJsonValidationErrors('challenge');
    }

    public function test_staff_must_set_up_2fa_before_anything_else(): void
    {
        $admin = User::factory()->create(['email' => 'boss@servio.am', 'password' => Hash::make(self::PASSWORD)]);
        $admin->forceFill(['is_super_admin' => true])->save();
        $old = $admin->createToken('admin-panel')->plainTextToken;

        $token = $this->login('boss@servio.am')
            ->assertOk()
            ->assertJsonPath('data.user.twoFactorSetupRequired', true)
            ->json('data.token');

        $this->asBearer($token, 'GET', '/api/v1/admin/me')->assertOk()->assertJsonPath('data.security.twoFactorSetupRequired', true);
        $this->asBearer($token, 'GET', '/api/v1/admin/tenants')->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');
        $this->asBearer($old, 'GET', '/api/v1/admin/dashboard')->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');

        $this->asBearer($token, 'POST', '/api/v1/admin/security/two-factor', ['password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $secret = $this->asBearer($token, 'POST', '/api/v1/admin/security/two-factor', ['password' => self::PASSWORD])
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('data.otpauthUrl', fn ($url) => str_starts_with($url, 'otpauth://totp/Phay:'))->etc())
            ->json('data.secret');

        $this->asBearer($token, 'POST', '/api/v1/admin/security/two-factor/confirm', ['code' => '123456'])->assertUnprocessable();
        $codes = $this->asBearer($token, 'POST', '/api/v1/admin/security/two-factor/confirm', ['code' => Totp::code($secret)])
            ->assertOk()
            ->assertJsonCount(8, 'data.recoveryCodes')
            ->json('data.recoveryCodes');
        $this->assertMatchesRegularExpression('/^[a-z2-9]{4}-[a-z2-9]{4}$/', $codes[0]);

        $this->asBearer($token, 'GET', '/api/v1/admin/tenants')->assertOk();
        // Turning 2FA on signed the older session out.
        $this->assertSame(1, $admin->tokens()->count());

        // Required for staff: can't be turned off.
        $this->asBearer($token, 'DELETE', '/api/v1/admin/security/two-factor', ['password' => self::PASSWORD, 'code' => Totp::code($secret, time() + 30)])
            ->assertUnprocessable();
        $this->assertNotNull($admin->fresh()->two_factor_secret);
        // Stored encrypted.
        $this->assertNotSame($secret, \DB::table('users')->where('id', $admin->id)->value('two_factor_secret'));
    }

    public function test_owners_need_2fa_when_configured_and_the_owner_can_require_it_for_the_team(): void
    {
        config(['security.two_factor.required_for_owners' => true]);
        $shop = $this->createTenant('test-shop');
        $owner = User::factory()->forTenant($shop, 'owner')->create();
        $moderator = User::factory()->forTenant($shop, 'moderator')->create();
        $admin = User::factory()->forTenant($shop, 'admin')->create();

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');

        Sanctum::actingAs($moderator);
        $this->getJson('/api/v1/admin/leads')->assertOk();

        Sanctum::actingAs($admin);
        $this->putJson('/api/v1/admin/security/business', ['require_two_factor' => true])->assertForbidden();

        $owner->forceFill(['two_factor_secret' => UserFactory::TWO_FACTOR_SECRET, 'two_factor_confirmed_at' => now()])->save();
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/security')->assertOk()->assertJsonPath('data.business.requireTwoFactor', false);
        $this->putJson('/api/v1/admin/security/business', ['require_two_factor' => true])->assertOk();

        Sanctum::actingAs($moderator);
        $this->getJson('/api/v1/admin/leads')->assertForbidden()->assertJsonPath('code', 'two_factor_setup_required');
        $this->getJson('/api/v1/admin/security')->assertOk()->assertJsonPath('data.twoFactor.required', true)->assertJsonPath('data.business', null);
    }

    public function test_someone_not_required_can_turn_2fa_off_with_password_and_code(): void
    {
        $user = User::factory()->withTwoFactor()->create(['password' => Hash::make(self::PASSWORD)]);
        Sanctum::actingAs($user);

        $this->deleteJson('/api/v1/admin/security/two-factor', ['password' => self::PASSWORD, 'code' => '000000'])->assertUnprocessable();
        $this->deleteJson('/api/v1/admin/security/two-factor', ['password' => self::PASSWORD, 'code' => $this->code()])->assertOk();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('security_events', ['user_id' => $user->id, 'event' => 'two_factor_disabled']);
    }

    public function test_an_invited_owner_sets_their_password_from_the_link(): void
    {
        $shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->superAdmin()->create());

        $url = $this->postJson("/api/v1/admin/tenants/{$shop->id}/users", ['name' => 'Ani', 'email' => 'ani@shop.am'])
            ->assertCreated()
            ->assertJsonPath('invitation.emailed', true)
            ->json('invitation.url');
        $this->assertStringStartsWith('http://localhost:3001/admin/invite#', $url);
        Mail::assertSent(InvitationMail::class, fn (InvitationMail $mail) => $mail->url === $url && $mail->hasTo('ani@shop.am'));
        $token = $this->tokenOf($url);

        $this->postJson('/api/v1/admin/invitations/lookup', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.email', 'ani@shop.am')
            ->assertJsonPath('data.businesses.0', 'Test Shop');
        $this->postJson('/api/v1/admin/invitations/lookup', ['token' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->postJson('/api/v1/admin/invitations/accept', ['token' => $token, 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->app['auth']->forgetGuards();
        $session = $this->postJson('/api/v1/admin/invitations/accept', ['token' => $token, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertOk()
            ->json('data.token');
        $this->asBearer($session, 'GET', '/api/v1/admin/me')->assertOk()->assertJsonPath('data.email', 'ani@shop.am');

        // One time only; and the account is now active.
        $this->postJson('/api/v1/admin/invitations/lookup', ['token' => $token])->assertUnprocessable();
        $this->login('ani@shop.am')->assertOk()->assertJsonStructure(['data' => ['token']]);
        $this->assertDatabaseHas('security_events', ['email' => 'ani@shop.am', 'event' => 'invitation_accepted']);
    }

    public function test_an_expired_invitation_is_refused_and_a_new_one_cancels_the_old(): void
    {
        $shop = $this->createTenant('test-shop');
        Sanctum::actingAs(User::factory()->superAdmin()->create());
        $first = $this->tokenOf($this->postJson("/api/v1/admin/tenants/{$shop->id}/users", ['name' => 'Ani', 'email' => 'ani@shop.am'])->json('invitation.url'));
        $user = User::query()->where('email', 'ani@shop.am')->sole();

        $second = $this->tokenOf($this->postJson("/api/v1/admin/tenants/{$shop->id}/users/{$user->id}/invitation")->assertOk()->json('data.url'));
        $this->postJson('/api/v1/admin/invitations/lookup', ['token' => $first])->assertUnprocessable();
        $this->postJson('/api/v1/admin/invitations/lookup', ['token' => $second])->assertOk();

        $this->travel(73)->hours();
        $this->postJson('/api/v1/admin/invitations/lookup', ['token' => $second])->assertUnprocessable();
    }

    public function test_forgot_password_answers_the_same_and_the_link_resets_once(): void
    {
        $user = User::factory()->withTwoFactor()->create(['email' => 'ani@shop.am']);
        $user->createToken('admin-panel');

        $this->postJson('/api/v1/admin/password/forgot', ['email' => 'nobody@shop.am'])->assertOk()->assertJsonPath('data.status', 'sent');
        Mail::assertNothingSent();

        $this->postJson('/api/v1/admin/password/forgot', ['email' => 'ANI@shop.am'])->assertOk()->assertJsonPath('data.status', 'sent');
        $url = null;
        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use (&$url) {
            $url = $mail->url;

            return true;
        });
        $token = $this->tokenOf($url);

        $this->postJson('/api/v1/admin/password/reset/lookup', ['token' => $token])->assertOk()->assertJsonPath('data.email', 'ani@shop.am');
        $this->postJson('/api/v1/admin/password/reset', ['token' => $token, 'password' => 'new-password-77', 'password_confirmation' => 'new-password-77'])->assertOk();

        $this->assertTrue(Hash::check('new-password-77', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->postJson('/api/v1/admin/password/reset', ['token' => $token, 'password' => 'other-password-77', 'password_confirmation' => 'other-password-77'])
            ->assertUnprocessable()->assertJsonValidationErrors('token');

        // A reset doesn't skip 2FA.
        $this->login('ani@shop.am', 'new-password-77')->assertOk()->assertJsonPath('data.twoFactor', true);
    }

    public function test_email_and_phone_are_confirmed_with_codes(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ani@shop.am']);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/admin/security/email/code')->assertOk();
        $code = null;
        Mail::assertSent(VerificationCodeMail::class, function (VerificationCodeMail $mail) use (&$code) {
            $code = $mail->code;

            return $mail->hasTo('ani@shop.am');
        });
        // A pause before the next code.
        $this->postJson('/api/v1/admin/security/email/code')->assertUnprocessable();

        $wrong = $code === '000000' ? '111111' : '000000';
        $this->postJson('/api/v1/admin/security/email/verify', ['code' => $wrong])->assertUnprocessable();
        $this->postJson('/api/v1/admin/security/email/verify', ['code' => $code])->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->postJson('/api/v1/admin/security/email/verify', ['code' => $code])->assertUnprocessable();

        $this->postJson('/api/v1/admin/security/phone/code', ['phone' => 'not a phone'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/admin/security/phone/code', ['phone' => '055 12 34 56'])->assertOk();
        $this->assertSame('+37455123456', $this->sms->sent[0]['to']);
        $this->getJson('/api/v1/admin/security')->assertJsonPath('data.phone.pending', '+37455••••56');

        $this->postJson('/api/v1/admin/security/phone/verify', ['code' => $this->sms->lastCode()])->assertOk();
        $fresh = $user->fresh();
        $this->assertSame('+37455123456', $fresh->phone);
        $this->assertNotNull($fresh->phone_verified_at);
    }

    public function test_a_code_takes_only_a_few_attempts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/admin/security/phone/code', ['phone' => '+37499000000'])->assertOk();
        $right = $this->sms->lastCode();
        $wrong = $right === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/admin/security/phone/verify', ['code' => $wrong])->assertUnprocessable();
        }
        $this->postJson('/api/v1/admin/security/phone/verify', ['code' => $right])->assertUnprocessable();
        $this->assertNull($user->fresh()->phone);
    }

    public function test_devices_are_listed_and_signed_out(): void
    {
        $user = User::factory()->create(['password' => Hash::make(self::PASSWORD)]);
        $other = $user->createToken('admin-panel')->accessToken;
        $token = $this->login($user->email)->json('data.token');

        $this->asBearer($token, 'GET', '/api/v1/admin/security')
            ->assertOk()
            ->assertJsonCount(2, 'data.sessions')
            ->assertJsonPath('data.events.0.event', 'login_succeeded');

        $this->asBearer($token, 'DELETE', "/api/v1/admin/security/sessions/{$other->id}")->assertNoContent();
        $this->assertSame(1, $user->tokens()->count());

        $user->createToken('admin-panel');
        $this->asBearer($token, 'DELETE', '/api/v1/admin/security/sessions')->assertOk()->assertJsonPath('data.revoked', 1);
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_staff_reset_a_lost_2fa_and_the_log_shows_it(): void
    {
        $shop = $this->createTenant('test-shop');
        $owner = User::factory()->forTenant($shop)->withTwoFactor()->create();
        $owner->createToken('admin-panel');
        $boss = User::factory()->superAdmin()->create();
        Sanctum::actingAs($boss);

        $this->deleteJson("/api/v1/admin/tenants/{$shop->id}/users/{$owner->id}/two-factor")->assertOk();
        $this->assertFalse($owner->fresh()->hasTwoFactorEnabled());
        $this->assertSame(0, $owner->tokens()->count());

        $this->getJson('/api/v1/admin/security-events?event=two_factor_reset')
            ->assertOk()
            ->assertJsonPath('data.0.user.id', $owner->id)
            ->assertJsonPath('data.0.actor.id', $boss->id);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/security-events')->assertForbidden();
    }

    public function test_failed_sign_ins_are_logged_even_for_unknown_emails(): void
    {
        $user = User::factory()->create(['email' => 'ani@shop.am', 'password' => Hash::make(self::PASSWORD)]);
        $invited = User::factory()->invited()->create(['email' => 'new@shop.am']);

        $this->login('ani@shop.am', 'wrong-pass')->assertUnprocessable();
        $this->login('ghost@shop.am', 'whatever')->assertUnprocessable();
        // An invited login with no password yet looks the same.
        $this->login('new@shop.am', '')->assertUnprocessable();

        $this->assertSame(1, SecurityLogEntry::query()->where('user_id', $user->id)->where('event', 'login_failed')->count());
        $this->assertDatabaseHas('security_events', ['email' => 'ghost@shop.am', 'user_id' => null, 'event' => 'login_failed']);
        $this->assertNull($invited->fresh()->password);
    }

    public function test_one_email_is_throttled_across_ips(): void
    {
        User::factory()->create(['email' => 'ani@shop.am']);

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])->login('ani@shop.am', 'wrong-pass')->assertUnprocessable();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])->login('ani@shop.am', 'wrong-pass')->assertStatus(429);
    }

    public function test_the_first_super_admin_comes_from_a_one_time_console_link(): void
    {
        $this->artisan('platform:bootstrap', ['email' => 'Boss@Servio.am', '--name' => 'Gurgen'])
            ->expectsOutputToContain('/admin/invite#')
            ->assertSuccessful();

        $boss = User::query()->where('email', 'boss@servio.am')->sole();
        $this->assertTrue($boss->is_super_admin);
        $this->assertNull($boss->password);

        // Not activated yet: the same email gets a fresh link; anyone else is refused.
        $this->artisan('platform:bootstrap', ['email' => 'boss@servio.am'])->assertSuccessful();
        $this->artisan('platform:bootstrap', ['email' => 'other@servio.am'])->assertFailed();

        $url = null;
        Mail::assertSent(InvitationMail::class, function (InvitationMail $mail) use (&$url) {
            $url = $mail->url;

            return true;
        });

        // The latest link sets the password; then 2FA comes first.
        $sent = Mail::sent(InvitationMail::class);
        $token = $this->tokenOf($sent->last()->url);
        $this->app['auth']->forgetGuards();
        $session = $this->postJson('/api/v1/admin/invitations/accept', ['token' => $token, 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('data.user.twoFactorSetupRequired', true)
            ->json('data.token');
        $this->asBearer($session, 'GET', '/api/v1/admin/tenants')->assertForbidden();

        // Once there is an active super admin, the console refuses.
        $this->artisan('platform:bootstrap', ['email' => 'boss@servio.am'])->assertFailed();
        $this->assertDatabaseHas('security_events', ['user_id' => $boss->id, 'event' => 'super_admin_bootstrapped']);
    }

    public function test_the_console_resets_a_lost_2fa(): void
    {
        $boss = User::factory()->superAdmin()->create(['email' => 'boss@servio.am']);

        $this->artisan('platform:two-factor:reset', ['email' => 'boss@servio.am'])
            ->expectsConfirmation('Turn off 2FA for boss@servio.am and sign them out everywhere?', 'yes')
            ->assertSuccessful();

        $this->assertFalse($boss->fresh()->hasTwoFactorEnabled());
        $this->assertDatabaseHas('security_events', ['user_id' => $boss->id, 'event' => 'two_factor_reset']);
    }

    public function test_sessions_expire_after_a_week(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('admin-panel')->plainTextToken;

        $this->asBearer($token, 'GET', '/api/v1/admin/me')->assertOk();
        $this->travel(8)->days();
        $this->asBearer($token, 'GET', '/api/v1/admin/me')->assertUnauthorized();
    }
}
