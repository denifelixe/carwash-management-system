<?php

use App\Support\Demo\Brand;
use App\Support\Demo\Catalog;
use App\Support\Demo\Customers;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

/**
 * The customer web application is an information and loyalty portal, so every
 * page must render read-only data and no mutating route may exist (BR-01…04).
 */
dataset('member pages', [
    'dashboard' => ['demo.member.dashboard', 'demo/member/Dashboard', ['stampHistory', 'rewards', 'promos']],
    'stamps' => ['demo.member.stamps', 'demo/member/Stamps', ['stampHistory', 'washHistory', 'rewards']],
    'services' => ['demo.member.services', 'demo/member/Services', ['services', 'categories']],
    'rewards' => ['demo.member.rewards', 'demo/member/Rewards', ['rewards', 'categories', 'vouchers']],
    'profile' => ['demo.member.profile', 'demo/member/Profile', ['washHistory', 'vouchers']],
]);

test('each portal page renders with the member and its own props', function (string $routeName, string $component, array $props) {
    $this->get(route($routeName))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) use ($component, $props) {
            $page->component($component)
                ->has('brand')
                ->has('member')
                ->has('notifications');

            foreach ($props as $prop) {
                $page->has($prop);
            }
        });
})->with('member pages');

test('the login and register screens are reachable without a session', function () {
    $this->get(route('demo.member.login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('demo/auth/MemberLogin'));

    $this->get(route('demo.member.register'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('demo/auth/MemberRegister'));
});

test('the portal exposes no mutating routes', function () {
    $mutating = collect(Route::getRoutes())
        ->filter(fn ($route): bool => str_starts_with((string) $route->getName(), 'demo.member.'))
        ->reject(fn ($route): bool => array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) === [])
        ->map(fn ($route): string => (string) $route->getName())
        ->values()
        ->all();

    expect($mutating)->toBeEmpty();
});

test('the member stamp balance never exceeds the card target', function () {
    $member = Customers::member();
    $target = Brand::identity()['stampTarget'];

    expect($member['stamps'])->toBeLessThanOrEqual($target)
        ->and($member['lifetimeStamps'])->toBeGreaterThanOrEqual($member['stamps']);
});

test('the portal only shows rewards that are active', function () {
    $this->get(route('demo.member.rewards'))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page) {
            /** @var list<array{status: string, requiredStamps: int}> $rewards */
            $rewards = $page->toArray()['props']['rewards'];

            expect($rewards)->not->toBeEmpty();

            foreach ($rewards as $reward) {
                expect($reward['requiredStamps'])->toBeGreaterThan(0);
            }
        });
});

test('every reward states the stamps required to claim it', function () {
    foreach (Catalog::rewards() as $reward) {
        expect($reward['requiredStamps'])->toBeGreaterThan(0)
            ->and($reward['name'])->not->toBeEmpty()
            ->and($reward['description'])->not->toBeEmpty();
    }
});

test('the service catalog is shared by the portal and the POS', function () {
    $this->get(route('demo.member.services'))
        ->assertOk()
        ->assertInertia(
            fn (AssertableInertia $page) => $page
                ->has('services', count(Catalog::services()))
        );
});
