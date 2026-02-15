<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Http\Requests\StoreMacroRequest;
use Escalated\Laravel\Models\Macro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class MacroController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Escalated/Admin/Macros/Index', [
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
