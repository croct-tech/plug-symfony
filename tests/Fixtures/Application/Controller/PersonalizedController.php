<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\Fixtures\Application\Controller;

use Croct\Plug\Plug;
use Symfony\Component\HttpFoundation\Response;

/**
 * Touches the visitor session, then returns a publicly cacheable response.
 */
final class PersonalizedController
{
    private Plug $croct;

    public function __construct(Plug $croct)
    {
        $this->croct = $croct;
    }

    public function __invoke(): Response
    {
        // Using the session flags the request as varying (no network needed for the handoff).
        $this->croct->getPlugOptions();

        $response = new Response('personalized');
        $response->setPublic();
        $response->setMaxAge(3600);

        return $response;
    }
}
