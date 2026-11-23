# Forms

The static site (`frontend/`) has no server of its own, so its forms post to this backend. `POST /forms/contact` (route
`forms.contact`) is a public endpoint for the browsers of the visitors. It is not part of `/cms-api` (that one is for the build agent:
IP allowlist and token) and not part of the `web` middleware group: a plain cross-origin form post has no CSRF token, and the
endpoint needs no cookie or session.

## How a post is handled

The form is a plain `<form method="post" action="https://admin.example.com/forms/contact">` with the fields `name` (max 120),
`email`, `message` (max 5000), the hidden honeypot field `website` (name: `cms.forms.honeypot_field`, must stay empty),
`recaptcha_token` (reCAPTCHA v3 token, action `contact`) and optionally `return_to` (absolute URL on an allowed origin).

1. **Origin check** (middleware `RequireAllowedFormOrigin`): the `Origin` header, else the origin of the `Referer`, must be in
   `CMS_FORMS_ALLOWED_ORIGINS`. Otherwise `403`. An empty list rejects everything in production and accepts every origin when
   `APP_ENV=local`.
2. **Validation** (`SubmitContactFormRequest`): `422` / `?form=invalid` on bad input.
3. `SubmitFormCommand` runs the pipeline:
   1. **Honeypot** filled: fake success, nothing is stored or sent.
   2. **Rate limit** per client: `cms.forms.rate_limit` (5 per 600 s). The client address is kept only as an HMAC-SHA256 with `APP_KEY`.
      The counters live in the cache store: use `file` or `database`.
   3. **reCAPTCHA v3**: score at least `CMS_FORMS_RECAPTCHA_MIN_SCORE`, action `contact`, hostname of an allowed origin. No token
      (JavaScript off) is rejected as `captcha_missing` with the message "Enable JavaScript or contact us at <contact>". Without
      `CMS_FORMS_RECAPTCHA_SECRET` the check is skipped with a logged warning outside production; in production the endpoint
      answers `503`. An unreachable Google rejects (`captcha_unavailable`).
   4. **Delivery**: a `form_submissions` row (shown in the admin under "Form submissions") and an e-mail to
      `CMS_FORMS_RECIPIENTS` (fallback: the contact e-mail of the site settings) over the `MAIL_*` SMTP settings, sent synchronously.
      Either one is enough; the row records `mail_status` (`pending`, `sent`, `failed`).
   5. **Last resort**: when both fail, one JSON line is appended to `storage/app/private/forms-last-resort/YYYY-MM.jsonl`
      (outside the webroot, hash instead of address). When that fails too the answer is `failed`.

## Answers

A plain post gets `303` to `return_to` (an absolute URL on an allowed origin; default: `/` of the posting site, i.e. the first allowed origin) with `?form=<code>#form-result`. A request with
`Accept: application/json` gets `{"status": "sent"|"error", "code": "...", "message": "..."}`, with the CORS header for the allowed origin.

| code | status | meaning |
| --- | --- | --- |
| `sent` | 200 | delivered (also the fake answer to the honeypot) |
| `invalid` | 422 | validation failed (`errors` in JSON) |
| `origin_forbidden` | 403 | origin not allowed (never redirects) |
| `rate_limited` | 429 | too many submissions from the client |
| `captcha_missing` | 422 | no reCAPTCHA token |
| `captcha_failed` | 403 | low score, wrong action or hostname |
| `captcha_unavailable` | 503 | reCAPTCHA could not be verified |
| `unavailable` | 503 | no reCAPTCHA secret in production |
| `failed` | 503 | database, e-mail and the last-resort file all failed |

The wording of the messages is in `App\Domain\Cms\FormOutcome`.

## Setup

- Environment: `CMS_FORMS_ALLOWED_ORIGINS`, `CMS_FORMS_RECIPIENTS`, `CMS_FORMS_RECAPTCHA_SECRET`, `CMS_FORMS_RECAPTCHA_MIN_SCORE`
  and the `MAIL_*` settings. Register the reCAPTCHA keys for the production domain of the site.
- Reverse proxy: route `/forms` to the backend next to `/cms-api` and the admin, for example `location /forms { try_files $uri /index.php?$query_string; }`
  on the backend's server block.
- After every launch-relevant deploy send a test submission and check that it arrives.
- Retention: the `form_submissions` table and the last-resort files hold personal data. Record the retention period in the project's
  decisions and delete older rows (admin: delete action) and files.
