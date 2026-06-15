<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\Twig;

use Croct\Plug\LoadMode;
use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\EventListener\CroctScriptSubscriber;
use Croct\Plug\Symfony\Twig\CroctScriptRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(CroctScriptRuntime::class)]
#[TestDox('The croct Twig runtime')]
final class CroctScriptRuntimeTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const API_KEY = '11111111-2222-4333-8444-555555555555';

    private const LOADER = 'https://cdn.example/plug.js';

    #[TestDox('Renders the bootstrap and flags the request so the subscriber skips it.')]
    public function testRendersAndFlagsRequest(): void
    {
        $request = Request::create('/');
        $stack = new RequestStack();
        $stack->push($request);

        $html = $this->createRuntime($stack)->render();

        self::assertStringContainsString('<script src="' . self::LOADER . '" defer></script>', $html);
        self::assertStringContainsString('"appId":"' . self::APP_ID . '"', $html);
        self::assertTrue($request->attributes->get(CroctScriptSubscriber::SCRIPT_ATTRIBUTE));
    }

    #[TestDox('Renders the loader according to the configured mode.')]
    public function testRendersConfiguredMode(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        $html = $this->createRuntime($stack, LoadMode::SYNC)->render();

        self::assertStringContainsString('<script src="' . self::LOADER . '"></script>', $html);
        self::assertStringNotContainsString('defer', $html);
    }

    #[TestDox('Passes the CSP nonce through to the tags.')]
    public function testPassesNonce(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        self::assertStringContainsString('nonce="abc"', $this->createRuntime($stack)->render('abc'));
    }

    #[TestDox('Renders even when there is no current request.')]
    public function testRendersWithoutRequest(): void
    {
        self::assertStringContainsString('croct.plug(', $this->createRuntime(new RequestStack())->render());
    }

    #[TestDox('Wraps a snippet in the onCroctPlug queue for the croct filter.')]
    public function testWrapsCallbackSnippet(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/'));

        self::assertSame(
            '<script>(window.onCroctPlug=window.onCroctPlug||(f=>(onCroctPlug.q=onCroctPlug.q||[]).push(f)))'
            . "(croct=>{croct.track('x')})</script>",
            $this->createRuntime($stack)->callback("croct.track('x')"),
        );
    }

    #[TestDox('Adds the request CSP nonce to the callback snippet.')]
    public function testCallbackUsesNonce(): void
    {
        $request = Request::create('/');
        $request->attributes->set('csp_nonce', 'n0nce');

        $stack = new RequestStack();
        $stack->push($request);

        self::assertStringContainsString(
            '<script nonce="n0nce">',
            $this->createRuntime($stack)->callback('croct.track("x")'),
        );
    }

    private function createRuntime(RequestStack $stack, LoadMode $mode = LoadMode::DEFER): CroctScriptRuntime
    {
        return new CroctScriptRuntime(
            new CroctManager($stack, self::APP_ID, self::API_KEY),
            $stack,
            self::LOADER,
            $mode,
        );
    }
}
