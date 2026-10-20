<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_new_user_sets_their_own_password_from_the_emailed_link(): void
    {
        Notification::fake();
        $alex = User::where('email', 'alex.rivera@mmsu.test')->first();
        $college = \App\Models\College::where('code', 'RO')->first();

        $this->actingAs($alex)->post(route('admin.users.store'), [
            'name' => 'Paz Molina', 'email' => 'paz.molina@mmsu.test', 'role' => 'nonteaching', 'college_id' => $college->id, 'active' => '1',
        ])->assertRedirect(route('admin.users.index'));
        auth()->logout();

        $paz = User::where('email', 'paz.molina@mmsu.test')->firstOrFail();
        $token = null;
        Notification::assertSentTo($paz, SetPasswordNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->get(route('password.reset', ['token' => $token, 'email' => $paz->email]))->assertOk();
        $this->post(route('password.update'), [
            'token' => $token, 'email' => $paz->email, 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $paz->email, 'password' => 'a-new-password', 'password_confirmation' => 'a-new-password',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('a-new-password', $paz->fresh()->password));
        $this->post(route('login.attempt'), ['email' => $paz->email, 'password' => 'a-new-password'])->assertRedirect(route('employee.dashboard'));
    }

    public function test_forgot_password_does_not_reveal_whether_an_email_exists(): void
    {
        Notification::fake();
        $sofia = User::where('email', 'sofia.mendoza@mmsu.test')->first();

        $this->post(route('password.email'), ['email' => $sofia->email])->assertSessionHas('status');
        $this->post(route('password.email'), ['email' => 'nobody@mmsu.test'])->assertSessionHas('status');

        Notification::assertSentTo($sofia, ResetPassword::class);
        Notification::assertCount(1);
    }

    public function test_reset_link_with_a_wrong_token_is_rejected(): void
    {
        $this->post(route('password.update'), [
            'token' => 'not-a-real-token', 'email' => 'sofia.mendoza@mmsu.test', 'password' => 'a-new-password', 'password_confirmation' => 'a-new-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.attempt'), ['email' => 'sofia.mendoza@mmsu.test', 'password' => 'wrong']);
        }

        $this->post(route('login.attempt'), ['email' => 'sofia.mendoza@mmsu.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many sign-in attempts.', session('errors')->first('email'));
        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_sign_in_but_records_stay(): void
    {
        $alex = User::where('email', 'alex.rivera@mmsu.test')->first();
        $sofia = User::where('email', 'sofia.mendoza@mmsu.test')->first();
        $records = $sofia->ipcrs()->count();

        $this->actingAs($alex)->post(route('admin.users.toggle', $sofia))->assertRedirect();
        $this->assertFalse($sofia->fresh()->active);
        auth()->logout();

        $this->post(route('login.attempt'), ['email' => $sofia->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertSame($records, $sofia->ipcrs()->count());

        // A signed-in user who gets deactivated is signed out on the next request.
        $this->actingAs($sofia->fresh())->get(route('employee.dashboard'))->assertRedirect(route('login'));
    }

    public function test_changing_the_department_head_moves_the_queue_to_the_new_person(): void
    {
        $alex = User::where('email', 'alex.rivera@mmsu.test')->first();
        $daniel = User::where('email', 'daniel.ramos@mmsu.test')->first();
        $leo = User::where('email', 'leo.pascual@mmsu.test')->first();

        // Daniel becomes a teaching employee and Leo becomes the Department Head of the same department.
        $this->actingAs($alex)->put(route('admin.users.update', $daniel), [
            'name' => $daniel->name, 'email' => $daniel->email, 'role' => 'teaching',
            'college_id' => $daniel->college_id, 'department_id' => $daniel->department_id, 'active' => '1',
        ])->assertRedirect();
        $this->actingAs($alex)->put(route('admin.users.update', $leo), [
            'name' => $leo->name, 'email' => $leo->email, 'role' => 'depthead',
            'college_id' => $leo->college_id, 'department_id' => $leo->department_id, 'active' => '1',
        ])->assertRedirect();

        $this->actingAs($leo->fresh())->get(route('depthead.queue'))->assertOk()->assertSee('Diana Torres');
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.updated', 'subject_id' => $leo->id]);
    }

    public function test_create_admin_command(): void
    {
        $this->artisan('eipcr:create-admin')
            ->expectsQuestion('Full name', 'Rosa Valdez')
            ->expectsQuestion('Email', 'rosa.valdez@mmsu.test')
            ->expectsQuestion('Password (at least 8 characters)', 'admin-password')
            ->expectsQuestion('Type the password again', 'admin-password')
            ->assertSuccessful();

        $rosa = User::where('email', 'rosa.valdez@mmsu.test')->firstOrFail();
        $this->assertSame('admin', $rosa->role->value);
        $this->assertTrue(Hash::check('admin-password', $rosa->password));

        $this->artisan('eipcr:create-admin')
            ->expectsQuestion('Full name', 'Someone')
            ->expectsQuestion('Email', 'rosa.valdez@mmsu.test')
            ->expectsQuestion('Password (at least 8 characters)', 'short')
            ->expectsQuestion('Type the password again', 'short')
            ->assertFailed();
    }

    public function test_audit_log_records_sign_ins_and_never_passwords(): void
    {
        $this->post(route('login.attempt'), ['email' => 'mika.santos@mmsu.test', 'password' => 'wrong-secret']);
        $this->post(route('login.attempt'), ['email' => 'mika.santos@mmsu.test', 'password' => 'password']);

        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.failed']);
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login']);
        $this->assertFalse(AuditLog::where('description', 'like', '%wrong-secret%')->orWhere('changes', 'like', '%wrong-secret%')->exists());

        $this->actingAs(User::where('email', 'alex.rivera@mmsu.test')->first())
            ->get(route('admin.audit', ['group' => 'auth']))->assertOk()->assertSee('signed in');
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(\RuntimeException::class);
        app(\Database\Seeders\DemoDataSeeder::class)->run();
    }
}
