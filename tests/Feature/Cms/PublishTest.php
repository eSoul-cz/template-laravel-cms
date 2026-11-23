<?php

declare(strict_types=1);

namespace Tests\Feature\Cms;

use App\Application\Contract\Cms\PublicMediaStore;
use App\Domain\Cms\PublishBlocked;
use App\Domain\Cms\PublishStatus;
use App\Domain\CQRS\Command\Cms\FinishPublishCommand;
use App\Domain\CQRS\Command\Cms\MarkPublishFailedCommand;
use App\Domain\CQRS\Command\Cms\RefreshPublishCommand;
use App\Domain\CQRS\Command\Cms\StartPublishCommand;
use App\Domain\CQRS\Query\Cms\GetPublishOverviewQuery;
use App\Infrastructure\Models\Publish;
use App\Infrastructure\Models\User;
use Esoul\LaravelCqrs\Facades\CommandBus;
use Esoul\LaravelCqrs\Facades\QueryBus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\SampleContent;
use Tests\TestCase;

final class PublishTest extends TestCase
{
    private const string JOB = 'https://jenkins.example.com/job/site';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cms.jenkins.job_url' => self::JOB,
            'cms.jenkins.user' => 'robot',
            'cms.jenkins.api_token' => 'token',
            'cms.jenkins.timeout_minutes' => 30,
        ]);
        Storage::fake(PublicMediaStore::MEDIA_DISK);
    }

    public function test_publish_triggers_the_job_with_the_publish_id(): void
    {
        SampleContent::create();
        Http::fake([self::JOB . '/buildWithParameters' => Http::response('', 201, ['Location' => 'http://internal:8080/queue/item/42/'])]);

        $publish = CommandBus::dispatch(new StartPublishCommand(User::factory()->create()));

        self::assertSame(PublishStatus::Queued, $publish->status);
        self::assertSame('https://jenkins.example.com/queue/item/42', $publish->queueUrl);
        Http::assertSent(fn (Request $request): bool => $request->url() === self::JOB . '/buildWithParameters'
            && $request['CMS_PUBLISH_ID'] === (string) $publish->id
            && $request->hasHeader('Authorization'));
    }

    public function test_publish_is_blocked_while_another_one_runs(): void
    {
        SampleContent::create();
        Publish::query()->create(['status' => PublishStatus::Running, 'started_at' => now()]);

        $this->expectException(PublishBlocked::class);

        CommandBus::dispatch(new StartPublishCommand(User::factory()->create()));
    }

    public function test_publish_is_blocked_without_jenkins_configuration(): void
    {
        SampleContent::create();
        config(['cms.jenkins.job_url' => null]);

        try {
            CommandBus::dispatch(new StartPublishCommand(User::factory()->create()));
            self::fail('Expected PublishBlocked.');
        } catch (PublishBlocked $blocked) {
            self::assertStringContainsString('Jenkins is not configured', $blocked->problems[0]);
        }
    }

    public function test_publish_is_blocked_by_export_problems(): void
    {
        Http::fake();

        try {
            CommandBus::dispatch(new StartPublishCommand(User::factory()->create()));
            self::fail('Expected PublishBlocked.');
        } catch (PublishBlocked $blocked) {
            self::assertContains('There is no published page.', $blocked->problems);
        }

        Http::assertNothingSent();
        self::assertSame(0, Publish::query()->count());
    }

    public function test_a_jenkins_failure_marks_the_publish_failed(): void
    {
        SampleContent::create();
        Http::fake([self::JOB . '/buildWithParameters' => Http::response('nope', 500)]);

        $publish = CommandBus::dispatch(new StartPublishCommand(User::factory()->create()));

        self::assertSame(PublishStatus::Failed, $publish->status);
        self::assertStringContainsString('HTTP 500', (string) $publish->message);
    }

    public function test_a_publish_without_a_result_times_out(): void
    {
        $stale = Publish::query()->create(['status' => PublishStatus::Running, 'started_at' => now()->subMinutes(31)]);

        self::assertNull(QueryBus::execute(new GetPublishOverviewQuery())->currentId);
        self::assertSame(PublishStatus::Failed, $stale->refresh()->status);
    }

    public function test_refresh_polls_the_queue_and_then_the_build(): void
    {
        $queue = 'https://jenkins.example.com/queue/item/42';
        $build = 'https://jenkins.example.com/job/site/7/';
        $publish = Publish::query()->create(['status' => PublishStatus::Queued, 'started_at' => now(), 'jenkins_queue_url' => $queue]);
        Http::fake([
            $queue . '/api/json' => Http::sequence()->push(['executable' => null])->push(['executable' => ['url' => $build]]),
            rtrim($build, '/') . '/api/json' => Http::sequence()->push(['building' => true, 'result' => null])->push(['building' => false, 'result' => 'SUCCESS']),
        ]);

        self::assertSame(PublishStatus::Queued, CommandBus::dispatch(new RefreshPublishCommand($publish->id))->status);

        self::assertSame(PublishStatus::Running, CommandBus::dispatch(new RefreshPublishCommand($publish->id))->status);
        self::assertSame($build, $publish->refresh()->jenkins_build_url);

        self::assertSame(PublishStatus::Success, CommandBus::dispatch(new RefreshPublishCommand($publish->id))->status);
        self::assertNotNull($publish->refresh()->finished_at);
    }

    public function test_refresh_rejects_an_unexpected_jenkins_answer(): void
    {
        $queue = 'https://jenkins.example.com/queue/item/42';
        $publish = Publish::query()->create(['status' => PublishStatus::Queued, 'started_at' => now(), 'jenkins_queue_url' => $queue]);
        Http::fake([$queue . '/api/json' => Http::response('<html>login</html>')]);

        $this->expectExceptionMessage('Unexpected Jenkins response');

        CommandBus::dispatch(new RefreshPublishCommand($publish->id));
    }

    public function test_unpublished_changes_are_detected(): void
    {
        SampleContent::create();
        self::assertTrue(QueryBus::execute(new GetPublishOverviewQuery())->hasUnpublishedChanges);

        $this->travelTo(now()->addMinute());
        Publish::query()->create(['status' => PublishStatus::Success, 'started_at' => now(), 'finished_at' => now()]);

        self::assertFalse(QueryBus::execute(new GetPublishOverviewQuery())->hasUnpublishedChanges);
    }

    public function test_a_stuck_publish_can_be_marked_failed_by_an_admin(): void
    {
        $publish = Publish::query()->create(['status' => PublishStatus::Running, 'started_at' => now()]);
        $admin = User::factory()->create(['name' => 'Ada']);

        $outcome = CommandBus::dispatch(new MarkPublishFailedCommand($publish->id, $admin));

        self::assertSame(PublishStatus::Failed, $outcome->status);
        self::assertSame('Marked as failed by Ada.', $outcome->message);
    }

    public function test_a_late_result_of_a_finished_publish_is_recorded_with_a_note(): void
    {
        $publish = Publish::query()->create(['status' => PublishStatus::Failed, 'started_at' => now(), 'finished_at' => now()]);

        $outcome = CommandBus::dispatch(new FinishPublishCommand($publish->id, success: true, buildUrl: null, contentHash: 'sha256:abc', message: 'Deployed.'));

        self::assertSame(PublishStatus::Success, $outcome->status);
        self::assertSame('Result arrived after the publish had been ended. Deployed.', $outcome->message);
        self::assertSame('sha256:abc', $publish->refresh()->content_hash);
    }
}
