<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use Croct\Plug\Symfony\CroctScriptContent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(CroctScriptContent::class)]
#[TestDox('The fetched script content')]
final class CroctScriptContentTest extends TestCase
{
    #[TestDox('Exposes the content and its encoding.')]
    public function testExposesContentAndEncoding(): void
    {
        $content = new CroctScriptContent('// plug', 'br');

        self::assertSame('// plug', $content->getContent());
        self::assertSame('br', $content->getEncoding());
    }

    #[TestDox('Allows a null encoding for uncompressed content.')]
    public function testAllowsNullEncoding(): void
    {
        self::assertNull((new CroctScriptContent('// plug', null))->getEncoding());
    }
}
