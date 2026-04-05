<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Models\CustomObject;
use Escalated\Laravel\Models\CustomObjectRecord;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;

class CustomObjectController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}
    public function index()
    {
        $objects = CustomObject::withCount('records')
            ->orderBy('name')
            ->get();

        return $this->renderer->render('Escalated/Admin/CustomObjects/Index', [
            'objects' => $objects,
        ]);
    }

    public function create()
    {
        return $this->renderer->render('Escalated/Admin/CustomObjects/Form');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:'.CustomObject::class.',slug'],
            'fields_schema' => ['required', 'array', 'min:1'],
            'fields_schema.*.name' => ['required', 'string', 'max:100'],
            'fields_schema.*.type' => ['required', 'string', 'in:text,number,select,date,lookup'],
            'fields_schema.*.required' => ['sometimes', 'boolean'],
            'fields_schema.*.options' => ['sometimes', 'array'],
        ]);

        CustomObject::create($validated);

        return redirect()->route('escalated.admin.custom-objects.index')
            ->with('success', 'Custom object created.');
    }

    public function edit(CustomObject $customObject)
    {
        return $this->renderer->render('Escalated/Admin/CustomObjects/Form', [
            'object' => $customObject,
        ]);
    }

    public function update(Request $request, CustomObject $customObject)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'alpha_dash', 'unique:'.CustomObject::class.',slug,'.$customObject->id],
            'fields_schema' => ['required', 'array', 'min:1'],
            'fields_schema.*.name' => ['required', 'string', 'max:100'],
            'fields_schema.*.type' => ['required', 'string', 'in:text,number,select,date,lookup'],
            'fields_schema.*.required' => ['sometimes', 'boolean'],
            'fields_schema.*.options' => ['sometimes', 'array'],
        ]);

        $customObject->update($validated);

        return redirect()->route('escalated.admin.custom-objects.index')
            ->with('success', 'Custom object updated.');
    }

    public function destroy(CustomObject $customObject)
    {
        $customObject->delete();

        return redirect()->route('escalated.admin.custom-objects.index')
            ->with('success', 'Custom object deleted.');
    }

    public function records(CustomObject $customObject)
    {
        $records = $customObject->records()->orderByDesc('id')->get();

        return $this->renderer->render('Escalated/Admin/CustomObjects/Records', [
            'object' => $customObject,
            'records' => $records,
        ]);
    }

    public function storeRecord(Request $request, CustomObject $customObject)
    {
        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        $customObject->records()->create([
            'data' => $validated['data'],
        ]);

        return redirect()->back()->with('success', 'Record created.');
    }

    public function updateRecord(Request $request, CustomObject $customObject, CustomObjectRecord $record)
    {
        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        $record->update(['data' => $validated['data']]);

        return redirect()->back()->with('success', 'Record updated.');
    }

    public function destroyRecord(CustomObject $customObject, CustomObjectRecord $record)
    {
        $record->delete();

        return redirect()->back()->with('success', 'Record deleted.');
    }
}
