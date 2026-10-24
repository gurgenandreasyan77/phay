<?php

namespace App\Providers;

use App\Assistant\AnthropicAssistantClient;
use App\Assistant\AssistantClient;
use App\Audience\NullSocialSource;
use App\Audience\SocialSource;
use App\Features\FeatureFlag;
use App\Google\GoogleCalendarClient;
use App\Google\HttpGoogleCalendarClient;
use App\Models\User;
use App\Security\Sms\LogSmsSender;
use App\Security\Sms\SmsSender;
use App\Security\Sms\TwilioSmsSender;
use App\Sites\RefreshSiteCache;
use App\Sites\SiteRevalidator;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
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

        // Collects cache tags during a request; sent once when it ends.
        $this->app->scoped(SiteRevalidator::class);

        // No social network can be connected yet; an Instagram source replaces this.
        $this->app->bind(SocialSource::class, NullSocialSource::class);

        $this->app->bind(GoogleCalendarClient::class, fn () => new HttpGoogleCalendarClient(
            clientId: config('services.google.client_id'),
            clientSecret: config('services.google.client_secret'),
            redirectUri: config('services.google.redirect_uri'),
        ));

        $this->app->bind(SmsSender::class, fn () => match (config('security.sms.driver')) {
            'twilio' => new TwilioSmsSender(
                sid: config('security.sms.twilio.sid'),
                token: config('security.sms.twilio.token'),
                from: config('security.sms.twilio.from'),
            ),
            default => new LogSmsSender,
        });

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

        // Per IP, and per typed email so one login can't be guessed at from many IPs.
        RateLimiter::for('admin-login', function (Request $request) {
            $email = 'email:'.Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute((int) config('services.admin_login.rate_limit_per_minute'))->by($request->ip()),
                Limit::perMinute(5)->by($email),
                Limit::perHour(20)->by($email),
            ];
        });

        // A challenge also burns after a few wrong codes (LoginChallenges).
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('password-forgot', fn (Request $request) => [
            Limit::perMinute(3)->by($request->ip()),
            Limit::perHour(5)->by('email:'.Str::lower((string) $request->input('email'))),
        ]);

        RateLimiter::for('account-links', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ? 'user:'.$request->user()->id : $request->ip()));

        // Password and 2FA code re-checks on the account page.
        RateLimiter::for('security', fn (Request $request) => Limit::perMinute(10)->by('user:'.$request->user()?->id));

        // Each SMS costs money; emails too after a point.
        RateLimiter::for('verification-codes', fn (Request $request) => [
            Limit::perMinute(5)->by('user:'.$request->user()?->id),
            Limit::perHour(10)->by('user:'.$request->user()?->id),
        ]);

        // At least 10 characters with letters and digits; in production also
        // not one of the passwords known from leaks (haveibeenpwned, k-anonymity).
        Password::defaults(function () {
            $rule = Password::min(10)->letters()->numbers()->max(128);

            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        // Each question to the AI assistant is a paid model call.
        RateLimiter::for('assistant', function (Request $request) {
            return Limit::perMinute(10)->by('user:'.$request->user()?->id);
        });

        // Reading a big spreadsheet is heavy: a few uploads a minute is plenty.
        // Each sync reads Google; a few a minute is plenty.
        RateLimiter::for('google-sync', function (Request $request) {
            return Limit::perMinute(6)->by('user:'.$request->user()?->id);
        });

        RateLimiter::for('data-imports', function (Request $request) {
            return Limit::perMinute(10)->by('user:'.$request->user()?->id);
        });

        // Feature flags belong to a business: Feature::active('market')
        // answers for the current tenant, not for the signed-in user.
        Feature::resolveScopeUsing(fn () => app(TenantContext::class)->current());

        foreach (FeatureFlag::cases() as $flag) {
            Feature::define($flag->value, fn () => $flag->default());
        }

        foreach (array_keys(RefreshSiteCache::TAGS) as $model) {
            $model::observe(RefreshSiteCache::class);
        }

        $this->app->terminating(fn () => $this->app->make(SiteRevalidator::class)->flush());

        Gate::define('manage-feature-flags', fn (User $user) => $user->is_super_admin);
        Gate::define('manage-tenants', fn (User $user) => $user->isPlatformStaff());
    }
}
