<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Twig;

use Croct\Plug\Symfony\CroctFactory;
use Croct\Plug\Symfony\CroctScript;
use Croct\Plug\Symfony\EventListener\CroctScriptSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface as RuntimeExtension;

/**
 * Renders the client-side SDK bootstrap for the croct_script Twig function.
 *
 * It flags the request so {@see CroctScriptSubscriber} does not inject a second copy.
 */
final class CroctScriptRuntime implements RuntimeExtension
{
    private CroctFactory $factory;

    private RequestStack $requestStack;

    private string $scriptSrc;

    public function __construct(CroctFactory $factory, RequestStack $requestStack, string $scriptSrc)
    {
        $this->factory = $factory;
        $this->requestStack = $requestStack;
        $this->scriptSrc = $scriptSrc;
    }

    public function render(?string $nonce = null): string
    {
        $this->requestStack->getCurrentRequest()?->attributes->set(CroctScriptSubscriber::SCRIPT_ATTRIBUTE, true);

        return (string) new CroctScript($this->scriptSrc, $this->factory->getPlugOptions(), $nonce);
    }
}
