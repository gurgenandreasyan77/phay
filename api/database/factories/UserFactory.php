<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public const TWO_FACTOR_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * By default a user is an owner of the current (normally the default)
     * business, which is what every admin test before multi-tenancy assumed.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user) {
            $user->tenants()->attach(app(TenantContext::class)->id(), ['role' => 'owner']);
        });
    }

    /**
     * Member of the given business only.
     */
    public function forTenant(Tenant $tenant, string $role = 'owner'): static
    {
        return $this->afterCreating(function (User $user) use ($tenant, $role) {
            $user->tenants()->sync([$tenant->getKey() => ['role' => $role]]);
        });
    }

    /**
     * Not a member of any business.
     */
    public function withoutTenant(): static
    {
        return $this->afterCreating(fn (User $user) => $user->tenants()->detach());
    }

    /**
     * Platform administrator: every business and the platform screens.
     */
    public function superAdmin(): static
    {
        return $this->withTwoFactor()->afterCreating(fn (User $user) => $user->forceFill(['is_super_admin' => true])->save());
    }

    /**
     * Platform moderator: onboards businesses, can't change modules or the
     * platform team.
     */
    public function platformModerator(): static
    {
        return $this->withTwoFactor()->afterCreating(fn (User $user) => $user->forceFill(['is_platform_moderator' => true])->save());
    }

    /**
     * 2FA already set up (platform staff must have it). The secret is
     * self::TWO_FACTOR_SECRET, so a test can make codes with Totp::code().
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn () => [
            'two_factor_secret' => self::TWO_FACTOR_SECRET,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [hash('sha256', 'abcd1234')],
        ]);
    }

    /** An invited login that hasn't set its password yet. */
    public function invited(): static
    {
        return $this->state(fn () => ['password' => null, 'email_verified_at' => null]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
