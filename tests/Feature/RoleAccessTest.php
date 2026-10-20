<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $first): User
    {
        return User::where('name', 'like', $first.' %')->firstOrFail();
    }

    public function test_guests_are_sent_to_login(): void
    {
        foreach (['/', '/employee', '/depthead/queue', '/hr/templates', '/admin/users'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_each_role_lands_on_its_own_dashboard_after_login(): void
    {
        $expected = [
            'sofia.mendoza@mmsu.test' => 'employee.dashboard',
            'luis.navarro@mmsu.test' => 'employee.dashboard',
            'daniel.ramos@mmsu.test' => 'depthead.dashboard',
            'elena.flores@mmsu.test' => 'dean.dashboard',
            'mika.santos@mmsu.test' => 'hr.dashboard',
            'alex.rivera@mmsu.test' => 'admin.dashboard',
        ];

        foreach ($expected as $email => $route) {
            $this->post(route('login.attempt'), ['email' => $email, 'password' => 'password'])
                ->assertRedirect(route($route));
            $this->get(route($route))->assertOk();
            $this->post(route('logout'));
        }
    }

    public function test_wrong_password_and_inactive_accounts_cannot_log_in(): void
    {
        $this->post(route('login.attempt'), ['email' => 'sofia.mendoza@mmsu.test', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->user('Sofia')->update(['active' => false]);
        $this->post(route('login.attempt'), ['email' => 'sofia.mendoza@mmsu.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_roles_get_403_on_other_roles_routes(): void
    {
        $forbidden = [
            'Sofia' => ['depthead.queue', 'dean.dashboard', 'hr.templates', 'hr.reports', 'admin.users.index'],
            'Daniel' => ['employee.dashboard', 'employee.ipcrs.index', 'dean.queue', 'hr.records', 'admin.dashboard'],
            'Elena' => ['depthead.queue', 'hr.templates', 'employee.tracking'],
            'Mika' => ['employee.ipcrs.current', 'dean.analytics', 'admin.users.create', 'admin.audit'],
            'Diana' => ['hr.periods.index', 'hr.settings', 'admin.units.index'],
            'Alex' => ['employee.dashboard', 'depthead.records', 'hr.queue', 'hr.records'],
        ];

        foreach ($forbidden as $name => $routes) {
            foreach ($routes as $route) {
                $this->actingAs($this->user($name))->get(route($route))->assertForbidden();
            }
        }
    }

    public function test_every_sidebar_page_opens_for_its_role(): void
    {
        $pages = [
            'Sofia' => ['employee.dashboard', 'employee.ipcrs.index', 'employee.ipcrs.current', 'employee.tracking', 'employee.notifications', 'employee.profile'],
            'Luis' => ['employee.dashboard', 'employee.ipcrs.index', 'employee.tracking'],
            'Daniel' => ['depthead.dashboard', 'depthead.queue', 'depthead.records', 'depthead.notifications', 'depthead.profile'],
            'Elena' => ['dean.dashboard', 'dean.queue', 'dean.records', 'dean.analytics', 'dean.notifications', 'dean.profile'],
            'Mika' => ['hr.dashboard', 'hr.queue', 'hr.records', 'hr.templates', 'hr.periods.index', 'hr.reports', 'hr.settings', 'hr.notifications', 'hr.profile'],
            'Alex' => ['admin.dashboard', 'admin.users.index', 'admin.users.create', 'admin.users.import', 'admin.units.index', 'admin.audit', 'admin.profile'],
        ];

        foreach ($pages as $name => $routes) {
            foreach ($routes as $route) {
                $this->actingAs($this->user($name))->get(route($route))->assertOk();
            }
        }
    }

    public function test_employee_cannot_open_another_employees_ipcr(): void
    {
        $diana = $this->user('Diana');
        $ipcr = $diana->ipcrs()->firstOrFail();

        $this->actingAs($this->user('Sofia'))->get(route('employee.ipcrs.show', $ipcr))->assertForbidden();
        $this->actingAs($this->user('Sofia'))->get(route('employee.ipcrs.print', $ipcr))->assertForbidden();
    }

    public function test_admin_can_add_and_edit_users(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $alex = $this->user('Alex');
        $college = \App\Models\College::where('name', 'College of Engineering')->first();
        $department = $college->departments()->where('name', 'Electrical Engineering')->first();

        $this->actingAs($alex)->post(route('admin.users.store'), [
            'name' => 'Ramon Cruz', 'email' => 'ramon.cruz@mmsu.test', 'role' => 'teaching',
            'college_id' => $college->id, 'department_id' => $department->id, 'position' => 'Instructor I',
            'active' => '1', 'employee_id' => '2026-0100',
        ])->assertRedirect(route('admin.users.index'));

        $ramon = User::where('email', 'ramon.cruz@mmsu.test')->firstOrFail();
        $this->assertSame('teaching', $ramon->employee_type);
        \Illuminate\Support\Facades\Notification::assertSentTo($ramon, \App\Notifications\SetPasswordNotification::class);

        $this->actingAs($alex)->put(route('admin.users.update', $ramon), [
            'name' => 'Ramon Cruz', 'email' => 'ramon.cruz@mmsu.test', 'role' => 'teaching',
            'college_id' => $college->id, 'department_id' => $department->id, 'active' => '0',
        ])->assertRedirect(route('admin.users.index'));
        $this->assertFalse($ramon->fresh()->active);

        // A department from another college is rejected.
        $math = \App\Models\Department::where('name', 'Mathematics')->first();
        $this->actingAs($alex)->put(route('admin.users.update', $ramon), [
            'name' => 'Ramon Cruz', 'email' => 'ramon.cruz@mmsu.test', 'role' => 'teaching',
            'college_id' => $college->id, 'department_id' => $math->id, 'active' => '1',
        ])->assertSessionHasErrors('department_id');
    }
}
