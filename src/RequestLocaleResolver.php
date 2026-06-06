<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Croct\Plug\LocaleResolver;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Detects the locale from the current request's Accept-Language header.
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
        return $this->requestStack->getCurrentRequest()?->getPreferredLanguage();
    }
}
