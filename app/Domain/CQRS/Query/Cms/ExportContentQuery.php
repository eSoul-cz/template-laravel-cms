<?php

declare(strict_types=1);

namespace App\Domain\CQRS\Query\Cms;

use App\Domain\Cms\ContentExport;
use Esoul\Cqrs\Contracts\QueryInterface;

/**
 * The content of the site as export contract v1: the payload, the media files it references and the
 * problems that would make the frontend schema reject it.
 *
 * @implements QueryInterface<ContentExport>
 */
final class ExportContentQuery implements QueryInterface {}
