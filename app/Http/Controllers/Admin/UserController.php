<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserFilterRequest;
use App\Http\Requests\Admin\UserRequest;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use App\Services\Accounts;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private Accounts $accounts) {}

    public function index(UserFilterRequest $request): View
    {
        $filters = $request->validated();

        return view('admin.users.index', [
            'users' => User::with(['college', 'department'])
                ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
                ->when($filters['college'] ?? null, fn ($q, $id) => $q->where('college_id', $id))
                ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('active', $status === 'active'))
                ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('employee_id', 'like', "%{$term}%")))
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'filters' => $filters,
            'colleges' => College::orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User(['active' => true]), ...$this->options()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->accounts->create($request->userData(), $request->user());

        return redirect()->route('admin.users.index')
            ->with('status', "{$user->name}'s account was created. A set-password email was sent to {$user->email}.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, ...$this->options()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $data = $request->userData();

        if ($user->is($request->user()) && (! $data['active'] || $data['role'] !== Role::Admin->value)) {
            return back()->withInput()->withErrors(['active' => 'You cannot deactivate your own account or remove your own Administrator role. Ask another Administrator.']);
        }

        $oldRole = $user->role;
        $user->fill($data);
        $changes = $this->accounts->safe($user->getDirty());
        $user->save();

        if ($changes) {
            $roleNote = isset($changes['role']) ? " Role changed from {$oldRole->label()} to {$user->role->label()}." : '';
            Audit::log('user.updated', "{$request->user()->name} updated {$user->name}'s account.{$roleNote}", $user, $changes);
        }

        return redirect()->route('admin.users.index')->with('status', "{$user->name}'s account was updated.");
    }

    /** Deactivate or reactivate. Deactivated users cannot sign in; their IPCRs and records stay. */
    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['active' => 'You cannot deactivate your own account.']);
        }

        $user->update(['active' => ! $user->active]);
        Audit::log($user->active ? 'user.reactivated' : 'user.deactivated', "{$request->user()->name} ".($user->active ? 'reactivated' : 'deactivated')." {$user->name}'s account.", $user);

        return back()->with('status', $user->active
            ? "{$user->name} can sign in again."
            : "{$user->name} can no longer sign in. Their IPCRs and records are kept.");
    }

    public function resend(Request $request, User $user): RedirectResponse
    {
        if (! $user->active) {
            return back()->withErrors(['active' => "{$user->name}'s account is inactive. Reactivate it first."]);
        }

        $this->accounts->sendSetPasswordLink($user, $request->user());

        return back()->with('status', "A new set-password email was sent to {$user->email}.");
    }

    private function options(): array
    {
        return [
            'roles' => Role::options(),
            'colleges' => College::where('active', true)->orderBy('name')->get(),
            'departments' => Department::with('college')->where('active', true)->orderBy('name')->get(),
        ];
    }
}
