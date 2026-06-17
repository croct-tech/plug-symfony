<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Symfony\Component\HttpFoundation\Response;

/**
 * Marks a response as depending on the visitor so it is never served from a shared cache.
 *
 * Each host provides its own implementation. Symfony sets the response private, while Drupal also
 * records the cacheability so its render and page caches vary per visitor.
 */
interface PersonalizationMarker
{
    public function mark(Response $response): void;
}
