<?php

use App\Models\Service;
use App\Models\ServiceVariation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class);

test('the MySQL catalog import creates one regular wash with two stable variation rows', function () {
    if (DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('SQL impor MySQL diuji terpisah dengan koneksi MySQL dan database uji terisolasi.');
    }

    $originalConnection = DB::connection();
    $databaseName = 'regular_wash_import_test_'.bin2hex(random_bytes(8));
    $originalConnection->statement("CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    try {
        config(['database.connections.catalog_import_test' => [
            ...$originalConnection->getConfig(), 'name' => 'catalog_import_test', 'database' => $databaseName, 'url' => null,
        ]]);
        DB::setDefaultConnection('catalog_import_test');
        $connection = DB::connection();

        Schema::create('services', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('category');
            $table->string('category_group')->default('');
            $table->json('variations')->nullable();
            $table->unsignedSmallInteger('stamps');
            $table->string('icon');
            $table->text('description')->nullable();
            $table->boolean('is_popular');
            $table->boolean('is_active');
            $table->unsignedInteger('sort_order');
            $table->datetimes();
        });
        Schema::create('service_variations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('service_id');
            $table->json('variations')->nullable();
            $table->unsignedBigInteger('price');
            $table->boolean('is_active');
            $table->datetimes();
        });
        $sql = file_get_contents(database_path('sql/services_speedtuner_cibinong.sql'));
        $connection->unprepared($sql);

        $regular = Service::query()->where('name', 'Regular Wash')->sole();
        $variations = $regular->serviceVariations()->orderBy('id')->get();

        expect(Service::query()->where('name', 'Regular Wash (Large)')->exists())->toBeFalse()
            ->and($regular->variations)->toBe(['Ukuran' => ['Standard', 'Large']])
            ->and($regular->category)->toBe('Cuci Mobil')
            ->and($regular->stamps)->toBe(1)
            ->and($regular->description)->toBe('Cuci reguler standar. Termasuk garansi hujan 24 jam.')
            ->and($variations->pluck('variations')->all())->toEqualCanonicalizing([['Ukuran' => 'Standard'], ['Ukuran' => 'Large']])
            ->and($variations->mapWithKeys(fn (ServiceVariation $variation): array => [$variation->variations['Ukuran'] => $variation->price])->all())->toEqual(['Standard' => 60000, 'Large' => 70000]);

        $otherSizedService = Service::query()->where('name', 'Coating Lite')->sole();
        expect($otherSizedService->variations)->toBe(['Ukuran' => ['Small', 'Medium', 'Large', 'Extra Large']])
            ->and($otherSizedService->serviceVariations()->count())->toBe(4);

        $serviceCount = $connection->table('services')->count();
        $variationCount = $connection->table('service_variations')->count();
        $variationIds = $variations->mapWithKeys(fn (ServiceVariation $variation): array => [$variation->variations['Ukuran'] => $variation->id])->all();

        $connection->unprepared($sql);

        expect($connection->table('services')->count())->toBe($serviceCount)
            ->and($connection->table('service_variations')->count())->toBe($variationCount)
            ->and(Service::query()->where('name', 'Regular Wash')->sole()->id)->toBe($regular->id)
            ->and($regular->serviceVariations()->get()->mapWithKeys(fn (ServiceVariation $variation): array => [$variation->variations['Ukuran'] => $variation->id])->all())->toEqual($variationIds);
    } finally {
        DB::purge('catalog_import_test');
        DB::setDefaultConnection($originalConnection->getName());
        $originalConnection->statement("DROP DATABASE `{$databaseName}`");
    }
});
