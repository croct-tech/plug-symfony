<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Twig;

use Croct\Plug\CroctScript;
use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\EventListener\CroctScriptSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface as RuntimeExtension;

/**
 * Renders the client-side SDK bootstrap for the croct_script Twig function.
 *
 * It flags the request so the script subscriber does not inject a second copy.
 */
final class CroctScriptRuntime implements RuntimeExtension
{
    private CroctManager $manager;

    private RequestStack $requestStack;

    private string $scriptSrc;

    public function __construct(CroctManager $manager, RequestStack $requestStack, string $scriptSrc)
    {
        $this->manager = $manager;
        $this->requestStack = $requestStack;
        $this->scriptSrc = $scriptSrc;
    }

    public function render(?string $nonce = null): string
    {
        $this->requestStack->getCurrentRequest()?->attributes->set(CroctScriptSubscriber::SCRIPT_ATTRIBUTE, true);

        return (string) new CroctScript($this->scriptSrc, $this->manager->getPlugOptions(), $nonce);
    }
}
