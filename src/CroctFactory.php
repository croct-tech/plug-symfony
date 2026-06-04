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
use Symfony\Contracts\Service\ResetInterface;

/**
 * Builds a request-scoped {@see Plug} from the current Symfony request.
 *
 * The facade is wrapped in the SDK's {@see VaryingResponseObserver}, which flags the request whenever
 * the visitor session is used, so {@see CroctResponseSubscriber} can finalize caching and cookies.
 * It keeps the {@see CookieStorage} so the subscriber can flush the cookies and the identity listener
 * can read the current token without flagging the request.
 */
final class CroctFactory implements ResetInterface
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
        $this->initialize();

        \assert($this->plug !== null);

        return $this->plug;
    }

    /**
     * @return list<Cookie>
     */
    public function getResponseCookies(): array
    {
        $this->initialize();

        \assert($this->storage !== null);

        return $this->storage->getResponseCookies();
    }

    /**
     * Reads the visitor token straight from storage, without flagging the request as varying.
     */
    public function getStoredUserToken(): ?Token
    {
        $this->initialize();

        \assert($this->storage !== null);

        return $this->storage->getUserToken();
    }

    public function reset(): void
    {
        $this->plug = null;
        $this->storage = null;
    }

    private function initialize(): void
    {
        if ($this->plug !== null) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        $configuration = new CookieConfiguration(
            domain: $this->cookieDomain,
            secure: $this->cookieSecure,
            sameSite: \ucfirst($this->cookieSameSite),
        );

        $this->storage = CookieStorage::fromArray($request?->cookies->all() ?? [], $configuration);

        // Build the request context straight from the Symfony request — no PSR-7 bridge needed.
        $context = $request === null
            ? new RequestContext()
            : new RequestContext(
                url: $request->getUri(),
                referrer: $request->headers->get('referer'),
                clientAgent: $request->headers->get('User-Agent'),
                clientIp: $request->getClientIp(),
                preferredLocale: $this->resolveLocale($request->getPreferredLanguage()),
            );

        // Symfony ships its own PSR-18 client, which the SDK auto-discovers. A null endpoint keeps
        // the SDK default.
        $croct = Croct::plug(
            appId: $this->appId,
            apiKey: $this->apiKey,
            storage: $this->storage,
            baseEndpointUrl: $this->baseEndpointUrl,
            context: $context,
        );

        $requestStack = $this->requestStack;

        $this->plug = new VaryingResponseObserver($croct, static function () use ($requestStack): void {
            $requestStack->getCurrentRequest()?->attributes->set(CroctResponseSubscriber::PERSONALIZED_ATTRIBUTE, true);
        });
    }

    /**
     * Resolves the locale to send: the configured value overrides detection; with detection off,
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
