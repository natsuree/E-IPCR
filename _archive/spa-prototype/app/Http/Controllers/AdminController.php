<?php

namespace App\Http\Controllers;

use App\Models\EvaluationPeriod;
use App\Models\IpcrRecord;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Services\DemoData;
use App\Services\Ipcr\IpcrException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** Administrator screens: users, units, periods, rating settings and demo reset. */
class AdminController extends Controller
{
    private function guardAdmin(Request $request): User
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            throw new IpcrException('Administrator access is required.');
        }

        return $user;
    }

    // ------------------------------------------------------------------ users

    public function storeUser(Request $request): JsonResponse
    {
        $this->guardAdmin($request);
        $payload = $this->userPayload($request, null);

        $user = User::create($payload['user']);
        $user->periods()->sync($payload['periodIds']);

        return $this->usersResponse();
    }

    public function updateUser(Request $request, string $id): JsonResponse
    {
        $this->guardAdmin($request);

        $user = User::findOrFail($id);
        $payload = $this->userPayload($request, $user);

        $attributes = $payload['user'];
        $password = $attributes['password'] ?? null;
        unset($attributes['password']);

        $user->fill($attributes);
        if ($password !== null) {
            $user->password = $password;
        }
        $user->save();
        $user->periods()->sync($payload['periodIds']);

        return $this->usersResponse();
    }

    public function toggleUser(Request $request, string $id): JsonResponse
    {
        $current = $this->guardAdmin($request);

        if ($current->id === $id) {
            throw new IpcrException('You cannot deactivate your current account.');
        }

        $user = User::findOrFail($id);
        $user->active = ! $user->active;
        $user->save();

        return $this->usersResponse();
    }

    /** Prototype admin form rules: duplicate email, self-demotion, required fields. */
    private function userPayload(Request $request, ?User $editing): array
    {
        $name = trim((string) $request->input('name'));
        $email = strtolower(trim((string) $request->input('email')));
        $password = (string) $request->input('password', '');
        $role = (string) $request->input('role', 'Teaching Employee');
        $position = trim((string) $request->input('position'));
        $unit = (string) $request->input('unit');
        $department = (string) $request->input('department');
        $type = str_ends_with($role, 'Employee')
            ? (str_starts_with($role, 'Teaching') ? 'Teaching' : 'Non-Teaching')
            : (string) $request->input('type', 'Teaching');

        if ($name === '') {
            throw new IpcrException('A name is required.');
        }
        if ($email === '') {
            throw new IpcrException('A name is required.');
        }
        if ($position === '') {
            throw new IpcrException('Position is required.');
        }

        $duplicate = User::where('lower(email)', $email)
            ->when($editing, fn ($query) => $query->where('id', '!=', $editing->id))
            ->exists();
        if ($duplicate) {
            throw new IpcrException('This email is already assigned to another account.');
        }

        if ($editing && $editing->id === Auth::id() && $role !== 'Administrator') {
            throw new IpcrException('You cannot remove your own Administrator role.');
        }

        $periodIds = $request->input('periodIds', []);
        if (! is_array($periodIds)) {
            $periodIds = [];
        }
        $periodIds = array_values(array_filter($periodIds, fn ($id) => is_string($id)));

        $attributes = [
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'type' => $type,
            'position' => $position,
            'unit' => $unit,
            'department' => $department,
        ];

        // Never re-hash a password that is already in effect (prototype stores plaintext).
        if ($password === '' || ($editing !== null && Hash::check($password, $editing->password))) {
            unset($attributes['password']);
        } else {
            $attributes['password'] = $password;
        }

        return ['user' => $attributes, 'periodIds' => $periodIds];
    }

    private function usersResponse(): JsonResponse
    {
        return response()->json([
            'users' => User::orderByRaw('rowid')
                ->get()
                ->map(fn (User $user) => $user->toApi(true))
                ->values(),
        ]);
    }

    // ------------------------------------------------------------------ units

    public function storeUnit(Request $request): JsonResponse
    {
        $this->guardAdmin($request);
        $name = trim((string) $request->input('name'));
        $departments = $this->departmentList($request);

        if ($name === '') {
            throw new IpcrException('A name is required.');
        }
        if (Unit::where('lower(name)', $name)->exists()) {
            throw new IpcrException('That unit name already exists.');
        }
        if ($departments === []) {
            throw new IpcrException('Add at least one department.');
        }

        Unit::create(['id' => (string) Str::uuid(), 'name' => $name, 'departments' => $departments]);

        return $this->unitsResponse();
    }

    public function updateUnit(Request $request, string $id): JsonResponse
    {
        $this->guardAdmin($request);

        $unit = Unit::findOrFail($id);
        $name = trim((string) $request->input('name'));
        $departments = $this->departmentList($request);

        if ($name === '') {
            throw new IpcrException('A name is required.');
        }
        if (Unit::where('lower(name)', $name)->where('id', '!=', $unit->id)->exists()) {
            throw new IpcrException('That unit name already exists.');
        }
        if ($departments === []) {
            throw new IpcrException('Add at least one department.');
        }

        $removed = array_diff($unit->departments, $departments);

        if ($removed !== []) {
            $inUseByUser = User::where('unit', $unit->name)->whereIn('department', $removed)->exists();
            $inUseByRecord = IpcrRecord::where('unit', $unit->name)->whereIn('department', $removed)->exists();
            if ($inUseByUser || $inUseByRecord) {
                throw new IpcrException('A department in use cannot be removed. Reassign its accounts first; historical records must retain their department.');
            }
        }

        DB::transaction(function () use ($unit, $name, $departments) {
            // Unit renaming updates current assignments; submission snapshots keep historical text.
            User::where('unit', $unit->name)->update(['unit' => $name]);
            IpcrRecord::where('unit', $unit->name)->update(['unit' => $name]);

            $unit->name = $name;
            $unit->departments = $departments;
            $unit->save();
        });

        return $this->unitsResponse();
    }

    /** @return list<string> */
    private function departmentList(Request $request): array
    {
        $input = $request->input('departments', []);

        if (is_string($input)) {
            $input = preg_split('/\r\n|\r|\n/', $input) ?: [];
        }
        if (! is_array($input)) {
            return [];
        }

        $names = array_values(array_unique(array_filter(array_map(
            fn ($value) => is_scalar($value) ? trim((string) $value) : '',
            $input
        ))));

        sort($names);

        return $names;
    }

    private function unitsResponse(): JsonResponse
    {
        return response()->json([
            'units' => Unit::orderByRaw('rowid')
                ->get()
                ->map(fn (Unit $unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    'departments' => $unit->departments,
                ])
                ->values(),
        ]);
    }

    // ---------------------------------------------------------------- periods

    public function storePeriod(Request $request): JsonResponse
    {
        $this->guardAdmin($request);
        $payload = $this->periodPayload($request, null);

        EvaluationPeriod::create($payload + ['active' => false]);

        return $this->periodsResponse();
    }

    public function updatePeriod(Request $request, string $id): JsonResponse
    {
        $this->guardAdmin($request);

        $period = EvaluationPeriod::findOrFail($id);
        $period->fill($this->periodPayload($request, $period));
        $period->save();

        return $this->periodsResponse();
    }

    public function activatePeriod(Request $request, string $id): JsonResponse
    {
        $this->guardAdmin($request);

        $target = EvaluationPeriod::findOrFail($id);
        EvaluationPeriod::query()->update(['active' => false]);
        $target->active = true;
        $target->save();

        return $this->periodsResponse();
    }

    private function periodPayload(Request $request, ?EvaluationPeriod $editing): array
    {
        $name = trim((string) $request->input('name'));
        $start = (string) $request->input('start');
        $end = (string) $request->input('end');

        if ($name === '') {
            throw new IpcrException('A name is required.');
        }
        if ($start === '' || $end === '') {
            throw new IpcrException('Start and end dates are required.');
        }
        if ($start > $end) {
            throw new IpcrException('The end date must be after the start date.');
        }

        return ['name' => $name, 'start' => $start, 'end' => $end];
    }

    private function periodsResponse(): JsonResponse
    {
        return response()->json([
            'periods' => EvaluationPeriod::orderByRaw('rowid')
                ->get()
                ->map(fn (EvaluationPeriod $period) => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'start' => $period->start,
                    'end' => $period->end,
                    'active' => $period->active,
                ])
                ->values(),
        ]);
    }

    // --------------------------------------------------------------- settings

    public function updateSettings(Request $request): JsonResponse
    {
        $this->guardAdmin($request);

        $weights = $request->input('weights', []);
        $scale = $request->input('scale', []);

        if (! is_array($weights) || count($weights) !== 3 || array_filter($weights, fn ($w) => ! is_numeric($w)) !== []) {
            throw new IpcrException('Category weights must total 100%.');
        }
        $weights = array_map(fn ($weight) => (float) $weight, array_values($weights));

        if (abs(array_sum($weights) - 100) > 0.001) {
            throw new IpcrException('Category weights must total 100%.');
        }

        if (! is_array($scale) || $scale === []) {
            throw new IpcrException('Thresholds must descend strictly, with Poor starting at 1.');
        }

        $cleanScale = [];
        foreach (array_values($scale) as $index => $entry) {
            $min = is_array($entry) ? ($entry['min'] ?? null) : null;
            $label = is_array($entry) && is_scalar($entry['label'] ?? null) ? (string) $entry['label'] : '';
            if (! is_numeric($min) || $label === '') {
                throw new IpcrException('Thresholds must descend strictly, with Poor starting at 1.');
            }
            $cleanScale[] = ['min' => (float) $min, 'label' => $label];
        }

        $last = $cleanScale[array_key_last($cleanScale)];
        if ($last['min'] !== 1.0) {
            throw new IpcrException('Thresholds must descend strictly, with Poor starting at 1.');
        }
        foreach ($cleanScale as $index => $entry) {
            if ($index > 0 && $entry['min'] >= $cleanScale[$index - 1]['min']) {
                throw new IpcrException('Thresholds must descend strictly, with Poor starting at 1.');
            }
        }

        $settings = Setting::current();
        $settings->weights = $weights;
        $settings->scale = $cleanScale;
        $settings->save();

        return response()->json(['settings' => ['weights' => $settings->weights, 'scale' => $settings->scale]]);
    }

    // ------------------------------------------------------------------- reset

    public function reset(Request $request): JsonResponse
    {
        $this->guardAdmin($request);

        DemoData::reset();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }
}
