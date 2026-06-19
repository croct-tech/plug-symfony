<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony\Twig;

use Croct\Plug\CroctCallback;
use Croct\Plug\CroctScript;
use Croct\Plug\LoadMode;
use Croct\Plug\Symfony\CroctManager;
use Croct\Plug\Symfony\EventListener\CroctScriptSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\RuntimeExtensionInterface as RuntimeExtension;

/**
 * Renders the client-side SDK bootstrap and visitor callbacks for the Twig helpers.
 *
 * The croct_script function renders the loader (and flags the request so the script subscriber does
 * not inject a second copy); the croct filter wraps a snippet so it runs once the SDK is plugged.
 */
final class CroctScriptRuntime implements RuntimeExtension
{
    private CroctManager $manager;

    private RequestStack $requestStack;

    private string $scriptSrc;

    private LoadMode $mode;

    public function __construct(
        CroctManager $manager,
        RequestStack $requestStack,
        string $scriptSrc,
        LoadMode $mode = LoadMode::DEFER,
    ) {
        $this->manager = $manager;
        $this->requestStack = $requestStack;
        $this->scriptSrc = $scriptSrc;
        $this->mode = $mode;
    }

    public function render(?string $nonce = null): string
    {
        $this->requestStack->getCurrentRequest()?->attributes->set(CroctScriptSubscriber::SCRIPT_ATTRIBUTE, true);

        return (string) new CroctScript(
            $this->scriptSrc,
            $this->manager->getPlug()->getPlugOptions(),
            $nonce,
            $this->mode,
        );
    }

    public function callback(string $body): string
    {
        $nonce = $this->requestStack->getCurrentRequest()?->attributes->get('csp_nonce');

        return (string) new CroctCallback($body, \is_string($nonce) ? $nonce : null);
    }
}
