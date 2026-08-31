<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderServicesRequest;
use App\Http\Requests\Admin\StoreServiceRequest;
use App\Http\Requests\Admin\UpdateServiceRequest;
use App\Models\Admin;
use App\Models\Service;
use App\Support\Admin\AdminShell;
use App\Support\Admin\ServiceIcons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.master_services.read');

        /** @var Admin $authenticatedAdmin */
        $authenticatedAdmin = $request->user('admin');
        $services = Service::query()
            ->withCount('orders')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('admin/master/Services', [
            ...$adminShell->props($authenticatedAdmin, 'Layanan', 'master_services'),
            'services' => $services->map(fn (Service $service): array => $this->serviceData($service))->all(),
            'categories' => $services->pluck('category')->unique()->sort()->values()->all(),
            'icons' => ServiceIcons::options(),
            'capabilities' => [
                'create' => Gate::allows('admin.master_services.create'),
                'update' => Gate::allows('admin.master_services.update'),
                'delete' => Gate::allows('admin.master_services.delete'),
            ],
        ]);
    }

    public function store(StoreServiceRequest $request): RedirectResponse
    {
        Service::query()->create([
            ...$request->validated(),
            'sort_order' => (int) Service::query()->max('sort_order') + 1,
        ]);

        return to_route('admin.master.services.index')->with('success', 'Layanan berhasil ditambahkan.');
    }

    /**
     * Persist the catalog order the operator dragged into place. The request
     * only passes if it carries every service, so the numbering stays dense.
     */
    public function updateOrder(ReorderServicesRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated()['ids'];

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $index => $id) {
                Service::query()->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Urutan layanan berhasil disimpan.']);

        return to_route('admin.master.services.index');
    }

    public function update(UpdateServiceRequest $request, Service $service): RedirectResponse
    {
        $service->update($request->validated());

        return to_route('admin.master.services.index')->with('success', 'Layanan berhasil diperbarui.');
    }

    public function destroy(Service $service): RedirectResponse
    {
        Gate::authorize('admin.master_services.delete');

        if ($service->orders()->exists()) {
            return back()->withErrors([
                'service' => 'Layanan sudah dipakai pada order sehingga tidak bisa dihapus. Nonaktifkan layanan ini sebagai gantinya.',
            ]);
        }

        $service->delete();

        return to_route('admin.master.services.index')->with('success', 'Layanan berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceData(Service $service): array
    {
        return [
            'id' => $service->id,
            'name' => $service->name,
            'category' => $service->category,
            'price' => (int) $service->price,
            'stamps' => (int) $service->stamps,
            'icon' => $service->icon,
            'description' => $service->description ?? '',
            'is_popular' => $service->is_popular,
            'is_active' => $service->is_active,
            'sort_order' => (int) $service->sort_order,
            'order_count' => (int) ($service->orders_count ?? 0),
        ];
    }
}
