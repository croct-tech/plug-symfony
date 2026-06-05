<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\Twig;

use Croct\Plug\Symfony\Twig\CroctScriptExtension;
use Croct\Plug\Symfony\Twig\CroctScriptRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(CroctScriptExtension::class)]
#[TestDox('The Croct Twig extension')]
final class CroctScriptExtensionTest extends TestCase
{
    #[TestDox('Declares the croct_script function bound to the runtime.')]
    public function testDeclaresCroctScriptFunction(): void
    {
        $functions = (new CroctScriptExtension())->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('croct_script', $functions[0]->getName());
        self::assertSame([CroctScriptRuntime::class, 'render'], $functions[0]->getCallable());
    }
}
