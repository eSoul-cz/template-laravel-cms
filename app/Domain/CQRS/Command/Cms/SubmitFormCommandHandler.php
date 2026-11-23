<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Command\Cms;

use App\Application\Contract\Cms\CaptchaVerifier;
use App\Application\Contract\Cms\FormLastResort;
use App\Application\Contract\Cms\FormMailer;
use App\Application\Contract\Cms\FormRateLimiter;
use App\Application\Dto\Cms\FormMessage;
use App\Domain\Cms\CaptchaVerdict;
use App\Domain\Cms\FormMailStatus;
use App\Domain\Cms\FormOutcome;
use App\Infrastructure\Models\FormSubmission;
use Esoul\Cqrs\Attributes\HandlesCommand;
use Esoul\Cqrs\Contracts\CommandHandlerInterface;
use Esoul\Cqrs\Contracts\CommandInterface;
use LogicException;
use Throwable;

#[HandlesCommand(SubmitFormCommand::class)]
final readonly class SubmitFormCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private FormRateLimiter $limiter,
        private CaptchaVerifier $captcha,
        private FormMailer $mailer,
        private FormLastResort $lastResort,
    ) {}

    public function handle(CommandInterface $command): FormOutcome
    {
        if ( ! $command instanceof SubmitFormCommand) {
            throw new LogicException('Unsupported command: ' . $command::class);
        }

        // 1. Honeypot: a robot gets a fake success and nothing is stored or sent.
        if ($command->honeypot !== null && $command->honeypot !== '') {
            return FormOutcome::Sent;
        }

        // 2. Rate limit per client.
        if ( ! $this->limiter->attempt($command->form, $command->clientHash)) {
            return FormOutcome::RateLimited;
        }

        // 3. reCAPTCHA v3.
        $captcha = $this->captcha->verify($command->recaptchaToken, $command->form);
        $rejection = match ($captcha->verdict) {
            CaptchaVerdict::Passed, CaptchaVerdict::Skipped => null,
            CaptchaVerdict::Missing => FormOutcome::CaptchaMissing,
            CaptchaVerdict::Failed => FormOutcome::CaptchaFailed,
            CaptchaVerdict::Unavailable => FormOutcome::CaptchaUnavailable,
            CaptchaVerdict::Misconfigured => FormOutcome::Unavailable,
        };

        if ($rejection !== null) {
            return $rejection;
        }

        // 4. Delivery: the database record and the e-mail are independent, either one is enough.
        $message = new FormMessage($command->form, $command->name, $command->email, $command->message);
        $record = $this->store($command, $captcha->score);
        $mailed = $this->mail($message);

        if ($record instanceof FormSubmission) {
            $this->markMail($record, $mailed ? FormMailStatus::Sent : FormMailStatus::Failed);
        }

        // 5. Last resort: nothing took the submission, so it goes to a file.
        if ($record === null && ! $mailed) {
            try {
                $this->lastResort->append($message, $command->clientHash, $captcha->score);
            } catch (Throwable $exception) {
                report($exception);

                return FormOutcome::Failed;
            }
        }

        return FormOutcome::Sent;
    }

    private function store(SubmitFormCommand $command, ?float $score): ?FormSubmission
    {
        try {
            return FormSubmission::query()->create([
                'form' => $command->form,
                'name' => $command->name,
                'email' => $command->email,
                'message' => $command->message,
                'client_hash' => $command->clientHash,
                'recaptcha_score' => $score,
                'mail_status' => FormMailStatus::Pending,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function mail(FormMessage $message): bool
    {
        try {
            $this->mailer->send($message);

            return true;
        } catch (Throwable $exception) {
            report($exception);

            return false;
        }
    }

    private function markMail(FormSubmission $record, FormMailStatus $status): void
    {
        try {
            $record->update(['mail_status' => $status]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
