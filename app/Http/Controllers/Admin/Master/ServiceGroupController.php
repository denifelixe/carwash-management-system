<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceGroupRequest;
use App\Http\Requests\Admin\UpdateServiceGroupRequest;
use App\Models\ServiceGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class ServiceGroupController extends Controller
{
    public function store(StoreServiceGroupRequest $request): RedirectResponse
    {
        ServiceGroup::query()->create($request->validated());

        return to_route('admin.master.services.index')->with('success', 'Group layanan berhasil ditambahkan.');
    }

    public function update(UpdateServiceGroupRequest $request, ServiceGroup $serviceGroup): RedirectResponse
    {
        $serviceGroup->update($request->validated());

        return to_route('admin.master.services.index')->with('success', 'Group layanan berhasil diperbarui.');
    }

    public function destroy(ServiceGroup $serviceGroup): RedirectResponse
    {
        Gate::authorize('admin.master_services.delete');
        $serviceGroup->delete();

        return to_route('admin.master.services.index')->with('success', 'Group layanan berhasil dihapus.');
    }
}
