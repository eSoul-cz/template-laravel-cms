<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Requests;

use App\Domain\Cms\FormOutcome;
use App\Domain\CQRS\Command\Cms\SubmitFormCommand;
use App\Infrastructure\Cms\FormOrigins;
use App\Infrastructure\Http\FormResponder;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * POST /forms/contact. There is no session on this route, so a failed validation is answered here (redirect or
 * JSON, see {@see FormResponder}) and never with the default redirect back with flashed errors.
 */
final class SubmitContactFormRequest extends FormRequest
{
    public const string FORM = 'contact';

    public static function honeypotField(): string
    {
        return config()->string('cms.forms.honeypot_field');
    }

    /** The origin of the site is checked by the RequireAllowedFormOrigin middleware, there is no user. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|Closure>> */
    public function rules(): array
    {
        $origins = $this->container->make(FormOrigins::class);

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'message' => ['required', 'string', 'max:5000'],
            // Not validated: a robot that fills it gets the fake success from the handler.
            self::honeypotField() => ['nullable'],
            'recaptcha_token' => ['nullable', 'string', 'max:4000'],
            'return_to' => [
                'nullable',
                'string',
                'max:2000',
                static function (string $attribute, mixed $value, Closure $fail) use ($origins): void {
                    if (is_string($value) && ! $origins->permits($value)) {
                        $fail('The :attribute must be a URL on an allowed origin.');
                    }
                },
            ],
        ];
    }

    public function toCommand(string $clientHash): SubmitFormCommand
    {
        return new SubmitFormCommand(
            form: self::FORM,
            name: $this->string('name')->toString(),
            email: $this->string('email')->toString(),
            message: $this->string('message')->toString(),
            honeypot: $this->honeypotFilled() ? '1' : null,
            recaptchaToken: $this->filled('recaptcha_token') ? $this->string('recaptcha_token')->toString() : null,
            clientHash: $clientHash,
        );
    }

    protected function failedValidation(Validator $validator): never
    {
        $responder = $this->container->make(FormResponder::class);

        // A robot that filled the honeypot is not told what was wrong with the rest.
        throw new HttpResponseException($this->honeypotFilled()
            ? $responder->respond($this, FormOutcome::Sent)
            : $responder->respond($this, FormOutcome::Invalid, $validator->errors()->toArray()));
    }

    private function honeypotFilled(): bool
    {
        return $this->filled(self::honeypotField());
    }
}
