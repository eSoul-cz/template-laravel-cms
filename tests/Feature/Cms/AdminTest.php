<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\Cms\PublishStatus;
use App\Infrastructure\Filament\Pages\PublishWebsite;
use App\Infrastructure\Filament\Resources\Pages\Pages\CreatePage;
use App\Infrastructure\Filament\Resources\Pages\Pages\EditPage;
use App\Infrastructure\Models\Page;
use App\Infrastructure\Models\Publish;
use App\Infrastructure\Models\SiteSetting;
use App\Infrastructure\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\SampleContent;
use Tests\TestCase;

final class AdminTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PublicMediaStore::MEDIA_DISK);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_the_cms_screens_render(): void
    {
        SampleContent::create();
        $home = Page::query()->where('slug', 'home')->firstOrFail();

        $this->get('/admin/pages')->assertOk()->assertSee('About us');
        $this->get('/admin/pages/create')->assertOk();
        $this->get("/admin/pages/{$home->id}/edit")->assertOk();
        $this->get('/admin/publish')->assertOk()->assertSee('unpublished changes');
    }

    public function test_the_settings_list_redirects_to_the_single_record(): void
    {
        $settings = SiteSetting::current();

        $this->get('/admin/site-settings')->assertRedirect("/admin/site-settings/{$settings->id}/edit");
        $this->get("/admin/site-settings/{$settings->id}/edit")->assertOk();
    }

    public function test_a_page_with_blocks_can_be_created_and_edited(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm([
                'title' => 'Contact',
                'slug' => 'contact',
                'is_published' => true,
                'sort' => 5,
                'blocks' => [
                    ['type' => 'heading', 'data' => ['level' => '2', 'text' => 'Write to us']],
                    ['type' => 'text', 'data' => ['body' => 'We answer within a day.']],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Page::query()->where('slug', 'contact')->firstOrFail();
        self::assertCount(2, $page->blocks ?? []);

        Livewire::test(EditPage::class, ['record' => $page->getKey()])
            ->fillForm(['title' => 'Contact us'])
            ->call('save')
            ->assertHasNoFormErrors();

        self::assertSame('Contact us', $page->refresh()->title);
    }

    public function test_an_invalid_slug_is_rejected(): void
    {
        Livewire::test(CreatePage::class)
            ->fillForm(['title' => 'Broken', 'slug' => 'Not A Slug'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_the_publish_button_triggers_jenkins(): void
    {
        SampleContent::create();
        config([
            'cms.jenkins.job_url' => 'https://jenkins.example.com/job/site',
            'cms.jenkins.user' => 'robot',
            'cms.jenkins.api_token' => 'token',
        ]);
        Http::fake(['*' => Http::response('', 201, ['Location' => 'https://jenkins.example.com/queue/item/9/'])]);

        Livewire::test(PublishWebsite::class)
            ->callAction('publish')
            ->assertNotified();

        self::assertSame(PublishStatus::Queued, Publish::query()->firstOrFail()->status);
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/buildWithParameters'));
    }
}
