<?php

use App\Models\CalculatorSetting;
use App\Models\Tenant;
use App\Tenancy\TenantContext;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The modules a super admin manages become Services, Market and Site.
 *
 * - Orders and sales are in every business's admin: their flags go.
 * - Auto care and dry cleaning stop being modules: auto care was already a
 *   category of the services catalog, and the dry cleaning calculator with
 *   its add-ons now belongs to the Services module. A business that had
 *   either keeps all its data and gets Services on.
 * - Site management (logo, contacts, videos, before/after, blog, feedback
 *   form) is a new module, on for every business that has a site.
 */
return new class extends Migration
{
    private const REMOVED = ['orders', 'sales', 'auto-care', 'dry-cleaning'];

    public function up(): void
    {
        Schema::table('service_videos', function (Blueprint $table) {
            // before_after | info (App\Enums\SiteVideoKind).
            $table->string('kind', 20)->default('info')->after('service_id');
        });

        // What visitors write through the site's feedback form.
        Schema::create('feedback_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->text('message');
            $table->string('locale', 5)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'read_at']);
        });

        $now = now();
        $on = fn (int $tenantId, string $name) => DB::table('features')->updateOrInsert(
            ['name' => $name, 'scope' => 'tenant:'.$tenantId],
            ['value' => 'true', 'updated_at' => $now, 'created_at' => $now],
        );

        $active = fn (string $name) => DB::table('features')
            ->where('name', $name)
            ->where('value', 'true')
            ->pluck('scope')
            ->map(fn (string $scope) => (int) str_replace('tenant:', '', $scope))
            ->all();

        $dryCleaning = $active('dry-cleaning');
        foreach (array_unique([...$active('auto-care'), ...$dryCleaning]) as $tenantId) {
            if (DB::table('tenants')->where('id', $tenantId)->exists()) {
                $on($tenantId, 'services');
            }
        }

        // The calculator now exists only where it was made: keep it for every
        // business that had dry cleaning, even if its site never opened it.
        foreach ($dryCleaning as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant) {
                app(TenantContext::class)->run($tenant, fn () => CalculatorSetting::current());
            }
        }

        $withSite = DB::table('sites')->distinct()->pluck('tenant_id')
            ->push(DB::table('tenants')->where('slug', config('platform.default_tenant'))->value('id'))
            ->filter()
            ->unique();
        foreach ($withSite as $tenantId) {
            $on((int) $tenantId, 'site');
        }

        DB::table('features')->whereIn('name', self::REMOVED)->delete();
        DB::table('module_prices')->whereIn('feature_key', self::REMOVED)->delete();
        DB::table('plans')->get(['id', 'included_modules'])->each(function ($plan) {
            $modules = array_values(array_diff(json_decode($plan->included_modules, true) ?: [], self::REMOVED));
            DB::table('plans')->where('id', $plan->id)->update(['included_modules' => json_encode($modules)]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_messages');

        Schema::table('service_videos', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        // Orders come back on everywhere (as they were for new businesses);
        // the rest of the old modules resolve to their old defaults.
        $now = now();
        DB::table('tenants')->pluck('id')->each(fn (int $id) => DB::table('features')->updateOrInsert(
            ['name' => 'orders', 'scope' => 'tenant:'.$id],
            ['value' => 'true', 'updated_at' => $now, 'created_at' => $now],
        ));
        DB::table('features')->where('name', 'site')->delete();
    }
};
