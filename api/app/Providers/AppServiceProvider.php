<?php

namespace App\Providers;

use App\Assistant\AnthropicAssistantClient;
use App\Assistant\AssistantClient;
use App\Features\FeatureFlag;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant per request, command or queued job ("scoped" resets
        // between Octane requests and queue jobs).
        $this->app->scoped(TenantContext::class);

        $this->app->bind(AssistantClient::class, fn () => new AnthropicAssistantClient(
            apiKey: config('services.anthropic.api_key'),
            model: config('services.anthropic.model'),
            effort: config('services.anthropic.effort'),
            maxTokens: config('services.anthropic.max_tokens'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // This is an API-only app with no "login" named route. Without this,
        // an unauthenticated request to an auth:sanctum route that doesn't
        // send `Accept: application/json` hits Authenticate::redirectTo(),
        // which tries to generate route('login') and throws a 500 instead
        // of the expected 401.
        Authenticate::redirectUsing(fn () => null);

        RateLimiter::for('leads', function (Request $request) {
            return Limit::perMinute((int) config('services.leads.rate_limit_per_minute'))
                ->by($request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute((int) config('services.admin_login.rate_limit_per_minute'))
                ->by($request->ip());
        });

        // Each question to the AI assistant is a paid model call.
        RateLimiter::for('assistant', function (Request $request) {
            return Limit::perMinute(10)->by('user:'.$request->user()?->id);
        });

        // Reading a big spreadsheet is heavy: a few uploads a minute is plenty.
        RateLimiter::for('data-imports', function (Request $request) {
            return Limit::perMinute(10)->by('user:'.$request->user()?->id);
        });

        // Feature flags belong to a business: Feature::active('market')
        // answers for the current tenant, not for the signed-in user.
        Feature::resolveScopeUsing(fn () => app(TenantContext::class)->current());

        foreach (FeatureFlag::cases() as $flag) {
            Feature::define($flag->value, fn () => $flag->default());
        }

        Gate::define('manage-feature-flags', fn (User $user) => $user->is_super_admin);
        Gate::define('manage-tenants', fn (User $user) => $user->isPlatformStaff());
    }
}
