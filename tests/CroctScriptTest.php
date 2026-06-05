<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests;

use Croct\Plug\Symfony\CroctScript;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[CoversClass(CroctScript::class)]
#[TestDox('The Croct script')]
final class CroctScriptTest extends TestCase
{
    #[TestDox('Renders a deferred loader and a bootstrap that runs on the loader load event.')]
    public function testRendersLoaderAndBootstrap(): void
    {
        $html = (string) new CroctScript(
            'https://cdn.example/plug.js',
            [
                'appId' => 'app-1',
                'disableCidMirroring' => true,
            ],
        );

        self::assertSame(
            '<script src="https://cdn.example/plug.js" defer></script>'
            . '<script>document.currentScript.previousElementSibling.onload=()=>'
            . 'croct.plug({"appId":"app-1","disableCidMirroring":true})</script>',
            $html,
        );
    }

    #[TestDox('Adds the CSP nonce to both tags when provided.')]
    public function testAddsNonceToBothTags(): void
    {
        $html = (string) new CroctScript('https://cdn.example/plug.js', ['appId' => 'app-1'], 'r4nd0m');

        self::assertStringContainsString(
            '<script src="https://cdn.example/plug.js" defer nonce="r4nd0m"></script>',
            $html,
        );
        self::assertStringContainsString('<script nonce="r4nd0m">', $html);
    }

    #[TestDox('Escapes the options so they cannot break out of the script tag.')]
    public function testEscapesUnsafeOptions(): void
    {
        $html = (string) new CroctScript('https://cdn.example/plug.js?a=1&b=2', ['appId' => '</script>']);

        self::assertStringContainsString('?a=1&amp;b=2', $html);
        self::assertStringContainsString('</script>', $html);
        self::assertStringNotContainsString('"</script>"', $html);
    }
}
