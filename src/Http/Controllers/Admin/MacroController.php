<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Contracts\EscalatedUiRenderer;
use Escalated\Laravel\Http\Requests\StoreMacroRequest;
use Escalated\Laravel\Models\Macro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MacroController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(Request $request): mixed
    {
        return $this->renderer->render('Escalated/Admin/Macros/Index', [
            'macros' => Macro::orderBy('order')->get(),
        ]);
    }

    public function store(StoreMacroRequest $request): RedirectResponse
    {
        Macro::create([
            ...$request->validated(),
            'created_by' => $request->user()->getKey(),
        ]);

        return back()->with('success', __('escalated::messages.macro.created'));
    }

    public function update(Macro $macro, StoreMacroRequest $request): RedirectResponse
    {
        $macro->update($request->validated());

        return back()->with('success', __('escalated::messages.macro.updated'));
    }

    public function destroy(Macro $macro): RedirectResponse
    {
        $macro->delete();

        return back()->with('success', __('escalated::messages.macro.deleted'));
    }
}
