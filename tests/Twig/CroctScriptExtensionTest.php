<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Tests\Twig;

use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\Twig\CroctScriptExtension;
use Croct\Plug\Symfony\Twig\CroctScriptRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

#[CoversClass(CroctScriptExtension::class)]
#[TestDox('The Croct Twig extension')]
final class CroctScriptExtensionTest extends TestCase
{
    private const APP_ID = '7e9d59a9-e4b3-45d4-b1c7-48287f1e5e8a';

    private const API_KEY = '11111111-2222-4333-8444-555555555555';

    #[TestDox('Declares the croct_script function bound to the runtime.')]
    public function testDeclaresCroctScriptFunction(): void
    {
        $functions = (new CroctScriptExtension())->getFunctions();

        self::assertCount(1, $functions);
        self::assertSame('croct_script', $functions[0]->getName());
        self::assertSame([CroctScriptRuntime::class, 'render'], $functions[0]->getCallable());
    }

    #[TestDox('Declares the croct filter bound to the runtime.')]
    public function testDeclaresCroctFilter(): void
    {
        $filters = (new CroctScriptExtension())->getFilters();

        self::assertCount(1, $filters);
        self::assertSame('croct', $filters[0]->getName());
        self::assertSame([CroctScriptRuntime::class, 'callback'], $filters[0]->getCallable());
    }

    #[TestDox('Wraps an {% apply croct %} block in the onCroctPlug queue.')]
    public function testAppliesCroctFilter(): void
    {
        $runtime = new CroctScriptRuntime(
            new CroctManager(new RequestStack(), self::APP_ID, self::API_KEY),
            new RequestStack(),
            'https://cdn.example/plug.js',
        );

        $twig = new Environment(new ArrayLoader([
            'page' => "{% apply croct %}croct.track('linkOpened'){% endapply %}",
        ]));
        $twig->addExtension(new CroctScriptExtension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            CroctScriptRuntime::class => static fn (): CroctScriptRuntime => $runtime,
        ]));

        $output = $twig->render('page');

        self::assertStringContainsString('window.onCroctPlug=window.onCroctPlug||', $output);
        self::assertStringContainsString("(croct=>{croct.track('linkOpened')})", $output);
    }
}
