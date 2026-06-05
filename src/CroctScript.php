<?php

declare(strict_types=1);

namespace Croct\Plug\Symfony;

/**
 * The tags that load the client-side SDK and bootstrap it with the plug options.
 *
 * The loader is deferred so it does not block rendering, and the bootstrap runs on its load event
 * rather than on DOMContentLoaded, so personalization applies as early as possible.
 */
final class CroctScript implements \Stringable
{
    private string $scriptSrc;

    /** @var array<string, mixed> */
    private array $options;

    private ?string $nonce;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $scriptSrc, array $options, ?string $nonce = null)
    {
        $this->scriptSrc = $scriptSrc;
        $this->options = $options;
        $this->nonce = $nonce;
    }

    public function __toString(): string
    {
        $nonceAttribute = $this->nonce === null
            ? ''
            : \sprintf(' nonce="%s"', \htmlspecialchars($this->nonce, ENT_QUOTES));

        $options = \json_encode(
            $this->options,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP,
        );

        return \sprintf(
            '<script src="%s" defer%s></script>'
            . '<script%s>document.currentScript.previousElementSibling.onload=()=>croct.plug(%s)</script>',
            \htmlspecialchars($this->scriptSrc, ENT_QUOTES),
            $nonceAttribute,
            $nonceAttribute,
            $options,
        );
    }
}
