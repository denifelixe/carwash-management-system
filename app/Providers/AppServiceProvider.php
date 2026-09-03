<?php

namespace App\Providers;

use App\Models\Admin;
use App\Support\AppSettings;
use App\Support\Auth\ActiveUserProvider;
use App\Support\DangerousKeyManager;
use App\Support\Demo\Brand;
use App\Support\Session\DatabaseSessionHandler;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as IlluminateView;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            DangerousKeyManager::class,
            fn (Application $app): DangerousKeyManager => new DangerousKeyManager($app->environmentFilePath()),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        AppSettings::applyTimezone();
        AppSettings::applyBranding();

        $this->configureDatabaseSessions();
        $this->configureAuthentication();
        $this->configureDefaults();

        Gate::before(fn (mixed $user): ?bool => $user instanceof Admin && $user->is_owner
            ? true
            : null);

        foreach (['users_and_roles', 'orders', 'pos', 'bookings', 'finance', 'members', 'leads', 'master_services', 'master_work_shifts', 'master_timezone', 'master_app_settings', 'master_receipt'] as $moduleKey) {
            foreach (['create', 'read', 'update', 'delete'] as $permission) {
                Gate::define(
                    "admin.{$moduleKey}.{$permission}",
                    fn (Admin $admin): bool => $admin->hasModulePermission($moduleKey, $permission),
                );
            }
        }

        View::composer('app', fn (IlluminateView $view): IlluminateView => $view->with('meta', Brand::meta()));
    }

    /**
     * Store sessions without coupling them to an implicit authentication guard.
     */
    protected function configureDatabaseSessions(): void
    {
        Session::extend('database', function (Application $app): DatabaseSessionHandler {
            $connectionName = config('session.connection');

            return new DatabaseSessionHandler(
                $app->make(DatabaseManager::class)->connection(
                    is_string($connectionName) ? $connectionName : null,
                ),
                (string) config('session.table'),
                (int) config('session.lifetime'),
                $app,
            );
        });
    }

    /**
     * Refuse deactivated accounts on every guard retrieval path, not only at login.
     */
    protected function configureAuthentication(): void
    {
        Auth::provider(
            'active_eloquent',
            /** @param array{model: class-string<Model>} $config */
            fn (Application $app, array $config): ActiveUserProvider => new ActiveUserProvider(
                $app->make(Hasher::class),
                $config['model'],
            ),
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(1));
    }
}
