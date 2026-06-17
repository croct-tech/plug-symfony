<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\Fixtures\Application\Controller;

use Symfony\Component\HttpFoundation\Response;

/**
 * Returns a publicly cacheable response without using the plug.
 */
final class PublicController
{
    public function __invoke(): Response
    {
        $response = new Response('public');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
