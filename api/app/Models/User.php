<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
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
     * first business. A super admin may work on any business and falls back
     * to the default one; anyone else without a business gets null.
     */
    public function resolveCurrentTenant(): ?Tenant
    {
        $current = $this->currentTenant;

        if ($current && $this->canAccessTenant($current)) {
            return $current;
        }

        return $this->tenants()->orderBy('tenants.id')->first()
            ?? ($this->is_super_admin ? Tenant::default() : null);
    }

    public function canAccessTenant(Tenant $tenant): bool
    {
        return $this->is_super_admin
            || $this->tenants()->whereKey($tenant->getKey())->exists();
    }
}
