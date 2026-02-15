<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Http\Requests\StoreEscalationRuleRequest;
use Escalated\Laravel\Models\EscalationRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class EscalationRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Escalated/Admin/EscalationRules/Index', [
            'rules' => EscalationRule::orderBy('order')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Escalated/Admin/EscalationRules/Form');
    }

    public function store(StoreEscalationRuleRequest $request): RedirectResponse
    {
        EscalationRule::create($request->validated());

        return redirect()->route('escalated.admin.escalation-rules.index')->with('success', __('escalated::messages.escalation_rule.created'));
    }

    public function edit(EscalationRule $escalationRule): Response
    {
        return Inertia::render('Escalated/Admin/EscalationRules/Form', ['rule' => $escalationRule]);
    }

    public function update(EscalationRule $escalationRule, StoreEscalationRuleRequest $request): RedirectResponse
    {
        $escalationRule->update($request->validated());

        return redirect()->route('escalated.admin.escalation-rules.index')->with('success', __('escalated::messages.escalation_rule.updated'));
    }

    public function destroy(EscalationRule $escalationRule): RedirectResponse
    {
        $escalationRule->delete();

        return redirect()->route('escalated.admin.escalation-rules.index')->with('success', __('escalated::messages.escalation_rule.deleted'));
    }
}
