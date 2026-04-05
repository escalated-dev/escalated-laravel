<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\CustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;

class CustomFieldController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        return $this->renderer->render('Escalated/Admin/CustomFields/Index', [
            'fields' => CustomField::orderBy('position')->get(),
        ]);
    }

    public function create(): mixed
    {
        return $this->renderer->render('Escalated/Admin/CustomFields/Form', [
            'contexts' => ['ticket', 'user', 'organization'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:text,textarea,select,multi_select,checkbox,date,number',
            'context' => 'required|string|in:ticket,user,organization',
            'options' => 'nullable|array',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'validation_rules' => 'nullable|array',
            'position' => 'integer',
            'active' => 'boolean',
        ]);

        CustomField::create($validated);

        return redirect()->route('escalated.admin.custom-fields.index')
            ->with('success', 'Custom field created.');
    }

    public function edit(CustomField $customField): mixed
    {
        return $this->renderer->render('Escalated/Admin/CustomFields/Form', [
            'field' => $customField,
            'contexts' => ['ticket', 'user', 'organization'],
        ]);
    }

    public function update(CustomField $customField, Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:text,textarea,select,multi_select,checkbox,date,number',
            'context' => 'required|string|in:ticket,user,organization',
            'options' => 'nullable|array',
            'required' => 'boolean',
            'placeholder' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'validation_rules' => 'nullable|array',
            'position' => 'integer',
            'active' => 'boolean',
        ]);

        $customField->update($validated);

        return redirect()->route('escalated.admin.custom-fields.index')
            ->with('success', 'Custom field updated.');
    }

    public function destroy(CustomField $customField): RedirectResponse
    {
        $customField->delete();

        return redirect()->route('escalated.admin.custom-fields.index')
            ->with('success', 'Custom field deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $request->validate([
            'positions' => 'required|array',
            'positions.*.id' => 'required|integer|exists:'.config('escalated.table_prefix', 'escalated_').'custom_fields,id',
            'positions.*.position' => 'required|integer',
        ]);

        foreach ($request->input('positions') as $item) {
            CustomField::where('id', $item['id'])->update(['position' => $item['position']]);
        }

        return back()->with('success', 'Fields reordered.');
    }
}
