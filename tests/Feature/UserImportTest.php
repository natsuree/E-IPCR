<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UserImportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'alex.rivera@mmsu.test')->firstOrFail();
    }

    private function csv(array $rows): UploadedFile
    {
        $lines = ["name,email,employee_id,role,employee_type,college_office,department"];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($v) => str_contains($v, ',') ? '"'.$v.'"' : $v, $row));
        }

        return UploadedFile::fake()->createWithContent('users.csv', implode("\n", $lines));
    }

    public function test_preview_shows_row_errors_and_import_saves_only_valid_rows(): void
    {
        Notification::fake();

        $file = $this->csv([
            ['Ramon Cruz', 'ramon.cruz@mmsu.test', '2026-0101', 'Teaching Employee', 'Teaching', 'College of Engineering', 'Electrical Engineering'],
            ['Liza Bautista', 'liza.bautista@mmsu.test', '2026-0102', 'Non-Teaching Employee', '', 'FO', ''],
            ['Duplicate Sofia', 'sofia.mendoza@mmsu.test', '', 'Teaching Employee', 'Teaching', 'College of Engineering', 'Computer Engineering'],
            ['Bad Role', 'bad.role@mmsu.test', '', 'Chancellor', '', 'College of Engineering', ''],
            ['No Unit', 'no.unit@mmsu.test', '', 'Teaching Employee', 'Teaching', 'College of Nowhere', ''],
            ['Head Without Dept', 'head@mmsu.test', '', 'Department Head', '', 'College of Engineering', ''],
            ['Wrong Type', 'wrong.type@mmsu.test', '', 'Teaching Employee', 'Non-teaching', 'College of Engineering', ''],
            ['Ramon Again', 'ramon.cruz@mmsu.test', '', 'Teaching Employee', 'Teaching', 'College of Engineering', ''],
        ]);

        $response = $this->actingAs($this->admin())->post(route('admin.users.import.preview'), ['file' => $file]);

        $response->assertOk()
            ->assertSee('2 ready to import')
            ->assertSee('6 with errors')
            ->assertSee('An account with this email already exists.')
            ->assertSee('Role must be one of')
            ->assertSee('was not found')
            ->assertSee('A Department Head needs a department.')
            ->assertSee('does not match the role')
            ->assertSee('used twice in this file');
        $this->assertNull(User::where('email', 'ramon.cruz@mmsu.test')->first(), 'Nothing is saved at the preview step.');

        $this->actingAs($this->admin())->post(route('admin.users.import.store'))->assertRedirect(route('admin.users.index'));

        $ramon = User::where('email', 'ramon.cruz@mmsu.test')->firstOrFail();
        $liza = User::where('email', 'liza.bautista@mmsu.test')->firstOrFail();
        $this->assertSame('teaching', $ramon->employee_type);
        $this->assertSame('Electrical Engineering', $ramon->department->name);
        $this->assertSame('Finance Office', $liza->college->name);
        $this->assertSame('nonteaching', $liza->employee_type);
        $this->assertFalse(User::where('email', 'bad.role@mmsu.test')->exists());
        Notification::assertSentTo([$ramon, $liza], SetPasswordNotification::class);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.imported']);
    }

    public function test_file_without_the_required_columns_is_rejected(): void
    {
        $file = UploadedFile::fake()->createWithContent('users.csv', "full name,mail\nA,b@c.d");

        $this->actingAs($this->admin())->post(route('admin.users.import.preview'), ['file' => $file])
            ->assertOk()->assertSee('The file is missing these columns');
    }

    public function test_only_the_administrator_can_import(): void
    {
        $this->actingAs(User::where('email', 'mika.santos@mmsu.test')->first())
            ->post(route('admin.users.import.preview'), ['file' => $this->csv([])])
            ->assertForbidden();
    }
}
