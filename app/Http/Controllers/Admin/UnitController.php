<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CollegeRequest;
use App\Http\Requests\Admin\DepartmentRequest;
use App\Http\Requests\UnitFilterRequest;
use App\Models\College;
use App\Models\Department;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Colleges, offices and their departments. Units are deactivated, never deleted, so records keep their unit. */
class UnitController extends Controller
{
    public function index(UnitFilterRequest $request): View
    {

        return view('admin.units.index', [
            'colleges' => College::withCount(['departments', 'users'])
                ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%"))
                ->orderBy('kind')->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(CollegeRequest $request): RedirectResponse
    {
        $college = College::create($request->validated());
        Audit::log('unit.created', "{$request->user()->name} added the ".($college->isOffice() ? 'office' : 'college')." {$college->name}.", $college);

        return redirect()->route('admin.units.edit', $college)->with('status', "{$college->name} was added. Add its departments below, if it has any.");
    }

    public function edit(College $college): View
    {
        return view('admin.units.edit', [
            'college' => $college,
            'departments' => $college->departments()->withCount('users')->get(),
        ]);
    }

    public function update(CollegeRequest $request, College $college): RedirectResponse
    {
        $college->fill($request->validated());
        $changes = $college->getDirty();
        $college->save();

        if ($changes) {
            Audit::log('unit.updated', "{$request->user()->name} updated {$college->name}.", $college, $changes);
        }

        return back()->with('status', "{$college->name} was saved.");
    }

    public function toggle(Request $request, College $college): RedirectResponse
    {
        $college->update(['active' => ! $college->active]);
        Audit::log($college->active ? 'unit.reactivated' : 'unit.deactivated', "{$request->user()->name} ".($college->active ? 'reactivated' : 'deactivated')." {$college->name}.", $college);

        return back()->with('status', $college->active
            ? "{$college->name} is active again."
            : "{$college->name} is inactive. It can no longer be chosen for accounts; existing accounts and records keep it.");
    }

    public function storeDepartment(DepartmentRequest $request, College $college): RedirectResponse
    {
        $department = $college->departments()->create($request->validated());
        Audit::log('unit.created', "{$request->user()->name} added the department {$department->name} to {$college->name}.", $department);

        return back()->with('status', "{$department->name} was added to {$college->name}.");
    }

    public function updateDepartment(DepartmentRequest $request, College $college, Department $department): RedirectResponse
    {
        $old = $department->name;
        $department->update($request->validated());
        if ($old !== $department->name) {
            Audit::log('unit.updated', "{$request->user()->name} renamed the department {$old} to {$department->name} ({$college->name}).", $department);
        }

        return back()->with('status', "{$department->name} was saved.");
    }

    public function toggleDepartment(Request $request, College $college, Department $department): RedirectResponse
    {
        $department->update(['active' => ! $department->active]);
        Audit::log($department->active ? 'unit.reactivated' : 'unit.deactivated', "{$request->user()->name} ".($department->active ? 'reactivated' : 'deactivated')." the department {$department->name} ({$college->name}).", $department);

        return back()->with('status', "{$department->name} is ".($department->active ? 'active again.' : 'inactive.'));
    }
}
