<?php

namespace App\Providers;

use App\Models\FileCheck;
use App\Models\SafeNavigationCheck;
use App\Models\ScanRun;
use App\Models\SecurityAsset;
use App\Models\User;
use App\Policies\FileCheckPolicy;
use App\Policies\SafeNavigationCheckPolicy;
use App\Policies\ScanRunPolicy;
use App\Policies\SecurityAssetPolicy;
use App\Policies\UserPolicy;
use App\Services\Admin\PlatformFeatureService;
use App\Services\AI\AgentChatProvider;
use App\Services\AI\OpenRouterChatProvider;
use App\Services\SafeNavigation\UrlReputationProviderRegistry;
use App\Services\SafeNavigation\VirusTotalProvider;
use App\Services\Scanner\CurlHttpProbe;
use App\Services\Scanner\DnsResolver;
use App\Services\Scanner\HttpProbe;
use App\Services\Scanner\NativeDnsResolver;
use App\Services\Scanner\WebsitePageProbe;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            UrlReputationProviderRegistry::class,
            fn ($app) => new UrlReputationProviderRegistry([
                $app->make(VirusTotalProvider::class),
            ]),
        );
        $this->app->bind(AgentChatProvider::class, OpenRouterChatProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(SecurityAsset::class, SecurityAssetPolicy::class);
        Gate::policy(ScanRun::class, ScanRunPolicy::class);
        Gate::policy(FileCheck::class, FileCheckPolicy::class);
        Gate::policy(SafeNavigationCheck::class, SafeNavigationCheckPolicy::class);
        $this->app->bind(DnsResolver::class, NativeDnsResolver::class);
        $this->app->bind(HttpProbe::class, CurlHttpProbe::class);
        $this->app->bind(WebsitePageProbe::class, CurlHttpProbe::class);

        View::composer(['layouts.app', 'welcome', 'dashboard'], function ($view): void {
            $features = collect(app(PlatformFeatureService::class)->all())
                ->keyBy('key')
                ->all();

            $view->with('platformFeatures', $features);
        });

        RateLimiter::for('login', function (Request $request) {
            $input = $request->input('email');
            $email = is_string($input) ? mb_strtolower(trim($input)) : '';

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(30)->by($request->ip()),
            ];
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('scanner-assets', function (Request $request) {
            return Limit::perHour(5)->by($request->user()?->getAuthIdentifier().'|'.$request->ip());
        });

        RateLimiter::for('scanner-verification', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->getAuthIdentifier().'|'.$request->ip());
        });

        RateLimiter::for('security-scans', function (Request $request) {
            return Limit::perMinute(3)->by($request->user()?->getAuthIdentifier().'|'.$request->ip());
        });

        RateLimiter::for('website-audits', function (Request $request) {
            return Limit::perMinute(2)->by($request->user()?->getAuthIdentifier().'|'.$request->ip());
        });

        RateLimiter::for('file-checks', function (Request $request) {
            return Limit::perHour(10)->by($request->user()?->getAuthIdentifier().'|'.$request->ip());
        });

        RateLimiter::for('virustotal-lookups', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier().'|'.$request->ip();

            return [
                Limit::perMinute(4)->by($key),
                Limit::perHour(100)->by($key),
            ];
        });

        RateLimiter::for('safe-navigation-checks', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->getAuthIdentifier().'|'.$request->ip());
        });

        RateLimiter::for('agent-messages', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier().'|'.$request->ip();

            return [
                Limit::perMinute(6)->by($key),
                Limit::perHour(30)->by($key),
            ];
        });

        RateLimiter::for('academy-quizzes', function (Request $request) {
            $key = $request->user()?->getAuthIdentifier().'|'.$request->ip();

            return [
                Limit::perMinute(10)->by($key),
                Limit::perHour(50)->by($key),
            ];
        });
    }
}
