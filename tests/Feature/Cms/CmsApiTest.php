<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\Cms\PublishStatus;
use App\Infrastructure\Models\Publish;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SampleContent;
use Tests\TestCase;

final class CmsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(PublicMediaStore::MEDIA_DISK);
        Storage::fake(PublicMediaStore::DISK);
    }

    public function test_the_export_is_disabled_without_a_token(): void
    {
        config(['cms.export_token' => null]);

        $this->getJson('/cms-api/v1/export')->assertNotFound();
    }

    public function test_the_export_requires_the_bearer_token(): void
    {
        config(['cms.export_token' => 'secret']);

        $this->getJson('/cms-api/v1/export')->assertUnauthorized();
        $this->withToken('wrong')->getJson('/cms-api/v1/export')->assertUnauthorized();
    }

    public function test_the_export_returns_the_contract_and_publishes_the_media(): void
    {
        config(['cms.export_token' => 'secret']);
        SampleContent::create();

        $response = $this->withToken('secret')->getJson('/cms-api/v1/export')->assertOk();

        $response->assertJsonPath('schemaVersion', 1)->assertJsonPath('pages.0.slug', 'home');

        $url = $response->json('settings.ogImage.url');
        self::assertIsString($url);
        self::assertStringStartsWith('/_cms/', $url);
        Storage::disk(PublicMediaStore::DISK)->assertExists(substr($url, strlen('/_cms/')));
    }

    public function test_the_export_refuses_content_that_breaks_the_contract(): void
    {
        config(['cms.export_token' => 'secret']);

        $this->withToken('secret')->getJson('/cms-api/v1/export')
            ->assertStatus(422)
            ->assertJsonPath('problems.0', 'There is no published page.');
    }

    public function test_the_allowlist_rejects_other_addresses(): void
    {
        config(['cms.export_token' => 'secret', 'cms.api_allowed_ips' => ['198.51.100.0/24']]);
        SampleContent::create();

        $this->withToken('secret')->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->getJson('/cms-api/v1/export')->assertForbidden();
        $this->withToken('secret')->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])->getJson('/cms-api/v1/export')->assertOk();
    }

    public function test_the_callback_records_the_result(): void
    {
        config(['cms.jenkins.callback_token' => 'callback']);
        $publish = Publish::query()->create(['status' => PublishStatus::Running, 'started_at' => now()]);

        $this->postJson("/cms-api/v1/publishes/{$publish->id}/status", ['result' => 'SUCCESS'])->assertUnauthorized();

        $this->withToken('callback')
            ->postJson("/cms-api/v1/publishes/{$publish->id}/status", [
                'result' => 'UNSTABLE',
                'buildUrl' => 'https://jenkins.example.com/job/site/12/',
                'contentHash' => 'sha256:abc',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $publish->refresh();
        self::assertSame(PublishStatus::Success, $publish->status);
        self::assertSame('sha256:abc', $publish->content_hash);
        self::assertStringContainsString('unstable', (string) $publish->message);
    }

    public function test_a_failed_build_is_recorded_as_failed(): void
    {
        config(['cms.jenkins.callback_token' => 'callback']);
        $publish = Publish::query()->create(['status' => PublishStatus::Running, 'started_at' => now()]);

        $this->withToken('callback')->postJson("/cms-api/v1/publishes/{$publish->id}/status", ['result' => 'FAILURE'])->assertOk();

        self::assertSame(PublishStatus::Failed, $publish->refresh()->status);
    }

    public function test_the_callback_rejects_a_body_without_a_result(): void
    {
        config(['cms.jenkins.callback_token' => 'callback']);
        $publish = Publish::query()->create(['status' => PublishStatus::Running, 'started_at' => now()]);

        $this->withToken('callback')
            ->postJson("/cms-api/v1/publishes/{$publish->id}/status", ['buildUrl' => 'not a url'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['result', 'buildUrl']);

        self::assertSame(PublishStatus::Running, $publish->refresh()->status);
    }
}
