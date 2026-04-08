<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Models\Automation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AutomationController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        $automations = Automation::orderBy('position')->get();

        return $this->renderer->render('Escalated/Admin/Automations/Index', [
            'automations' => $automations,
        ]);
    }

    public function create(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Automations/Form');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'conditions' => 'required|array|min:1',
            'actions' => 'required|array|min:1',
            'active' => 'boolean',
        ]);

        Automation::create([
            'name' => $request->input('name'),
            'conditions' => $request->input('conditions'),
            'actions' => $request->input('actions'),
            'active' => $request->boolean('active', true),
            'position' => Automation::max('position') + 1,
        ]);

        return redirect()->route('escalated.admin.automations.index')
            ->with('success', 'Automation created.');
    }

    public function edit(Automation $automation): mixed
    {
        return $this->renderer->render('Escalated/Admin/Automations/Form', [
            'automation' => $automation,
        ]);
    }

    public function update(Request $request, Automation $automation): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'conditions' => 'required|array|min:1',
            'actions' => 'required|array|min:1',
            'active' => 'boolean',
        ]);

        $automation->update([
            'name' => $request->input('name'),
            'conditions' => $request->input('conditions'),
            'actions' => $request->input('actions'),
            'active' => $request->boolean('active', true),
        ]);

        return redirect()->route('escalated.admin.automations.index')
            ->with('success', 'Automation updated.');
    }

    public function destroy(Automation $automation): RedirectResponse
    {
        $automation->delete();

        return redirect()->route('escalated.admin.automations.index')
            ->with('success', 'Automation deleted.');
    }
}
