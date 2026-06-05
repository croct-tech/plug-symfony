<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Registers the croct_script Twig function that renders the client-side SDK bootstrap.
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
}
