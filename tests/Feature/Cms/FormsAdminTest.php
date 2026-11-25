<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Infrastructure\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Infrastructure\Models\FormSubmission;
use App\Infrastructure\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

final class FormsAdminTest extends TestCase
{
    public function test_an_admin_lists_and_opens_the_submissions(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $submission = FormSubmission::factory()->create(['name' => 'Jane Doe', 'message' => "First line\nSecond line"]);

        $this->get('/admin/form-submissions')->assertOk()->assertSee('Jane Doe');
        $this->get("/admin/form-submissions/{$submission->id}")->assertOk()->assertSee('Second line');
    }

    public function test_the_newest_submission_comes_first_and_the_list_is_searchable(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        // Fixed emails: the search covers name and email, and a random email may contain "old" (harold@…).
        $old = FormSubmission::factory()->create(['name' => 'Old Sender', 'email' => 'first@example.com', 'created_at' => now()->subDay()]);
        $new = FormSubmission::factory()->create(['name' => 'New Sender', 'email' => 'second@example.com']);

        Livewire::test(ListFormSubmissions::class)
            ->assertCanSeeTableRecords([$new, $old], inOrder: true)
            ->searchTable('Old')
            ->assertCanSeeTableRecords([$old])
            ->assertCanNotSeeTableRecords([$new]);
    }

    public function test_a_submission_can_be_deleted_but_not_created_or_edited(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $submission = FormSubmission::factory()->create();

        Livewire::test(ListFormSubmissions::class)->callTableAction('delete', $submission);

        $this->assertModelMissing($submission);

        $this->get('/admin/form-submissions/create')->assertNotFound();
        $this->get("/admin/form-submissions/{$submission->id}/edit")->assertNotFound();
    }

    public function test_users_without_the_admin_flag_are_forbidden(): void
    {
        $submission = FormSubmission::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->get('/admin/form-submissions')->assertForbidden();
        $this->get("/admin/form-submissions/{$submission->id}")->assertForbidden();
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/admin/form-submissions')->assertRedirect('/admin/login');
    }
}
