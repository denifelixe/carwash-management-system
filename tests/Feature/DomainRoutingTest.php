<?php

use App\Models\Admin;
use App\Models\Member;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * @return array<string, string>
 */
function environmentForApplicationType(string $applicationType): array
{
    $cacheSuffix = Str::lower($applicationType);

    return [
        'APP_CONFIG_CACHE' => base_path("bootstrap/cache/config-{$cacheSuffix}-test.php"),
        'APP_ROUTES_CACHE' => base_path("bootstrap/cache/routes-{$cacheSuffix}-test.php"),
        'APP_TYPE' => $applicationType,
        'APP_URL' => 'https://carwash-demo.zenadigital.id',
        'DEMO_DOMAIN' => 'ignored-demo.zenadigital.id',
        'DEMO_URL' => 'https://ignored-demo.zenadigital.id',
    ];
}

/**
 * @return Collection<int, array<string, mixed>>
 */
function routesForApplicationType(string $applicationType): Collection
{
    $result = Process::path(base_path())
        ->env(environmentForApplicationType($applicationType))
        ->run([PHP_BINARY, 'artisan', 'route:list', '--json', '--except-vendor', '--no-interaction']);

    expect($result->successful())->toBeTrue($result->errorOutput());

    return collect(json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR));
}

test('application domains are derived from the configured application url', function () {
    expect(config('app.type'))->toBe('ALL')
        ->and(config('app.generate_all_routes'))->toBeFalse()
        ->and(config('domains.app'))->toBe('carwash-management-system.test')
        ->and(config('domains.admin'))->toBe('admin.carwash-management-system.test')
        ->and(config('domains.member'))->toBe('member.carwash-management-system.test')
        ->and(config('domains.demo'))->toBe('demo.carwash-management-system.test')
        ->and(config('domains.admin_url'))->toBe('https://admin.carwash-management-system.test')
        ->and(config('domains.member_url'))->toBe('https://member.carwash-management-system.test')
        ->and(config('domains.demo_url'))->toBe('https://demo.carwash-management-system.test')
        ->and(config('fortify.portals.admin.guard'))->toBe('admin')
        ->and(config('fortify.portals.member.guard'))->toBe('member');
});

test('demo mode only registers demo routes on the application url', function () {
    $routes = routesForApplicationType('DEMO');
    $routeNames = $routes->pluck('name');
    $demoUrl = Process::path(base_path())
        ->env(environmentForApplicationType('DEMO'))
        ->run([PHP_BINARY, 'artisan', 'config:show', 'domains.demo_url', '--no-interaction']);

    expect($demoUrl->successful())->toBeTrue($demoUrl->errorOutput())
        ->and($demoUrl->output())->toContain('https://carwash-demo.zenadigital.id')
        ->not->toContain('ignored-demo.zenadigital.id')
        ->and($routeNames)->toContain('demo.home')
        ->not->toContain('home', 'admin.login', 'member.login')
        ->and($routes->firstWhere('name', 'demo.home')['domain'])
        ->toBe('carwash-demo.zenadigital.id');
});

test('live mode only registers live routes', function () {
    $routeNames = routesForApplicationType('LIVE')->pluck('name');

    expect($routeNames)->toContain('home', 'admin.login', 'member.login')
        ->and($routeNames->filter(fn (?string $name): bool => Str::startsWith((string) $name, 'demo.')))
        ->toBeEmpty();
});

test('staging mode only registers live routes', function () {
    $routeNames = routesForApplicationType('STAGING')->pluck('name');

    expect($routeNames)->toContain('home', 'admin.login', 'member.login')
        ->and($routeNames->filter(fn (?string $name): bool => Str::startsWith((string) $name, 'demo.')))
        ->toBeEmpty();
});

test('all mode registers demo and live routes', function () {
    $routeNames = routesForApplicationType('ALL')->pluck('name');

    expect($routeNames)->toContain('demo.home', 'home', 'admin.login', 'member.login');
});

test('an invalid application type prevents the application from booting', function () {
    $result = Process::path(base_path())
        ->env([
            'APP_CONFIG_CACHE' => base_path('bootstrap/cache/config-invalid-test.php'),
            'APP_TYPE' => 'demo',
        ])
        ->run([PHP_BINARY, 'artisan', 'about', '--no-interaction']);

    expect($result->failed())->toBeTrue()
        ->and($result->output().$result->errorOutput())
        ->toContain('APP_TYPE must be one of: DEMO, LIVE, STAGING, ALL.');
});

test('staging pages show a fixed warning banner', function () {
    config(['app.type' => 'STAGING']);

    $this->get(route('admin.login'))
        ->assertOk()
        ->assertSee('STAGING ENVIRONMENT')
        ->assertSee('app-staging', false)
        ->assertSee('staging-banner fixed', false);
});

test('non-staging pages do not show the warning banner', function () {
    config(['app.type' => 'LIVE']);

    $this->get(route('admin.login'))
        ->assertOk()
        ->assertDontSee('STAGING ENVIRONMENT')
        ->assertDontSee('app-staging', false);
});

test('the main domain sends visitors to the member portal', function () {
    $this->get('https://carwash-management-system.test/')
        ->assertRedirect(route('member.home'));
});

test('the admin domain only serves admin authentication', function () {
    config(['app.member_portal_enabled' => true]);

    $this->get('https://admin.carwash-management-system.test/')
        ->assertRedirect(route('admin.login'));

    $this->get(route('admin.login'))->assertOk();

    $this->get('https://carwash-management-system.test/login')->assertNotFound();
    $this->get('https://member.carwash-management-system.test/login')->assertOk();
});

test('authenticated staff entering the admin domain are sent to their dashboard', function () {
    $admin = Admin::factory()->create();

    $this->actingAs($admin, 'admin')
        ->get('https://admin.carwash-management-system.test/')
        ->assertRedirect(route('admin.dashboard'));
});

test('the member domain serves member authentication without exposing admin routes', function () {
    config(['app.member_portal_enabled' => true]);

    $this->get('https://member.carwash-management-system.test/')
        ->assertRedirect(route('member.login'));

    $this->get('https://member.carwash-management-system.test/dashboard')
        ->assertRedirect(route('member.login'));

    $member = Member::factory()->create();

    $this->actingAs($member, 'member')
        ->get('https://member.carwash-management-system.test/')
        ->assertRedirect(route('member.dashboard'));
});

test('named routes generate urls on their intended domains', function () {
    expect(parse_url(route('home'), PHP_URL_HOST))->toBe('carwash-management-system.test')
        ->and(parse_url(route('admin.home'), PHP_URL_HOST))->toBe('admin.carwash-management-system.test')
        ->and(parse_url(route('admin.login'), PHP_URL_HOST))->toBe('admin.carwash-management-system.test')
        ->and(parse_url(route('admin.dashboard'), PHP_URL_HOST))->toBe('admin.carwash-management-system.test')
        ->and(parse_url(route('member.home'), PHP_URL_HOST))->toBe('member.carwash-management-system.test')
        ->and(parse_url(route('member.login'), PHP_URL_HOST))->toBe('member.carwash-management-system.test')
        ->and(parse_url(route('member.dashboard'), PHP_URL_HOST))->toBe('member.carwash-management-system.test')
        ->and(parse_url(route('demo.home'), PHP_URL_HOST))->toBe('demo.carwash-management-system.test');
});
