<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use Croct\Plug\Symfony\RequestLocaleResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(RequestLocaleResolver::class)]
#[TestDox('The request locale resolver')]
final class RequestLocaleResolverTest extends TestCase
{
    #[TestDox('Returns the locale resolved by Symfony for the current request.')]
    public function testReturnsRequestLocale(): void
    {
        $request = Request::create('/');
        $request->setLocale('pt_BR');

        $stack = new RequestStack();
        $stack->push($request);

        self::assertSame('pt_BR', (new RequestLocaleResolver($stack))->getLocale());
    }

    #[TestDox('Returns null when there is no current request.')]
    public function testReturnsNullWithoutRequest(): void
    {
        self::assertNull((new RequestLocaleResolver(new RequestStack()))->getLocale());
    }
}
