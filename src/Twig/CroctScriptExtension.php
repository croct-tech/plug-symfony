<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Registers the Twig helpers for the client-side SDK.
 *
 * The croct_script function renders the loader, and the croct filter (used through {% apply croct %})
 * wraps a snippet so it runs once the SDK is plugged.
 */
final class CroctScriptExtension extends AbstractExtension
{
    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('croct_script', [CroctScriptRuntime::class, 'render'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('croct', [CroctScriptRuntime::class, 'callback'], ['is_safe' => ['html']]),
        ];
    }
}
