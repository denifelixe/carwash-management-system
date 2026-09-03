<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLeadRequest;
use App\Http\Requests\Admin\UpdateLeadRequest;
use App\Http\Requests\Admin\UpdateLeadStatusRequest;
use App\Models\Admin;
use App\Models\Lead;
use App\Support\Admin\AdminShell;
use App\Support\Admin\LeadQueries;
use App\Support\Admin\OrderPresenter;
use App\Support\Admin\Paginated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request, AdminShell $adminShell): Response
    {
        Gate::authorize('admin.leads.read');

        /** @var Admin $admin */
        $admin = $request->user('admin');
        $filters = LeadQueries::filters($request);

        return Inertia::render('admin/Leads', [
            ...$adminShell->props($admin, 'Leads', 'leads'),
            'leads' => fn (): array => Paginated::fromPaginator(
                LeadQueries::page($filters),
                fn (Lead $lead): array => OrderPresenter::lead($lead),
            ),
            'stats' => fn (): array => LeadQueries::stats(),
            'leadDetail' => fn (): ?array => LeadQueries::detail($request->integer('lead') ?: null),
            'filters' => $filters,
            'statusFilters' => LeadQueries::STATUS_FILTERS,
            'conversionFilters' => LeadQueries::CONVERSION_FILTERS,
            'capabilities' => [
                'create' => Gate::allows('admin.leads.create'),
                'update' => Gate::allows('admin.leads.update'),
            ],
        ]);
    }

    public function store(StoreLeadRequest $request): RedirectResponse
    {
        $lead = Lead::query()->create($request->lead());

        return to_route('admin.leads.index', ['lead' => $lead->id])
            ->with('success', 'Lead berhasil ditambahkan.');
    }

    public function update(UpdateLeadRequest $request, Lead $lead): RedirectResponse
    {
        $lead->update($request->lead());

        return back()->with('success', 'Data lead berhasil diperbarui.');
    }

    public function updateStatus(UpdateLeadStatusRequest $request, Lead $lead): RedirectResponse
    {
        $lead->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Status lead berhasil diperbarui.');
    }
}
