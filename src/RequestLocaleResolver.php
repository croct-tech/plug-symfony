<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Croct\Plug\LocaleResolver;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Follows the locale Symfony resolved for the current request.
 */
final class RequestLocaleResolver implements LocaleResolver
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function getLocale(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale();
    }
}
