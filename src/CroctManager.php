<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

use Croct\Plug\Content\ContentProvider;
use Croct\Plug\Cookie;
use Croct\Plug\CookieConfiguration;
use Croct\Plug\CookieStorage;
use Croct\Plug\Croct;
use Croct\Plug\IdentityResolver;
use Croct\Plug\LocaleResolver;
use Croct\Plug\Plug;
use Croct\Plug\RequestContext;
use Croct\Plug\Symfony\EventListener\CroctResponseSubscriber;
use Croct\Plug\VaryingResponseObserver;
use Psr\Log\LoggerInterface as Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface as ResettableService;

/**
 * Builds and manages the request-scoped plug for the current Symfony request.
 *
 * Besides creating the plug, it reconciles the visitor identity with the authenticated user and
 * exposes the session cookies and the client-side bootstrap options for the response.
 */
final class CroctManager implements ResettableService
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

    private LocaleResolver $localeResolver;

    private ?ContentProvider $contentProvider;

    private ?Logger $logger;

    private int $tokenDuration;

    private ?IdentityResolver $identity;

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
        ?LocaleResolver $localeResolver = null,
        ?ContentProvider $contentProvider = null,
        ?Logger $logger = null,
        int $tokenDuration = Croct::DEFAULT_TOKEN_DURATION,
        ?IdentityResolver $identity = null,
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
        $this->localeResolver = $localeResolver ?? new RequestLocaleResolver($requestStack);
        $this->contentProvider = $contentProvider;
        $this->logger = $logger;
        $this->tokenDuration = $tokenDuration;
        $this->identity = $identity;
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
     * Reconciles the visitor token with the authenticated user.
     *
     * When the logged-in user no longer matches the cookie token, the visitor is re-identified
     * through the session. That flags the request as varying, so the new cookie is written and
     * the response goes private. A matching or anonymous visitor is left untouched, keeping the
     * response shared-cacheable, the same way plug-next and plug-nuxt reconcile.
     */
    public function reconcile(): void
    {
        if ($this->identity === null) {
            return;
        }

        $stored = $this->getStorage()->getUserToken();
        $userId = $this->identity->getUserId();

        $matches = $userId === null
            ? ($stored?->isAnonymous() ?? true)
            : ($stored?->isSubject($userId) ?? false);

        if ($matches) {
            return;
        }

        if ($userId === null) {
            $this->getPlug()->anonymize();
        } else {
            $this->getPlug()->identify($userId);
        }
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
                previewToken: RequestContext::resolvePreviewToken(self::getPreviewToken($request)),
                url: $request->getUri(),
                referrer: $request->headers->get('referer'),
                clientAgent: $request->headers->get('User-Agent'),
                clientIp: $request->getClientIp(),
                preferredLocale: $this->resolveLocale($this->localeResolver->getLocale()),
            );

        $croct = Croct::plug(
            appId: $this->appId,
            apiKey: $this->apiKey,
            storage: $this->getStorage(),
            identity: $this->identity,
            baseEndpointUrl: $this->baseEndpointUrl,
            tokenDuration: $this->tokenDuration,
            contentProvider: $this->contentProvider,
            context: $context,
            logger: $this->logger,
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
    private static function getPreviewToken(Request $request): ?string
    {
        $value = $request->query->getString(RequestContext::PREVIEW_QUERY_PARAMETER);

        return $value !== '' ? $value : null;
    }

    private function resolveLocale(?string $detected): ?string
    {
        if (!$this->localeEnabled) {
            return $this->defaultLocale;
        }

        return $this->defaultLocale ?? $detected;
    }
}
