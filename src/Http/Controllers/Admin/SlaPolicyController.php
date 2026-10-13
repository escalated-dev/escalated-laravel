<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Http\Requests\StoreSlaPolicyRequest;
use Escalated\Laravel\Models\Department;
use Escalated\Laravel\Models\SlaPolicy;
use Escalated\Laravel\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

class SlaPolicyController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        return $this->renderer->render('Escalated/Admin/SlaPolicies/Index', [
            'policies' => SlaPolicy::withCount('tickets')->get(),
        ]);
    }

    public function create(): mixed
    {
        return $this->renderer->render('Escalated/Admin/SlaPolicies/Form', $this->formOptions());
    }

    public function store(StoreSlaPolicyRequest $request): RedirectResponse
    {
        SlaPolicy::create($request->validated());

        return redirect()->route('escalated.admin.sla-policies.index')->with('success', __('escalated::messages.sla_policy.created'));
    }

    public function edit(SlaPolicy $slaPolicy): mixed
    {
        return $this->renderer->render('Escalated/Admin/SlaPolicies/Form', [
            'policy' => $slaPolicy,
        ] + $this->formOptions());
    }

    private function formOptions(): array
    {
        return [
            'priorities' => config('escalated.priorities'),
            'targeted' => config('escalated.sla.targeted', false),
            'departments' => Department::active()->get(['id', 'name']),
            'ticketTypes' => Ticket::TYPES,
        ];
    }

    public function update(SlaPolicy $slaPolicy, StoreSlaPolicyRequest $request): RedirectResponse
    {
        $slaPolicy->update($request->validated());

        return redirect()->route('escalated.admin.sla-policies.index')->with('success', __('escalated::messages.sla_policy.updated'));
    }

    public function destroy(SlaPolicy $slaPolicy): RedirectResponse
    {
        $slaPolicy->delete();

        return redirect()->route('escalated.admin.sla-policies.index')->with('success', __('escalated::messages.sla_policy.deleted'));
    }
}
