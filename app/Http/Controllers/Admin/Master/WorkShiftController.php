<?php

namespace App\Http\Controllers\Admin\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWorkShiftRequest;
use App\Http\Requests\Admin\UpdateWorkShiftRequest;
use App\Models\Admin;
use App\Models\AdminShift;
use App\Support\Admin\AdminShell;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WorkShiftController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.master_work_shifts.read');

        /** @var Admin $authenticatedAdmin */
        $authenticatedAdmin = $request->user('admin');
        $workShifts = AdminShift::query()
            ->withCount(['admins' => fn ($query) => $query->visibleInOperations()])
            ->withExists('admins')
            ->orderBy('starts_at')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/master/WorkShifts', [
            ...$adminShell->props($authenticatedAdmin, 'Shift', 'master_work_shifts'),
            'workShifts' => $workShifts->map(fn (AdminShift $workShift): array => $this->workShiftData($workShift))->all(),
            'capabilities' => [
                'create' => Gate::allows('admin.master_work_shifts.create'),
                'update' => Gate::allows('admin.master_work_shifts.update'),
                'delete' => Gate::allows('admin.master_work_shifts.delete'),
            ],
        ]);
    }

    public function store(StoreWorkShiftRequest $request): RedirectResponse
    {
        AdminShift::query()->create($request->validated());

        return to_route('admin.master.work-shifts.index')->with('success', 'Shift berhasil ditambahkan.');
    }

    public function update(UpdateWorkShiftRequest $request, AdminShift $workShift): RedirectResponse
    {
        $workShift->update($request->validated());

        return to_route('admin.master.work-shifts.index')->with('success', 'Shift berhasil diperbarui.');
    }

    public function destroy(AdminShift $workShift): RedirectResponse
    {
        Gate::authorize('admin.master_work_shifts.delete');

        if ($workShift->admins()->exists()) {
            return back()->withErrors([
                'work_shift' => 'Shift sudah dipakai oleh admin sehingga tidak bisa dihapus. Nonaktifkan shift ini sebagai gantinya.',
            ]);
        }

        $workShift->delete();

        return to_route('admin.master.work-shifts.index')->with('success', 'Shift berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function workShiftData(AdminShift $workShift): array
    {
        return [
            'id' => $workShift->id,
            'key' => $workShift->key,
            'name' => $workShift->name,
            'starts_at' => $workShift->starts_at !== null ? mb_substr($workShift->starts_at, 0, 5) : null,
            'ends_at' => $workShift->ends_at !== null ? mb_substr($workShift->ends_at, 0, 5) : null,
            'is_active' => $workShift->is_active,
            'admin_count' => (int) ($workShift->admins_count ?? 0),
            'is_deletable' => ! (bool) ($workShift->admins_exists ?? false),
        ];
    }
}
