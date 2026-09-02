<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();

        $this->app->bind(StatefulGuard::class, fn (): StatefulGuard => match (request()->getHost()) {
            config('domains.admin') => Auth::guard('admin'),
            config('domains.member') => Auth::guard('member'),
            default => $this->app->runningInConsole()
                ? Auth::guard('admin')
                : throw new \LogicException('No authentication guard is configured for this host.'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('admin-login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->string(Fortify::username())).'|'.$request->ip());

            return $this->loginLimit('admin', $throttleKey);
        });

        RateLimiter::for('member-login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->string(Fortify::username())).'|'.$request->ip());

            return $this->loginLimit('member', $throttleKey);
        });
    }

    private function loginLimit(string $portal, string $throttleKey): Limit
    {
        return Limit::perMinute(5)
            ->by($portal.'|'.$throttleKey)
            ->response(function (Request $request, array $headers) use ($portal): Response {
                $response = Inertia::render('errors/TooManyRequests', [
                    'email' => $request->string(Fortify::username())->toString(),
                    'retryAfter' => max((int) ($headers['Retry-After'] ?? 60), 1),
                    'returnUrl' => route($portal.'.login', absolute: false),
                ])->toResponse($request);

                $response->setStatusCode(Response::HTTP_TOO_MANY_REQUESTS);
                $response->headers->add($headers);

                return $response;
            });
    }
}
