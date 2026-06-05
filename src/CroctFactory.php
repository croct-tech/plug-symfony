<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Croct\Plug\Cookie;
use Croct\Plug\CookieConfiguration;
use Croct\Plug\CookieStorage;
use Croct\Plug\Croct;
use Croct\Plug\Plug;
use Croct\Plug\RequestContext;
use Croct\Plug\Symfony\EventListener\CroctResponseSubscriber;
use Croct\Plug\Token;
use Croct\Plug\VaryingResponseObserver;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface as ResettableService;

/**
 * Builds a request-scoped {@see Plug} from the current Symfony request.
 */
final class CroctFactory implements ResettableService
{
    private RequestStack $requestStack;

    private string $appId;

    private string $apiKey;

    private ?string $baseEndpointUrl;

    private bool $localeEnabled;

    private ?string $defaultLocale;

    private ?string $cookieDomain;

    private bool $cookieSecure;

    private string $cookieSameSite;

    private ?Plug $plug = null;

    private ?CookieStorage $storage = null;

    public function __construct(
        RequestStack $requestStack,
        string $appId,
        string $apiKey,
        ?string $baseEndpointUrl = null,
        bool $localeEnabled = true,
        ?string $defaultLocale = null,
        ?string $cookieDomain = null,
        bool $cookieSecure = true,
        string $cookieSameSite = 'none',
    ) {
        $this->requestStack = $requestStack;
        $this->appId = $appId;
        $this->apiKey = $apiKey;
        $this->baseEndpointUrl = $baseEndpointUrl;
        $this->localeEnabled = $localeEnabled;
        $this->defaultLocale = $defaultLocale;
        $this->cookieDomain = $cookieDomain;
        $this->cookieSecure = $cookieSecure;
        $this->cookieSameSite = $cookieSameSite;
    }

    public function getPlug(): Plug
    {
        return $this->plug ??= $this->createPlug();
    }

    /**
     * @return list<Cookie>
     */
    public function getResponseCookies(): array
    {
        return $this->getStorage()->getResponseCookies();
    }

    /**
     * Reads the visitor token straight from storage, without flagging the request as varying.
     */
    public function getStoredUserToken(): ?Token
    {
        return $this->getStorage()->getUserToken();
    }

    /**
     * Returns the visitor-independent options for bootstrapping the client-side SDK.
     *
     * Built without resolving the Plug so it stays cache-neutral and works before the credentials
     * are validated. The visitor identity is read client-side from the cookies.
     *
     * @return array<string, mixed>
     */
    public function getPlugOptions(): array
    {
        return [
            'appId' => $this->appId,
            'disableCidMirroring' => true,
            'cookie' => $this->createCookieConfiguration()->toBrowserCookies(),
        ];
    }

    public function reset(): void
    {
        $this->plug = null;
        $this->storage = null;
    }

    private function getStorage(): CookieStorage
    {
        return $this->storage ??= $this->createStorage();
    }

    private function createStorage(): CookieStorage
    {
        $request = $this->requestStack->getCurrentRequest();

        return CookieStorage::fromArray($request?->cookies->all() ?? [], $this->createCookieConfiguration());
    }

    private function createCookieConfiguration(): CookieConfiguration
    {
        return new CookieConfiguration(
            domain: $this->cookieDomain,
            secure: $this->cookieSecure,
            sameSite: \ucfirst($this->cookieSameSite),
        );
    }

    private function createPlug(): Plug
    {
        $request = $this->requestStack->getCurrentRequest();

        $context = $request === null
            ? new RequestContext()
            : new RequestContext(
                url: $request->getUri(),
                referrer: $request->headers->get('referer'),
                clientAgent: $request->headers->get('User-Agent'),
                clientIp: $request->getClientIp(),
                preferredLocale: $this->resolveLocale($request->getPreferredLanguage()),
            );

        $croct = Croct::plug(
            appId: $this->appId,
            apiKey: $this->apiKey,
            storage: $this->getStorage(),
            baseEndpointUrl: $this->baseEndpointUrl,
            context: $context,
        );

        $requestStack = $this->requestStack;

        return new VaryingResponseObserver($croct, static function () use ($requestStack): void {
            $requestStack->getCurrentRequest()?->attributes->set(CroctResponseSubscriber::PERSONALIZED_ATTRIBUTE, true);
        });
    }

    /**
     * Resolves the locale to send. The configured value overrides detection. With detection off,
     * only the configured value (if any) is used.
     */
    private function resolveLocale(?string $detected): ?string
    {
        if (!$this->localeEnabled) {
            return $this->defaultLocale;
        }

        return $this->defaultLocale ?? $detected;
    }
}
