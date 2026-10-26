<?php

namespace Tests\Feature\Tenancy;

use App\Models\AccountToken;
use App\Models\AdminNotification;
use App\Models\AssistantBonus;
use App\Models\Concerns\BelongsToTenant;
use App\Models\MetaDataDeletion;
use App\Models\ModulePrice;
use App\Models\Plan;
use App\Models\SecurityLogEntry;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The most common multi-tenancy bug is a model that forgets the tenant
 * scope. This fails as soon as a new model is added without it.
 */
class TenantModelCoverageTest extends TestCase
{
    use RefreshDatabase;

    // Platform-level models: they are not owned by one business. Plans and
    // module prices are the platform's own; a subscription and its payments
    // are what the platform bills a business, read across businesses by the
    // super admin and always looked up by tenant_id explicitly (Subscriptions);
    // so are the AI assistant requests the super admin gives (AssistantQuota).
    // A notification belongs to a person and may be about a business they
    // aren't in (platform staff); read by user_id (AdminNotification::visibleTo).
    private const PLATFORM_MODELS = [
        User::class, Tenant::class, Site::class,
        Plan::class, ModulePrice::class, Subscription::class, SubscriptionPayment::class,
        AssistantBonus::class, AdminNotification::class,
        // Sign-in security belongs to a person, not a business.
        AccountToken::class, VerificationCode::class, SecurityLogEntry::class,
        // Meta's deletion requests name a Facebook login, which may be
        // connected to several businesses.
        MetaDataDeletion::class,
    ];

    public function test_every_content_model_is_scoped_to_a_tenant(): void
    {
        $models = collect(glob(app_path('Models/*.php')))
            ->map(fn (string $path) => 'App\\Models\\'.basename($path, '.php'))
            ->filter(fn (string $class) => is_subclass_of($class, Model::class))
            ->reject(fn (string $class) => in_array($class, self::PLATFORM_MODELS, true));

        $this->assertNotEmpty($models);

        foreach ($models as $class) {
            $this->assertContains(BelongsToTenant::class, class_uses_recursive($class), "{$class} must use BelongsToTenant.");

            $table = (new $class)->getTable();
            $this->assertTrue(Schema::hasColumn($table, 'tenant_id'), "{$table} must have a tenant_id column.");
        }
    }
}
