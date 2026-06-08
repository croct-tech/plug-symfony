<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use Croct\Plug\Symfony\PrivateResponseMarker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PrivateResponseMarker::class)]
#[TestDox('The private response marker')]
final class PrivateResponseMarkerTest extends TestCase
{
    #[TestDox('Marks the response private so shared caches never store it.')]
    public function testMarksResponsePrivate(): void
    {
        $response = new Response();
        $response->setPublic();

        (new PrivateResponseMarker())->mark($response);

        self::assertTrue($response->headers->hasCacheControlDirective('private'));
        self::assertFalse($response->headers->hasCacheControlDirective('public'));
    }
}
