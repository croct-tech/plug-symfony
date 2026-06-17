<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Symfony\Component\HttpFoundation\Response;

/**
 * Marks the response private so shared caches never store visitor-specific content.
 */
final class PrivateResponseMarker implements PersonalizationMarker
{
    public function mark(Response $response): void
    {
        $response->setPrivate();
    }
}
