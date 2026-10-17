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
#[Hidden(['password', 'remember_token'])]
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
        ];
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
