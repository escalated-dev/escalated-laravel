<?php

namespace Escalated\Laravel\Http\Controllers\Admin;

use Escalated\Laravel\Http\Requests\StoreDepartmentRequest;
use Escalated\Laravel\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Escalated\Laravel\Contracts\EscalatedUiRenderer;

class DepartmentController extends Controller
{
    public function __construct(
        protected EscalatedUiRenderer $renderer,
    ) {}

    public function index(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Departments/Index', [
            'departments' => Department::withCount('tickets', 'agents')->get(),
        ]);
    }

    public function create(): mixed
    {
        return $this->renderer->render('Escalated/Admin/Departments/Form');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        Department::create($request->validated());

        return redirect()->route('escalated.admin.departments.index')->with('success', __('escalated::messages.department.created'));
    }

    public function edit(Department $department): mixed
    {
        $department->load('agents');

        return $this->renderer->render('Escalated/Admin/Departments/Form', ['department' => $department]);
    }

    public function update(Department $department, StoreDepartmentRequest $request): RedirectResponse
    {
        $department->update($request->validated());

        return redirect()->route('escalated.admin.departments.index')->with('success', __('escalated::messages.department.updated'));
    }

    public function destroy(Department $department): RedirectResponse
    {
        $department->delete();

        return redirect()->route('escalated.admin.departments.index')->with('success', __('escalated::messages.department.deleted'));
    }
}
