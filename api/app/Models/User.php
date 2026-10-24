<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\BusinessRole;
use App\Enums\PlatformRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Same as the column defaults, so a fresh model answers false, not null. */
    protected $attributes = [
        'is_super_admin' => false,
        'is_platform_moderator' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_platform_moderator' => 'boolean',
            'phone_verified_at' => 'datetime',
            // Encrypted with APP_KEY: a database dump alone doesn't give the codes away.
            'two_factor_secret' => 'encrypted',
            // sha256 hashes of the unused recovery codes.
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_last_step' => 'integer',
            'password_changed_at' => 'datetime',
        ];
    }

    /** Has set a password: an invited login without one can't sign in yet. */
    public function isActivated(): bool
    {
        return $this->password !== null;
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_confirmed_at !== null && $this->two_factor_secret !== null;
    }

    /**
     * Must use 2FA: platform staff always; a business owner (they see the
     * money) unless security.two_factor.required_for_owners is off; anyone
     * in a business whose owner asked for it.
     */
    public function requiresTwoFactor(): bool
    {
        if ($this->isPlatformStaff()) {
            return true;
        }

        $memberships = $this->tenants()->get(['tenants.id', 'tenants.require_two_factor']);

        return $memberships->contains(fn (Tenant $tenant) => (bool) $tenant->require_two_factor
            || (config('security.two_factor.required_for_owners') && $tenant->pivot->role === BusinessRole::Owner->value));
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)->withPivot('role')->withTimestamps();
    }

    public function currentTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'current_tenant_id');
    }

    /**
     * The business the admin panel works on: the one last chosen
     * (current_tenant_id) if the user may still access it, otherwise their
     * first business. Platform staff may work on any business and falls back
     * to the default one; anyone else without a business gets null.
     */
    public function resolveCurrentTenant(): ?Tenant
    {
        $current = $this->currentTenant;

        if ($current && $this->canAccessTenant($current)) {
            return $current;
        }

        return $this->tenants()->orderBy('tenants.id')->first()
            ?? ($this->isPlatformStaff() ? Tenant::default() : null);
    }

    public function canAccessTenant(Tenant $tenant): bool
    {
        return $this->isPlatformStaff()
            || $this->tenants()->whereKey($tenant->getKey())->exists();
    }

    /** Servio's own people: the super admin and platform moderators. */
    public function isPlatformStaff(): bool
    {
        return $this->platformRole() !== null;
    }

    public function platformRole(): ?PlatformRole
    {
        return match (true) {
            (bool) $this->is_super_admin => PlatformRole::SuperAdmin,
            (bool) $this->is_platform_moderator => PlatformRole::Moderator,
            default => null,
        };
    }

    /**
     * What this user may do in the business. Platform staff act as its owner;
     * anyone else has the role of their membership, or none.
     */
    public function businessRole(Tenant $tenant): ?BusinessRole
    {
        if ($this->isPlatformStaff()) {
            return BusinessRole::Owner;
        }

        $role = $this->tenants()->whereKey($tenant->getKey())->first()?->pivot->role;

        // An unknown legacy value gets the narrowest rights, not none.
        return $role === null ? null : (BusinessRole::tryFrom($role) ?? BusinessRole::Moderator);
    }
}
